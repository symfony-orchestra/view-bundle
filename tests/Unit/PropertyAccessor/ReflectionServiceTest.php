<?php
declare(strict_types=1);

/*
 * This file is part of the SymfonyOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\PropertyAccessor;

use PHPUnit\Framework\TestCase;
use SymfonyOrchestra\ViewBundle\PropertyAccessor\ReflectionService;

class ReflectionServiceTest extends TestCase
{
    /**
     * @dataProvider getTestGetReflectionPropertiesData
     */
    public function testGetReflectionProperties(array $expected, object|string $class): void
    {
        $service = new ReflectionService();
        $this->assertValid($expected, $service->getReflectionProperties($class));
    }

    public function getTestGetReflectionPropertiesData(): array
    {
        return [
            [['aPublic', 'aProtected', 'aPrivate'], A::class],
            [['bPublic', 'bProtected', 'bPrivate', 'aPublic', 'aProtected', 'aPrivate'], B::class],
            [['cPublic', 'cProtected', 'cPrivate', 'bPublic', 'bProtected', 'bPrivate', 'aPublic', 'aProtected', 'aPrivate'], C::class],
            [['cPublic', 'cProtected', 'cPrivate', 'bPublic', 'bProtected', 'bPrivate', 'aPublic', 'aProtected', 'aPrivate', 'dPublic', 'dProtected', 'd3Private'], D::class],
            [['aPublic', 'aProtected', 'aPrivate'], new \ReflectionClass(A::class)],
            [['bPublic', 'bProtected', 'bPrivate', 'aPublic', 'aProtected', 'aPrivate'], new \ReflectionClass(B::class)],
            [['cPublic', 'cProtected', 'cPrivate', 'bPublic', 'bProtected', 'bPrivate', 'aPublic', 'aProtected', 'aPrivate'], new \ReflectionClass(C::class)],
            [['cPublic', 'cProtected', 'cPrivate', 'bPublic', 'bProtected', 'bPrivate', 'aPublic', 'aProtected', 'aPrivate', 'dPublic', 'dProtected', 'd3Private'], new \ReflectionClass(D::class)],
        ];
    }

    private function assertValid(array $expectedKeys, array $properties): void
    {
        \sort($expectedKeys);
        $actual = \array_keys($properties);
        \sort($actual);
        $this->assertEquals($expectedKeys, $actual);
        foreach ($properties as $property) {
            $this->assertInstanceOf(\ReflectionProperty::class, $property);
        }
    }

    /**
     * @dataProvider getTestGetReflectionPropertyData
     */
    public function testGetReflectionProperty(string|object $class, string $property, \ReflectionProperty $expected): void
    {
        $service = new ReflectionService();
        $property = $service->getReflectionProperty($class, $property);

        $this->assertEquals($expected, $property);
    }

    public function getTestGetReflectionPropertyData(): array
    {
        $getProperty = function (string|object $class, string $property) {
            return new \ReflectionProperty($class, $property);
        };

        return [
            [A::class, 'aPublic', $getProperty(A::class, 'aPublic')],
            [A::class, 'aProtected', $getProperty(A::class, 'aProtected')],
            [A::class, 'aPrivate', $getProperty(A::class, 'aPrivate')],
            [B::class, 'bPublic', $getProperty(B::class, 'bPublic')],
            [B::class, 'bProtected', $getProperty(B::class, 'bProtected')],
            [B::class, 'bPrivate', $getProperty(B::class, 'bPrivate')],
            [B::class, 'aPublic', $getProperty(B::class, 'aPublic')],
            [B::class, 'aProtected', $getProperty(B::class, 'aProtected')],
            [B::class, 'aPrivate', $getProperty(A::class, 'aPrivate')],
            [C::class, 'cPublic', $getProperty(C::class, 'cPublic')],
            [C::class, 'cProtected', $getProperty(C::class, 'cProtected')],
            [C::class, 'cPrivate', $getProperty(C::class, 'cPrivate')],
            [C::class, 'aPublic', $getProperty(C::class, 'aPublic')],
            [C::class, 'aProtected', $getProperty(C::class, 'aProtected')],
            [C::class, 'aPrivate', $getProperty(A::class, 'aPrivate')],
            [C::class, 'bPublic', $getProperty(C::class, 'bPublic')],
            [C::class, 'bProtected', $getProperty(C::class, 'bProtected')],
            [C::class, 'bPrivate', $getProperty(B::class, 'bPrivate')],
            [D::class, 'dPublic', $getProperty(D::class, 'dPublic')],
            [D::class, 'dProtected', $getProperty(D::class, 'dProtected')],
            [D::class, 'd3Private', $getProperty(D::class, 'd3Private')],
            [D::class, 'aPublic', $getProperty(D::class, 'aPublic')],
            [D::class, 'aProtected', $getProperty(D::class, 'aProtected')],
            [D::class, 'aPrivate', $getProperty(A::class, 'aPrivate')],
            [D::class, 'bPublic', $getProperty(D::class, 'bPublic')],
            [D::class, 'bProtected', $getProperty(D::class, 'bProtected')],
            [D::class, 'bPrivate', $getProperty(B::class, 'bPrivate')],
            [D::class, 'cPublic', $getProperty(D::class, 'cPublic')],
            [D::class, 'cProtected', $getProperty(D::class, 'cProtected')],
            [D::class, 'cPrivate', $getProperty(D::class, 'cPrivate')],
        ];
    }
}

class A
{
    public bool $aPublic;
    protected bool $aProtected;
    private bool $aPrivate;
}

class B extends A
{
    protected bool $aProtected;
    public bool $bPublic;
    protected bool $bProtected;
    private bool $bPrivate;
}

class C extends B
{
    public bool $bPublic;
    public bool $cPublic;
    protected bool $cProtected;
    private bool $cPrivate;
}

class D extends C
{
    use DTrait;
}

trait DTrait
{
    protected bool $bProtected;
    public bool $dPublic;
    protected bool $dProtected;
    private bool $d3Private;
    private bool $cPrivate;
}