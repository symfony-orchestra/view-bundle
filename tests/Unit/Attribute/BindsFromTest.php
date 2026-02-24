<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Attribute;

use ChamberOrchestra\ViewBundle\Attribute\BindsFrom;
use PHPUnit\Framework\TestCase;

final class BindsFromTest extends TestCase
{
    public function testItStoresClassCorrectly(): void
    {
        $attr = new BindsFrom(\stdClass::class);

        self::assertSame(\stdClass::class, $attr->class);
    }

    public function testItRejectsNonExistentClass(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Class "NonExistent\Foo\Bar" does not exist');

        new BindsFrom('NonExistent\\Foo\\Bar');
    }

    public function testItTargetsClassAndIsRepeatable(): void
    {
        $reflection = new \ReflectionClass(BindsFrom::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        self::assertCount(1, $attributes);

        /** @var \Attribute $attr */
        $attr = $attributes[0]->newInstance();

        self::assertNotSame(0, $attr->flags & \Attribute::TARGET_CLASS);
        self::assertNotSame(0, $attr->flags & \Attribute::IS_REPEATABLE);
    }

    public function testItWorksAsRepeatableAttributeOnClass(): void
    {
        $reflection = new \ReflectionClass(BindsFromFixtureView::class);
        $attributes = $reflection->getAttributes(BindsFrom::class);

        self::assertCount(2, $attributes);
        self::assertSame(BindsFromFixtureEntityA::class, $attributes[0]->newInstance()->class);
        self::assertSame(BindsFromFixtureEntityB::class, $attributes[1]->newInstance()->class);
    }
}

class BindsFromFixtureEntityA
{
}

class BindsFromFixtureEntityB
{
}

#[BindsFrom(BindsFromFixtureEntityA::class)]
#[BindsFrom(BindsFromFixtureEntityB::class)]
class BindsFromFixtureView
{
}
