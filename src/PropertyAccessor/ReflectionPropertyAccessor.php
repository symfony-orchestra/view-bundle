<?php

declare(strict_types=1);

/*
 * This file is part of the SymfonyOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SymfonyOrchestra\ViewBundle\PropertyAccessor;

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

    public function setValue(object|array &$objectOrArray, string|PropertyPathInterface $propertyPath, mixed $value): void
    {
        if ($objectOrArray instanceof Proxy && !$objectOrArray->__isInitialized()) {
            $objectOrArray->__load();
        }

        // only public properties of view are supported
        $this->decorated->setValue($objectOrArray, $propertyPath, $value);
    }

    /**
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
            if (!$this->isIntercepted($e, $objectOrArray, $propertyPath)) {
                throw $e;
            }
            if (null === $property = $this->getReflectionProperty($objectOrArray, $propertyPath)) {
                throw $e;
            }
            return $property->getValue($objectOrArray);
        }
    }

    /**
     * @throws \ReflectionException
     */
    public function isWritable(object|iterable $objectOrArray, string|PropertyPathInterface $propertyPath): bool
    {
        return $this->decorated->isWritable($objectOrArray, $propertyPath) || $this->propertyExists($objectOrArray, $propertyPath);
    }

    /**
     * @throws \ReflectionException
     */
    public function isReadable(object|iterable $objectOrArray, string|PropertyPathInterface $propertyPath): bool
    {
        return $this->decorated->isReadable($objectOrArray, $propertyPath) || $this->propertyExists($objectOrArray, $propertyPath);
    }

    /**
     * Is the property accessible as public of getter method
     */
    public function isStrictlyReadable(object|iterable $objectOrArray, string|PropertyPathInterface $propertyPath): bool
    {
        return $this->decorated->isReadable($objectOrArray, $propertyPath);
    }

    /**
     * @throws \ReflectionException
     */
    private function propertyExists(object|iterable $objectOrArray, string|PropertyPathInterface $propertyPath): bool
    {
        return null !== $this->getReflectionProperty($objectOrArray, (string)$propertyPath);
    }

    /**
     * @throws \ReflectionException
     */
    private function getReflectionProperty(object|iterable $objectOrArray, string $propertyPath): ?\ReflectionProperty
    {
        if (false === \is_object($objectOrArray)) {
            return null;
        }

        return $this->reflectionService->getReflectionProperty($objectOrArray, $propertyPath);
    }

    private function isIntercepted(\Throwable $e, object|array $objectOrArray, string|PropertyPathInterface $propertyPath): bool
    {
        if ($e instanceof NoSuchPropertyException) {
            return true;
        }
        $supported = [
            '/^Cannot access (private|protected) property '.\preg_quote(\get_debug_type($objectOrArray), '/').'::\$'.$propertyPath.'$/',
            '/^Can\'t get a way to read the property "'.$propertyPath.'" in class '.\preg_quote(\get_debug_type($objectOrArray), '/').'$/',
        ];

        return \array_any($supported, fn($pattern) => \preg_match($pattern, $e->getMessage()));
    }
}
