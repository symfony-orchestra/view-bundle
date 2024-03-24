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
    public function testGetReflectionProperties(): void
    {
        $service = new ReflectionService();
        $this->assertValid(['a', 'a2', 'a3'], $service->getReflectionProperties(A::class));
        $this->assertValid(['b', 'b2', 'b3', 'a', 'a2', 'a3'], $service->getReflectionProperties(B::class));
        $this->assertValid(['c', 'c2', 'c3', 'b', 'b2', 'b3', 'a', 'a2', 'a3'], $service->getReflectionProperties(C::class));
        $this->assertValid(['c', 'c2', 'c3', 'b', 'b2', 'b3', 'a', 'a2', 'a3', 'd', 'd2', 'd3'], $service->getReflectionProperties(D::class));

        $this->assertValid(['a', 'a2', 'a3'], $service->getReflectionProperties(new \ReflectionClass(A::class)));
        $this->assertValid(['b', 'b2', 'b3', 'a', 'a2', 'a3'], $service->getReflectionProperties(new \ReflectionClass(B::class)));
        $this->assertValid(['c', 'c2', 'c3', 'b', 'b2', 'b3', 'a', 'a2', 'a3'], $service->getReflectionProperties(new \ReflectionClass(C::class)));
        $this->assertValid(['c', 'c2', 'c3', 'b', 'b2', 'b3', 'a', 'a2', 'a3', 'd', 'd2', 'd3'], $service->getReflectionProperties(new \ReflectionClass(D::class)));
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
}

class A
{
    public bool $a;
    protected bool $a2;
    private bool $a3;
}

class B extends A
{
    protected bool $a2;
    public bool $b;
    protected bool $b2;
    private bool $b3;
}

class C extends B
{
    public bool $b;
    public bool $c;
    protected bool $c2;
    private bool $c3;
}

class D extends C
{
    use DTrait;
}

trait DTrait
{
    protected bool $b2;
    public bool $d;
    protected bool $d2;
    private bool $d3;
    private bool $c3;
}