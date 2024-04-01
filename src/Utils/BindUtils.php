<?php
declare(strict_types=1);

/*
 * This file is part of the SymfonyOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SymfonyOrchestra\ViewBundle\Utils;

use Doctrine\Common\Util\ClassUtils;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use SymfonyOrchestra\ViewBundle\Attribute\Type;
use SymfonyOrchestra\ViewBundle\PropertyAccessor\ReflectionPropertyAccessor;
use SymfonyOrchestra\ViewBundle\PropertyAccessor\ReflectionService;
use SymfonyOrchestra\ViewBundle\View\BindView;
use SymfonyOrchestra\ViewBundle\View\IterableView;
use SymfonyOrchestra\ViewBundle\View\ViewInterface;

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
     * @throws \ReflectionException
     */
    public function sync(object $target, object $source): void
    {
        foreach ($this->getIntersectedProperties($target, $source) as [$targetProperty, $sourceProperty]) {
            /** @var \ReflectionProperty $targetProperty */
            /** @var \ReflectionProperty $sourceProperty */
            if ($this->getAccessor()->isStrictlyReadable($target, $targetProperty->getName())) {
                if (null !== $this->getAccessor()->getValue($target, $targetProperty->getName())) {
                    continue;
                }
            }
            $this->getAccessor()->setValue($target, $targetProperty->getName(), $this->getValue($targetProperty, $sourceProperty, $source));
        }
    }

    private function getValue(\ReflectionProperty $targetProperty, \ReflectionProperty $sourceProperty, object $source): mixed
    {
        if (null === $value = $this->getAccessor()->getValue($source, $sourceProperty->getName())) {
            return null;
        }

        if ($this->isView($type = $targetProperty->getType())) {
            /** @var \ReflectionNamedType $type */
            if ($this->isTypedIterableView($targetProperty)) {
                return $this->buildIterableView($targetProperty, $value);
            }

            return new ($type->getName())($value);
        }

        return $value;
    }

    private function isTypedIterableView(\ReflectionProperty $property): bool
    {
        return \is_a($property->getType()->getName(), IterableView::class, true) && \count($property->getAttributes(Type::class)) > 0;
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
        $key = \implode('@', [$targetClassName = ClassUtils::getClass($target), $sourceClassName = ClassUtils::getClass($source)]);
        if (isset(self::$storage[$key])) {
            return self::$storage[$key];
        }

        $targetProperties = $this->reflectionService->getReflectionProperties($targetClassName);
        $sourceProperties = $this->reflectionService->getReflectionProperties($sourceClassName);

        $intersection = [];
        foreach (\array_intersect(\array_keys($targetProperties), \array_keys($sourceProperties)) as $key) {
            /** @var \ReflectionProperty $targetProperty */
            $targetProperty = $targetProperties[$key];
            /** @var \ReflectionProperty $sourceProperty */
            $sourceProperty = $sourceProperties[$key];

            if (!$this->isReflectionTypeValidForInitialization($targetProperty->getType(), $sourceProperty->getType())) {
                continue;
            }

            $intersection[$key] = [$targetProperty, $sourceProperty];
        }

        return self::$storage[$key] = $intersection;
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
        foreach ([BindView::class, IterableView::class] as $class) {
            if (\is_a($type->getName(), $class, true)) {
                return true;
            }
        }

        return false;
    }

    private function getAccessor(): ReflectionPropertyAccessor
    {
        static $accessor;
        return $accessor ??= new ReflectionPropertyAccessor(new PropertyAccessor(
            ReflectionExtractor::DISALLOW_MAGIC_METHODS,
            PropertyAccessor::THROW_ON_INVALID_INDEX | PropertyAccessor::THROW_ON_INVALID_PROPERTY_PATH,
            static::$configured ? PropertyAccessor::createCache(static::$cacheNamespace, static::$cacheLifetime, static::$version) : null,
            new ReflectionExtractor([], $a = ['get', 'is', 'has'], $b = ['-', '-'], false, ReflectionExtractor::ALLOW_PRIVATE | ReflectionExtractor::ALLOW_PROTECTED | ReflectionExtractor::ALLOW_PUBLIC, null, ReflectionExtractor::DISALLOW_MAGIC_METHODS),
            new ReflectionExtractor(['set'], $a, $b, false, ReflectionExtractor::ALLOW_PUBLIC, null, ReflectionExtractor::DISALLOW_MAGIC_METHODS)
        ), $this->reflectionService);
    }
}