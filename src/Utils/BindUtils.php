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
    private const int CACHE_LIFETIME_SECONDS = 86400; // 24 hours
    private const int MAX_CACHE_SIZE = 1024;

    private string $cacheNamespace;
    private int $cacheLifetime;
    private string $version;
    private ?string $warmCachePath;

    /** @var array<string, array<string, array{0: \ReflectionProperty, 1: \ReflectionProperty}>> */
    private array $storage = [];

    /** @var array<string, array<string, array{0: array{class: class-string, name: string}, 1: array{class: class-string, name: string}}>>|null */
    private ?array $warmedCache = null;
    private ReflectionService $reflectionService;
    private ?ReflectionPropertyAccessor $accessor = null;

    public function __construct(
        string $buildId = '',
        bool $debug = false,
        ?string $shareDir = null,
    ) {
        $this->version = $buildId;
        $this->cacheLifetime = $debug ? 0 : self::CACHE_LIFETIME_SECONDS;
        $this->cacheNamespace = 'view_bind';
        $this->warmCachePath = $shareDir ? $shareDir."/bind_utils_mappings_{$buildId}.php" : null;
        $this->reflectionService = new ReflectionService();
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

    public static function isReflectionTypeValidForInitialization(\ReflectionType $targetType, \ReflectionType $sourceType): bool
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

        if (self::isAutoConfigurableType($targetType)) {
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

    public static function isView(\ReflectionType $type): bool
    {
        return $type instanceof \ReflectionNamedType && \is_a($type->getName(), ViewInterface::class, true);
    }

    public static function isAutoConfigurableType(\ReflectionNamedType $type): bool
    {
        return \array_any([BindView::class, IterableView::class], fn ($class) => \is_a($type->getName(), $class, true));
    }

    private function getValue(\ReflectionProperty $targetProperty, \ReflectionProperty $sourceProperty, object $source): mixed
    {
        if (null === $value = $this->getAccessor()->getValue($source, $sourceProperty->getName())) {
            return null;
        }

        $type = $targetProperty->getType();

        if (null !== $type && self::isView($type)) {
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

        // 1. Check runtime cache
        if (isset($this->storage[$cacheKey])) {
            return $this->storage[$cacheKey];
        }

        // 2. Check warmed cache
        if ($warmed = $this->loadFromWarmedCache($cacheKey)) {
            return $this->storage[$cacheKey] = $warmed;
        }

        // 3. Fall back to reflection
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

            if (!self::isReflectionTypeValidForInitialization($targetType, $sourceType)) {
                continue;
            }

            $intersection[$propertyName] = [$targetProperty, $sourceProperty];
        }

        if (\count($this->storage) >= self::MAX_CACHE_SIZE) {
            unset($this->storage[\array_key_first($this->storage)]);
        }

        return $this->storage[$cacheKey] = $intersection;
    }

    /**
     * Load property mappings from warmed cache.
     *
     * @return array<string, array{0: \ReflectionProperty, 1: \ReflectionProperty}>|null
     */
    private function loadFromWarmedCache(string $cacheKey): ?array
    {
        if (null === $this->warmCachePath || !\file_exists($this->warmCachePath)) {
            return null;
        }

        // Load warmed cache file once
        if (null === $this->warmedCache) {
            /** @var array<string, array<string, array{0: array{class: class-string, name: string}, 1: array{class: class-string, name: string}}>> $loaded */
            $loaded = require $this->warmCachePath;
            $this->warmedCache = $loaded;
        }

        if (!isset($this->warmedCache[$cacheKey])) {
            return null;
        }

        $cached = $this->warmedCache[$cacheKey];
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

    private function getAccessor(): ReflectionPropertyAccessor
    {
        if (null !== $this->accessor) {
            return $this->accessor;
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

        $cache = '' !== $this->version
            ? PropertyAccessor::createCache($this->cacheNamespace, $this->cacheLifetime, $this->version)
            : null;

        $propertyAccessor = new PropertyAccessor(
            ReflectionExtractor::DISALLOW_MAGIC_METHODS,
            PropertyAccessor::THROW_ON_INVALID_INDEX | PropertyAccessor::THROW_ON_INVALID_PROPERTY_PATH,
            $cache,
            $readExtractor,
            $writeExtractor
        );

        return $this->accessor = new ReflectionPropertyAccessor($propertyAccessor, $this->reflectionService);
    }
}
