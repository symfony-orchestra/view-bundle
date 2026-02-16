<?php

declare(strict_types=1);

namespace Tests\Unit\PropertyAccessor;

use ChamberOrchestra\ViewBundle\PropertyAccessor\ReflectionService;
use PHPUnit\Framework\TestCase;

final class ReflectionServiceTest extends TestCase
{
    protected function setUp(): void
    {
        $this->resetCache();
    }

    public function testItCachesAndMergesParentProperties(): void
    {
        $service = new ReflectionService();

        $first = $service->getReflectionProperties(ChildSubject::class);

        $this->assertArrayHasKey('childProp', $first);
        $this->assertArrayHasKey('parentProp', $first);
        $this->assertArrayHasKey('sharedProp', $first);

        $this->assertSame(ChildSubject::class, $first['childProp']->getDeclaringClass()->getName());
        $this->assertSame(ParentSubject::class, $first['parentProp']->getDeclaringClass()->getName());
        // For duplicate property names, the last discovered reflection is kept (runtime order is implementation-dependent).
        $this->assertContains(
            $first['sharedProp']->getDeclaringClass()->getName(),
            [ChildSubject::class, ParentSubject::class]
        );

        $second = $service->getReflectionProperties(ChildSubject::class);
        $this->assertSame(\spl_object_id($first['childProp']), \spl_object_id($second['childProp']));
        $this->assertSame(\spl_object_id($first['parentProp']), \spl_object_id($second['parentProp']));
        $this->assertSame(\spl_object_id($first['sharedProp']), \spl_object_id($second['sharedProp']));
    }

    public function testItReturnsSinglePropertyOrNull(): void
    {
        $service = new ReflectionService();
        $child = new ChildSubject();

        $property = $service->getReflectionProperty($child, 'parentProp');
        $this->assertNotNull($property);
        $this->assertSame('parentProp', $property->getName());
        $this->assertSame(ParentSubject::class, $property->getDeclaringClass()->getName());

        $this->assertNull($service->getReflectionProperty($child, 'unknown'));
    }

    public function testItAcceptsReflectionClassInput(): void
    {
        $service = new ReflectionService();

        $properties = $service->getReflectionProperties(new \ReflectionClass(ChildSubject::class));

        $this->assertArrayHasKey('childProp', $properties);
        $this->assertArrayHasKey('parentProp', $properties);
    }

    private function resetCache(): void
    {
        $ref = new \ReflectionProperty(ReflectionService::class, 'storage');
        $ref->setValue(null, []);
    }
}

class ParentSubject
{
    private string $parentProp;
    private string $sharedProp;
}

class ChildSubject extends ParentSubject
{
    private string $childProp;
    private string $sharedProp;
}
