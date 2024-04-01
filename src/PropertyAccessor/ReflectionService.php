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
    /** @var array<string, \ReflectionProperty[]> */
    private static array $propertiesCache = [];
    /** @var array<string, \ReflectionClass> */
    private static array $classCache = [];

    /**
     * @return array<string, \ReflectionProperty>
     * @throws \ReflectionException
     */
    public function getReflectionProperties(object|string $class): array
    {
        $className = $class instanceof \ReflectionClass ? $class->getName() : (\is_object($class) ? ClassUtils::getClass($class) : $class);
        if (isset(static::$propertiesCache[$className])) {
            return static::$propertiesCache[$className];
        }

        $cache = $this->getClassProperties($class = $this->getClass($class));
        while (($class = $class->getParentClass()) instanceof \ReflectionClass) {
            $cache = $cache + $this->getReflectionProperties($class);
        }

        return static::$propertiesCache[$className] = $cache;
    }

    /**
     * @throws \ReflectionException
     */
    public function getReflectionProperty(string|object $class, string $propertyPath): \ReflectionProperty|null
    {
        return $this->getReflectionProperties(\is_object($class) ? ClassUtils::getClass($class) : $class)[$propertyPath] ?? null;
    }

    /**
     * @return array<string, \ReflectionProperty>
     */
    private function getClassProperties(\ReflectionClass $class): array
    {
        $properties = [];
        foreach ($class->getProperties() as $p) {
            $properties[$p->getName()] = $p;
        }
        return $properties;
    }

    private function getClass(object|string $class): \ReflectionClass
    {
        if ($class instanceof \ReflectionClass) {
            return $class;
        }
        return self::$classCache[\is_object($class) ? \get_class($class) : $class] ??= new \ReflectionClass($class);
    }
}
