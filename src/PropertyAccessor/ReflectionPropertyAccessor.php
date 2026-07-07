<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ViewBundle\PropertyAccessor;

use Doctrine\Persistence\Proxy;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyAccess\PropertyPathInterface;

readonly class ReflectionPropertyAccessor implements PropertyAccessorInterface
{
    public function __construct(
        private PropertyAccessorInterface $decorated,
        private ReflectionService $reflectionService,
    ) {
    }

    /**
     * @param object|array<mixed> $objectOrArray
     *
     * @param-out object|array<mixed> $objectOrArray
     */
    public function setValue(object|array &$objectOrArray, string|PropertyPathInterface $propertyPath, mixed $value): void
    {
        if ($objectOrArray instanceof Proxy && !$objectOrArray->__isInitialized()) {
            $objectOrArray->__load();
        }

        $this->decorated->setValue($objectOrArray, $propertyPath, $value);
    }

    /**
     * @param object|array<mixed> $objectOrArray
     *
     * @throws \ReflectionException
     */
    public function getValue(object|array $objectOrArray, string|PropertyPathInterface $propertyPath): mixed
    {
        if ($objectOrArray instanceof Proxy && !$objectOrArray->__isInitialized()) {
            $objectOrArray->__load();
        }

        try {
            return $this->decorated->getValue($objectOrArray, $propertyPath);
        } catch (\Throwable $e) {
            // PHP throws a raw \Error (not an Exception) when reading an inaccessible property directly

            if (!$this->isIntercepted($e, $objectOrArray, $propertyPath)) {
                throw $e;
            }
            if (null === $property = $this->getReflectionProperty($objectOrArray, (string) $propertyPath)) {
                throw $e;
            }

            \assert(\is_object($objectOrArray));

            return $property->getValue($objectOrArray);
        }
    }

    /**
     * @param object|iterable<mixed> $objectOrArray
     *
     * @throws \ReflectionException
     */
    public function isWritable(object|iterable $objectOrArray, string|PropertyPathInterface $propertyPath): bool
    {
        return $this->decorated->isWritable($objectOrArray, $propertyPath) || $this->propertyExists($objectOrArray, $propertyPath);
    }

    /**
     * @param object|iterable<mixed> $objectOrArray
     *
     * @throws \ReflectionException
     */
    public function isReadable(object|iterable $objectOrArray, string|PropertyPathInterface $propertyPath): bool
    {
        return $this->decorated->isReadable($objectOrArray, $propertyPath) || $this->propertyExists($objectOrArray, $propertyPath);
    }

    /**
     * Is the property accessible as public of getter method.
     *
     * @param object|iterable<mixed> $objectOrArray
     */
    public function isStrictlyReadable(object|iterable $objectOrArray, string|PropertyPathInterface $propertyPath): bool
    {
        return $this->decorated->isReadable($objectOrArray, $propertyPath);
    }

    /**
     * @param object|iterable<mixed> $objectOrArray
     *
     * @throws \ReflectionException
     */
    private function propertyExists(object|iterable $objectOrArray, string|PropertyPathInterface $propertyPath): bool
    {
        return null !== $this->getReflectionProperty($objectOrArray, (string) $propertyPath);
    }

    /**
     * @param object|iterable<mixed> $objectOrArray
     *
     * @throws \ReflectionException
     */
    private function getReflectionProperty(object|iterable $objectOrArray, string $propertyPath): ?\ReflectionProperty
    {
        if (false === \is_object($objectOrArray)) {
            return null;
        }

        return $this->reflectionService->getReflectionProperty($objectOrArray, $propertyPath);
    }

    /**
     * @param object|array<mixed> $objectOrArray
     */
    private function isIntercepted(\Throwable $e, object|array $objectOrArray, string|PropertyPathInterface $propertyPath): bool
    {
        if ($e instanceof NoSuchPropertyException) {
            return true;
        }
        $objectType = \get_debug_type($objectOrArray);

        $interceptablePatterns = [
            '/^Cannot access (private|protected) property '.\preg_quote($objectType, '/').'::\$'.$propertyPath.'$/',
            '/^Can\'t get a way to read the property "'.$propertyPath.'" in class '.\preg_quote($objectType, '/').'$/',
        ];

        return \array_any($interceptablePatterns, static fn (string $pattern): bool => (bool) \preg_match($pattern, $e->getMessage()));
    }
}
