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
use ChamberOrchestra\ViewBundle\View\CachedView;
use ChamberOrchestra\ViewBundle\View\IterableView;
use ChamberOrchestra\ViewBundle\View\SourceCacheSignatureInterface;
use ChamberOrchestra\ViewBundle\View\ViewInterface;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\Persistence\Proxy;
use Symfony\Component\PropertyAccess\Exception\UninitializedPropertyException;
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

    /** @var array<string, string> Getter method name per "Class::property"; empty string means direct property access */
    private array $getterCache = [];
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
        // Initialize Doctrine proxies once per sync instead of on every property access
        if ($source instanceof Proxy && !$source->__isInitialized()) {
            $source->__load();
        }

        foreach ($this->getIntersectedProperties($target, $source) as [$targetProperty, $sourceProperty]) {
            /** @var \ReflectionProperty $targetProperty */
            /* @var \ReflectionProperty $sourceProperty */

            // Skip properties that already have non-null values
            if ($this->hasNonNullValue($target, $targetProperty)) {
                continue;
            }

            $this->writeTargetValue($target, $targetProperty, $this->getValue($targetProperty, $sourceProperty, $source));
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
        return \array_any([BindView::class, IterableView::class], static fn ($class) => \is_a($type->getName(), $class, true));
    }

    private function getValue(\ReflectionProperty $targetProperty, \ReflectionProperty $sourceProperty, object $source): mixed
    {
        if (null === $value = $this->readSourceValue($source, $sourceProperty)) {
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

    /**
     * Whether the property already holds a non-null, publicly readable value.
     *
     * Getters take priority over direct property access, mirroring PropertyAccessor semantics.
     */
    private function hasNonNullValue(object $target, \ReflectionProperty $property): bool
    {
        $getter = $this->resolveGetter($target::class, $property->getName());

        if ('' !== $getter) {
            return null !== $target->{$getter}();
        }

        if ($property->isPublic()) {
            return $property->isInitialized($target) && null !== $property->getValue($target);
        }

        return false;
    }

    private function writeTargetValue(object $target, \ReflectionProperty $property, mixed $value): void
    {
        if ($property->isPublic()) {
            $property->setValue($target, $value);

            return;
        }

        // Non-public target properties go through the accessor for setter support and proper failures
        $this->getAccessor()->setValue($target, $property->getName(), $value);
    }

    private function readSourceValue(object $source, \ReflectionProperty $property): mixed
    {
        $getter = $this->resolveGetter($source::class, $property->getName());

        if ('' !== $getter) {
            return $source->{$getter}();
        }

        if (!$property->isInitialized($source)) {
            throw new UninitializedPropertyException(\sprintf('The property "%s::$%s" is not initialized.', $property->getDeclaringClass()->getName(), $property->getName()));
        }

        return $property->getValue($source);
    }

    /**
     * Resolve a public getter ("get"/"is"/"has" prefix) for the property, cached per class.
     *
     * @param class-string $className
     *
     * @return string The getter method name, or an empty string when the property must be read directly
     */
    private function resolveGetter(string $className, string $propertyName): string
    {
        $cacheKey = $className.'::'.$propertyName;

        if (isset($this->getterCache[$cacheKey])) {
            return $this->getterCache[$cacheKey];
        }

        $reflection = new \ReflectionClass($className);
        $suffix = \ucfirst($propertyName);

        foreach (['get', 'is', 'has'] as $prefix) {
            $method = $prefix.$suffix;

            if (!$reflection->hasMethod($method)) {
                continue;
            }

            $reflectionMethod = $reflection->getMethod($method);

            if ($reflectionMethod->isPublic() && !$reflectionMethod->isStatic() && 0 === $reflectionMethod->getNumberOfRequiredParameters()) {
                return $this->getterCache[$cacheKey] = $method;
            }
        }

        return $this->getterCache[$cacheKey] = '';
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

        if ($type->cached) {
            /** @var class-string<SourceCacheSignatureInterface> $viewClass */
            $viewClass = $type->class;

            // Each element becomes a cheap CachedView descriptor: the element view is only
            // built and normalized when its per-item cache entry misses
            return new IterableView($value, static fn (object $v) => new CachedView($v, $viewClass));
        }

        return new IterableView($value, static fn (object|array $v) => new ($type->class)($v));
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
