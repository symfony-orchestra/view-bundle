<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ViewBundle\Utils;

use ChamberOrchestra\ViewBundle\Attribute\Type;
use ChamberOrchestra\ViewBundle\PropertyAccessor\ReflectionPropertyAccessor;
use ChamberOrchestra\ViewBundle\PropertyAccessor\ReflectionService;
use ChamberOrchestra\ViewBundle\View\BindView;
use ChamberOrchestra\ViewBundle\View\IterableView;
use ChamberOrchestra\ViewBundle\View\ViewInterface;
use Doctrine\Common\Util\ClassUtils;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;

class BindUtils
{
    private static bool $configured = false;
    private static string $cacheNamespace = 'bind_view';
    private static int $cacheLifetime = 0;
    private static string $version = '';
    private static ?string $warmCachePath = null;
    private const int MAX_CACHE_SIZE = 1024;

    /** @var array<string, array<string, array{0: \ReflectionProperty, 1: \ReflectionProperty}>> */
    private static array $storage = [];

    /** @var array<string, array<string, array{0: array{class: class-string, name: string}, 1: array{class: class-string, name: string}}>>|null */
    private static ?array $warmedCache = null;
    private ReflectionService $reflectionService;

    private function __construct()
    {
        $this->reflectionService = new ReflectionService();
    }

    /**
     * Configures caching parameters. Called once at bootstrap via SetVersionSubscriber.
     * Subsequent calls are ignored to ensure configuration consistency.
     *
     * @param string      $buildId       Unique build identifier for cache versioning (e.g., container.build_id)
     * @param int         $cacheLifetime Cache lifetime in seconds (0 = disabled, used in debug mode)
     * @param string      $namespace     Cache namespace prefix to prevent collisions
     * @param string|null $shareDir      Optional share directory for loading warmed cache
     *
     * @throws \InvalidArgumentException If cacheLifetime is negative or namespace is empty
     */
    public static function configure(string $buildId, int $cacheLifetime = 3600, string $namespace = 'bind_view', ?string $shareDir = null): void
    {
        if (self::$configured) {
            return;
        }

        if ($cacheLifetime < 0) {
            throw new \InvalidArgumentException(\sprintf('Cache lifetime must be non-negative, got %d', $cacheLifetime));
        }

        if ('' === $namespace) {
            throw new \InvalidArgumentException('Cache namespace cannot be empty');
        }

        self::$configured = true;
        self::$version = $buildId;
        self::$cacheLifetime = $cacheLifetime;
        self::$cacheNamespace = $namespace;
        self::$warmCachePath = $shareDir ? $shareDir."/bind_utils_mappings_{$buildId}.php" : null;
    }

    public static function instance(): self
    {
        /** @var self|null $instance */
        static $instance;

        return $instance ??= new self();
    }

    /**
     * Synchronizes properties from source object to target object by copying matching property values.
     *
     * Only properties with matching names and compatible types are synchronized. If a target property
     * already has a non-null value that is publicly readable, it will not be overwritten.
     *
     * For properties typed as ViewInterface, the appropriate view class is instantiated automatically.
     * For IterableView properties with a #[Type] attribute, typed collections are created.
     *
     * @throws \ReflectionException
     */
    public function sync(object $target, object $source): void
    {
        foreach ($this->getIntersectedProperties($target, $source) as [$targetProperty, $sourceProperty]) {
            /** @var \ReflectionProperty $targetProperty */
            /** @var \ReflectionProperty $sourceProperty */
            $propertyName = $targetProperty->getName();

            // Skip properties that already have non-null values
            if ($this->getAccessor()->isStrictlyReadable($target, $propertyName)) {
                if (null !== $this->getAccessor()->getValue($target, $propertyName)) {
                    continue;
                }
            }

            $this->getAccessor()->setValue($target, $propertyName, $this->getValue($targetProperty, $sourceProperty, $source));
        }
    }

    private function getValue(\ReflectionProperty $targetProperty, \ReflectionProperty $sourceProperty, object $source): mixed
    {
        if (null === $value = $this->getAccessor()->getValue($source, $sourceProperty->getName())) {
            return null;
        }

        $type = $targetProperty->getType();

        if (null !== $type && $this->isView($type)) {
            if (!$type instanceof \ReflectionNamedType) {
                throw new \LogicException(\sprintf('Property %s::$%s has view type but is not a named type. Union/intersection types with ViewInterface are not supported.', $targetProperty->getDeclaringClass()->getName(), $targetProperty->getName()));
            }

            if ($this->isTypedIterableView($targetProperty) && \is_iterable($value)) {
                return $this->buildIterableView($targetProperty, $value);
            }

            return new ($type->getName())($value);
        }

        return $value;
    }

    private function isTypedIterableView(\ReflectionProperty $property): bool
    {
        $type = $property->getType();

        if (!$type instanceof \ReflectionNamedType) {
            return false;
        }

        return \is_a($type->getName(), IterableView::class, true) && \count($property->getAttributes(Type::class)) > 0;
    }

    /**
     * @param iterable<mixed> $value
     */
    private function buildIterableView(\ReflectionProperty $property, iterable $value): IterableView
    {
        /** @var \ReflectionAttribute<Type> $attr */
        $attr = \current($property->getAttributes(Type::class));
        /** @var Type $type */
        $type = $attr->newInstance();

        return new IterableView($value, fn (object|array $v) => new ($type->class)($v));
    }

    /**
     * @return array<string, array{0: \ReflectionProperty, 1: \ReflectionProperty}>
     *
     * @throws \ReflectionException
     */
    private function getIntersectedProperties(object $target, object $source): array
    {
        $targetClassName = ClassUtils::getClass($target);
        $sourceClassName = ClassUtils::getClass($source);
        $cacheKey = $targetClassName.'@'.$sourceClassName;

        // 1. Check runtime cache (existing)
        if (isset(self::$storage[$cacheKey])) {
            return self::$storage[$cacheKey];
        }

        // 2. Check warmed cache (NEW)
        if ($warmed = $this->loadFromWarmedCache($cacheKey)) {
            return self::$storage[$cacheKey] = $warmed;
        }

        // 3. Fall back to reflection (existing)
        $targetProperties = $this->reflectionService->getReflectionProperties($targetClassName);
        $sourceProperties = $this->reflectionService->getReflectionProperties($sourceClassName);

        $intersection = [];
        $commonProperties = \array_intersect_key($targetProperties, $sourceProperties);

        foreach ($commonProperties as $propertyName => $targetProperty) {
            $sourceProperty = $sourceProperties[$propertyName];

            $targetType = $targetProperty->getType();
            $sourceType = $sourceProperty->getType();

            if (null === $targetType || null === $sourceType) {
                continue;
            }

            if (!$this->isReflectionTypeValidForInitialization($targetType, $sourceType)) {
                continue;
            }

            $intersection[$propertyName] = [$targetProperty, $sourceProperty];
        }

        if (\count(self::$storage) >= self::MAX_CACHE_SIZE) {
            unset(self::$storage[\array_key_first(self::$storage)]);
        }

        return self::$storage[$cacheKey] = $intersection;
    }

    /**
     * Load property mappings from warmed cache.
     *
     * @return array<string, array{0: \ReflectionProperty, 1: \ReflectionProperty}>|null
     */
    private function loadFromWarmedCache(string $cacheKey): ?array
    {
        if (null === self::$warmCachePath || !\file_exists(self::$warmCachePath)) {
            return null;
        }

        // Load warmed cache file once
        if (null === self::$warmedCache) {
            /** @var array<string, array<string, array{0: array{class: class-string, name: string}, 1: array{class: class-string, name: string}}>> $loaded */
            $loaded = require self::$warmCachePath;
            self::$warmedCache = $loaded;
        }

        if (!isset(self::$warmedCache[$cacheKey])) {
            return null;
        }

        $cached = self::$warmedCache[$cacheKey];
        $result = [];

        // Reconstruct ReflectionProperty objects from cached data
        foreach ($cached as $propertyName => $data) {
            [$targetData, $sourceData] = $data;

            $targetReflection = new \ReflectionClass($targetData['class']);
            $sourceReflection = new \ReflectionClass($sourceData['class']);

            $result[$propertyName] = [
                $targetReflection->getProperty($targetData['name']),
                $sourceReflection->getProperty($sourceData['name']),
            ];
        }

        return $result;
    }

    private function isReflectionTypeValidForInitialization(\ReflectionType $targetType, \ReflectionType $sourceType): bool
    {
        if (!$targetType instanceof \ReflectionNamedType) {
            // union types are not supported for binding
            return false;
        }

        $sourceTypes = $sourceType instanceof \ReflectionUnionType ? $sourceType->getTypes() : [$sourceType];

        if ($targetType->isBuiltin()) {
            // pass all built in values, in case if one of the source values is built in either
            foreach ($sourceTypes as $st) {
                if ($st instanceof \ReflectionNamedType && $st->isBuiltin()) {
                    return true;
                }
            }

            return false;
        }

        if ($this->isAutoConfigurableType($targetType)) {
            return true;
        }

        foreach ($sourceTypes as $st) {
            // all custom objects are valid only if the types are valid
            if ($st instanceof \ReflectionNamedType && \is_a($targetType->getName(), $st->getName(), true)) {
                return true;
            }
        }

        return false;
    }

    private function isView(\ReflectionType $type): bool
    {
        return $type instanceof \ReflectionNamedType && \is_a($type->getName(), ViewInterface::class, true);
    }

    private function isAutoConfigurableType(\ReflectionNamedType $type): bool
    {
        return \array_any([BindView::class, IterableView::class], fn ($class) => \is_a($type->getName(), $class, true));
    }

    private function getAccessor(): ReflectionPropertyAccessor
    {
        /** @var ReflectionPropertyAccessor|null $accessor */
        static $accessor;

        if (null !== $accessor) {
            return $accessor;
        }

        $accessorPrefixes = ['get', 'is', 'has'];
        $disabledMutatorPrefixes = ['-', '-'];

        $readExtractor = new ReflectionExtractor(
            [],
            $accessorPrefixes,
            $disabledMutatorPrefixes,
            false,
            ReflectionExtractor::ALLOW_PRIVATE | ReflectionExtractor::ALLOW_PROTECTED | ReflectionExtractor::ALLOW_PUBLIC,
            null,
            ReflectionExtractor::DISALLOW_MAGIC_METHODS
        );

        $writeExtractor = new ReflectionExtractor(
            ['set'],
            $accessorPrefixes,
            $disabledMutatorPrefixes,
            false,
            ReflectionExtractor::ALLOW_PUBLIC,
            null,
            ReflectionExtractor::DISALLOW_MAGIC_METHODS
        );

        $cache = self::$configured
            ? PropertyAccessor::createCache(self::$cacheNamespace, self::$cacheLifetime, self::$version)
            : null;

        $propertyAccessor = new PropertyAccessor(
            ReflectionExtractor::DISALLOW_MAGIC_METHODS,
            PropertyAccessor::THROW_ON_INVALID_INDEX | PropertyAccessor::THROW_ON_INVALID_PROPERTY_PATH,
            $cache,
            $readExtractor,
            $writeExtractor
        );

        return $accessor = new ReflectionPropertyAccessor($propertyAccessor, $this->reflectionService);
    }
}
