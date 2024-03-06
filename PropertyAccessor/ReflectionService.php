<?php

declare(strict_types=1);

/*
 * This file is part of the SymfonyOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SymfonyOrchestra\ViewBundle\PropertyAccessor;

use Doctrine\Common\Util\ClassUtils;

class ReflectionService
{
    private static array $storage = [];

    /**
     * @throws \ReflectionException
     */
    public function getReflectionProperties(\ReflectionClass|string $class): array
    {
        $className = $class instanceof \ReflectionClass ? $class->getName() : $class;
        if (isset(static::$storage[$className])) {
            return static::$storage[$className];
        }

        $cache = [];
        $class = $class instanceof \ReflectionClass ? $class : new \ReflectionClass($class);
        foreach ($class->getProperties() as $p) {
            $cache[$p->getName()] = $p;
        }

        if (($parent = $class->getParentClass()) instanceof \ReflectionClass) {
            $cache = \array_merge($cache, $this->getReflectionProperties($parent));
        }

        return static::$storage[$className] = $cache;
    }

    /**
     * @throws \ReflectionException
     */
    public function getReflectionProperty(string|object $class, string $propertyPath): \ReflectionProperty|null
    {
        return $this->getReflectionProperties(\is_object($class) ? ClassUtils::getClass($class) : $class)[$propertyPath] ?? null;
    }
}
