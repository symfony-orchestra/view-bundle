<?php
declare(strict_types=1);

/*
 * This file is part of the SymfonyOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\PropertyAccessor;

use Doctrine\Persistence\Proxy;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use SymfonyOrchestra\ViewBundle\PropertyAccessor\ReflectionPropertyAccessor;
use SymfonyOrchestra\ViewBundle\PropertyAccessor\ReflectionService;

class ReflectionPropertyAccessorTest extends TestCase
{
    /**
     * @dataProvider getTestSetValueData
     */
    public function testSetValue(object|array $objectOrArray, string $path, mixed $value): void
    {
        $accessor = $this->createMock(PropertyAccessorInterface::class);
        $accessor->expects(self::once())->method('setValue')->with($objectOrArray, $path, $value);

        $service = new ReflectionPropertyAccessor($accessor, new ReflectionService());
        $service->setValue($objectOrArray, $path, $value);
    }

    public function getTestSetValueData(): array
    {
        $proxy = function (bool $initialized): Proxy {
            $proxy = $this->createMock(Proxy::class);
            $proxy->expects(self::once())->method('__isInitialized')->willReturn($initialized);
            $proxy->expects($initialized ? self::never() : self::once())->method('__load');
            return $proxy;
        };

        return [
            'object' => [
                new \stdClass(),
                'path',
                'value',
            ],
            'array' => [
                [],
                'path',
                'value',
            ],
            'proxy_initialized' => [
                $proxy(true),
                'path',
                'value',
            ],
            'proxy_uninitialized' => [
                $proxy(false),
                'path',
                'value',
            ],
        ];
    }

    /**
     * @dataProvider getTestGetValueData
     */
    public function testGetValue(object|array $objectOrArray, string $path): void
    {
        $accessor = $this->createMock(PropertyAccessorInterface::class);
        $accessor->expects(self::once())->method('getValue')->with($objectOrArray, $path);

        $service = new ReflectionPropertyAccessor($accessor, new ReflectionService());
        $service->getValue($objectOrArray, $path);
    }

    public function getTestGetValueData(): array
    {
        $proxy = function (bool $initialized): Proxy {
            $proxy = $this->createMock(Proxy::class);
            $proxy->expects(self::once())->method('__isInitialized')->willReturn($initialized);
            $proxy->expects($initialized ? self::never() : self::once())->method('__load');
            return $proxy;
        };

        return [
            'object' => [
                new \stdClass(),
                'path',
            ],
            'array' => [
                [],
                'path',
            ],
            'proxy_initialized' => [
                $proxy(true),
                'path',
            ],
            'proxy_uninitialized' => [
                $proxy(false),
                'path',
            ],
        ];
    }

    /**
     * @dataProvider getTestGetValueDecoratedData
     */
    public function testGetValueDecorated(object $class, string $path, \Throwable $exception): void
    {
        $accessor = $this->createMock(PropertyAccessorInterface::class);
        $accessor->expects(self::once())->method('getValue')->willThrowException($exception);

        $property = $this->createMock(\ReflectionProperty::class);
        $property->expects(self::once())->method('getValue')->with($class)->willReturn($result = 'result');

        $reflectionService = $this->createMock(ReflectionService::class);
        $reflectionService->expects(self::once())->method('getReflectionProperty')->willReturn($property);

        $this->assertEquals($result, (new ReflectionPropertyAccessor($accessor, $reflectionService))->getValue($class, $path));
    }

    public function getTestGetValueDecoratedData(): array
    {
        return [
            [
                new \stdClass(),
                'path',
                new NoSuchPropertyException('Cannot access private property stdClass::$path'),
            ],
            [
                new \stdClass(),
                'path2',
                new NoSuchPropertyException('Cannot access protected property stdClass::$path2'),
            ],
        ];
    }

    /**
     * @dataProvider getTestGetValueExceptionData
     */
    public function testGetValueException(\Exception $exception): void
    {
        $class = new \stdClass();
        $path = 'path';

        $accessor = $this->createMock(PropertyAccessorInterface::class);
        $accessor->expects(self::once())->method('getValue')->willThrowException($exception);

        $property = $this->createMock(\ReflectionProperty::class);
        $property->expects(self::never())->method('getValue');

        $reflectionService = $this->createMock(ReflectionService::class);
        $reflectionService->expects(self::never())->method('getReflectionProperty');

        $this->expectExceptionObject($exception);
        (new ReflectionPropertyAccessor($accessor, $reflectionService))->getValue($class, $path);
    }

    public function getTestGetValueExceptionData(): array
    {
        return [
            [new NoSuchPropertyException('Incorrect message')],
            [new \Exception('Incorrect exception')],
        ];
    }

    public function testGetValueExceptionInService(): void
    {
        $class = new \stdClass();
        $path = 'path';

        $accessor = $this->createMock(PropertyAccessorInterface::class);
        $accessor->expects(self::once())->method('getValue')->willThrowException($e = new NoSuchPropertyException('Cannot access private property stdClass::$path'));

        $property = $this->createMock(\ReflectionProperty::class);
        $property->expects(self::never())->method('getValue');

        $reflectionService = $this->createMock(ReflectionService::class);
        $reflectionService->expects(self::once())->method('getReflectionProperty')->with($class, $path)->willReturn(null);

        $this->expectExceptionObject($e);
        (new ReflectionPropertyAccessor($accessor, $reflectionService))->getValue($class, $path);
    }

    /**
     * @dataProvider getTestIsWritableData
     */
    public function testIsWritable(bool $isWritable, bool $hasProperty, bool $expected): void
    {
        $class = new \stdClass();
        $path = 'path';

        $accessor = $this->createMock(PropertyAccessorInterface::class);
        $accessor->expects(self::once())->method('isWritable')->with($class, $path)->willReturn($isWritable);

        $property = $this->createMock(\ReflectionProperty::class);

        $reflectionService = $this->createMock(ReflectionService::class);
        $reflectionService
            ->expects($isWritable ? self::never() : self::once())
            ->method('getReflectionProperty')
            ->willReturn($hasProperty ? $property : null);

        $actual = (new ReflectionPropertyAccessor($accessor, $reflectionService))->isWritable($class, $path);
        $this->assertEquals($expected, $actual);
    }

    public function getTestIsWritableData(): array
    {
        return [
            [
                true,
                false,
                true,
            ],
            [
                false,
                false,
                false,
            ],
            [
                false,
                true,
                true,
            ],
        ];
    }

    /**
     * @dataProvider getTestIsReadableData
     */
    public function testIsReadable(bool $isReadable, bool $hasProperty, bool $expected): void
    {
        $class = new \stdClass();
        $path = 'path';

        $accessor = $this->createMock(PropertyAccessorInterface::class);
        $accessor->expects(self::once())->method('isReadable')->with($class, $path)->willReturn($isReadable);

        $property = $this->createMock(\ReflectionProperty::class);

        $reflectionService = $this->createMock(ReflectionService::class);
        $reflectionService
            ->expects($isReadable ? self::never() : self::once())
            ->method('getReflectionProperty')
            ->willReturn($hasProperty ? $property : null);

        $actual = (new ReflectionPropertyAccessor($accessor, $reflectionService))->isReadable($class, $path);
        $this->assertEquals($expected, $actual);
    }

    public function getTestIsReadableData(): array
    {
        return [
            [
                true,
                false,
                true,
            ],
            [
                false,
                false,
                false,
            ],
            [
                false,
                true,
                true,
            ],
        ];
    }

    /**
     * @dataProvider getTestIsStrictlyReadableData
     */
    public function testIsStrictlyReadable(bool $isReadable, bool $expected): void
    {
        $class = new \stdClass();
        $path = 'path';

        $accessor = $this->createMock(PropertyAccessorInterface::class);
        $accessor->expects(self::once())->method('isReadable')->with($class, $path)->willReturn($isReadable);

        $reflectionService = $this->createMock(ReflectionService::class);
        $reflectionService->expects(self::never())->method('getReflectionProperty');

        $actual = (new ReflectionPropertyAccessor($accessor, $reflectionService))->isStrictlyReadable($class, $path);
        $this->assertEquals($expected, $actual);
    }

    public function getTestIsStrictlyReadableData(): array
    {
        return [
            [
                true,
                true,
            ],
            [
                false,
                false,
            ],
        ];
    }
}