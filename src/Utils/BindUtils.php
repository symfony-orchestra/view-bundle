<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ViewBundle\Utils;

use Doctrine\Common\Util\ClassUtils;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use ChamberOrchestra\ViewBundle\Attribute\Type;
use ChamberOrchestra\ViewBundle\PropertyAccessor\ReflectionPropertyAccessor;
use ChamberOrchestra\ViewBundle\PropertyAccessor\ReflectionService;
use ChamberOrchestra\ViewBundle\View\BindView;
use ChamberOrchestra\ViewBundle\View\IterableView;
use ChamberOrchestra\ViewBundle\View\ViewInterface;

class BindUtils
{
    private static bool $configured = false;
    private static string $cacheNamespace = 'bind_view';
    private static int $cacheLifetime = 0;
    private static string $version = '';
    private static array $storage = [];
    private ReflectionService $reflectionService;

    private function __construct()
    {
        $this->reflectionService = new ReflectionService();
    }

    public static function configure(string $buildId, int $cacheLifetime = 3600, string $namespace = 'bind_view'): void
    {
        if (static::$configured) {
            return;
        }

        if ($cacheLifetime < 0) {
            throw new \InvalidArgumentException(\sprintf('Cache lifetime must be non-negative, got %d', $cacheLifetime));
        }

        if ($namespace === '') {
            throw new \InvalidArgumentException('Cache namespace cannot be empty');
        }

        static::$configured = true;
        static::$version = $buildId;
        static::$cacheLifetime = $cacheLifetime;
        static::$cacheNamespace = $namespace;
    }

    public static function instance(): self
    {
        static $instance;
        return $instance ??= new static();
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

        if ($this->isView($type)) {
            if (!$type instanceof \ReflectionNamedType) {
                throw new \LogicException('Expected ReflectionNamedType for view instantiation');
            }

            if ($this->isTypedIterableView($targetProperty)) {
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

    private function buildIterableView(\ReflectionProperty $property, iterable $value): IterableView
    {
        /** @var \ReflectionAttribute $attr */
        $attr = \current($property->getAttributes(Type::class));
        /** @var Type $type */
        $type = $attr->newInstance();

        return new IterableView($value, fn(object|array $v) => new ($type->class)($v));
    }

    /**
     * @throws \ReflectionException
     */
    private function getIntersectedProperties(object $target, object $source): array
    {
        $targetClassName = ClassUtils::getClass($target);
        $sourceClassName = ClassUtils::getClass($source);
        $cacheKey = $targetClassName . '@' . $sourceClassName;

        if (isset(self::$storage[$cacheKey])) {
            return self::$storage[$cacheKey];
        }

        $targetProperties = $this->reflectionService->getReflectionProperties($targetClassName);
        $sourceProperties = $this->reflectionService->getReflectionProperties($sourceClassName);

        $intersection = [];
        $commonProperties = \array_intersect_key($targetProperties, $sourceProperties);

        foreach ($commonProperties as $propertyName => $targetProperty) {
            $sourceProperty = $sourceProperties[$propertyName];

            $targetType = $targetProperty->getType();
            $sourceType = $sourceProperty->getType();

            if ($targetType === null || $sourceType === null) {
                continue;
            }

            if (!$this->isReflectionTypeValidForInitialization($targetType, $sourceType)) {
                continue;
            }

            $intersection[$propertyName] = [$targetProperty, $sourceProperty];
        }

        return self::$storage[$cacheKey] = $intersection;
    }

    private function isReflectionTypeValidForInitialization(\ReflectionType $targetType, \ReflectionType $sourceType): bool
    {
        if (!$targetType instanceof \ReflectionNamedType) {
            // union types are not supported for binding
            return false;
        }

        $sourceTypes = $sourceType instanceof \ReflectionUnionType ? $sourceType->getTypes() : [$sourceType];

        if ($targetType->isBuiltin()) {
            //pass all built in values, in case if one of the source values is built in either
            foreach ($sourceTypes as $sourceType) {
                if ($sourceType->isBuiltin()) {
                    return true;
                }
            }

            return false;
        }

        if ($this->isAutoConfigurableType($targetType)) {
            return true;
        }

        foreach ($sourceTypes as $sourceType) {
            // all custom objects are valid only if the types are valid
            if (\is_a($targetType->getName(), $sourceType->getName(), true)) {
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
        return \array_any([BindView::class, IterableView::class], fn($class) => \is_a($type->getName(), $class, true));

    }

    private function getAccessor(): ReflectionPropertyAccessor
    {
        static $accessor;

        if ($accessor !== null) {
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

        $cache = static::$configured
            ? PropertyAccessor::createCache(static::$cacheNamespace, static::$cacheLifetime, static::$version)
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