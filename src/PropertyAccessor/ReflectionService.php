<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ViewBundle\PropertyAccessor;

use Doctrine\Common\Util\ClassUtils;

class ReflectionService
{
    private const MAX_CACHE_SIZE = 512;

    /** @var array<string, array<string, \ReflectionProperty>> */
    private array $storage = [];

    /**
     * @param \ReflectionClass<object>|class-string $class
     *
     * @return array<string, \ReflectionProperty>
     *
     * @throws \ReflectionException
     */
    public function getReflectionProperties(\ReflectionClass|string $class): array
    {
        $className = $class instanceof \ReflectionClass ? $class->getName() : $class;
        if (isset($this->storage[$className])) {
            return $this->storage[$className];
        }

        $cache = [];
        $class = $class instanceof \ReflectionClass ? $class : new \ReflectionClass($className);
        foreach ($class->getProperties() as $p) {
            $cache[$p->getName()] = $p;
        }

        if (($parent = $class->getParentClass()) instanceof \ReflectionClass) {
            $cache = \array_merge($cache, $this->getReflectionProperties($parent));
        }

        if (\count($this->storage) >= self::MAX_CACHE_SIZE) {
            unset($this->storage[\array_key_first($this->storage)]);
        }

        return $this->storage[$className] = $cache;
    }

    /**
     * @throws \ReflectionException
     */
    public function getReflectionProperty(string|object $class, string $propertyPath): ?\ReflectionProperty
    {
        /** @var class-string $className */
        $className = \is_object($class) ? ClassUtils::getClass($class) : $class;

        return $this->getReflectionProperties($className)[$propertyPath] ?? null;
    }
}
