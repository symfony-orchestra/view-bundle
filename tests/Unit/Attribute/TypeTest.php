<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Attribute;

use ChamberOrchestra\ViewBundle\Attribute\Type;
use ChamberOrchestra\ViewBundle\View\SourceCacheSignatureInterface;
use ChamberOrchestra\ViewBundle\View\ViewInterface;
use PHPUnit\Framework\TestCase;

final class TypeTest extends TestCase
{
    public function testAttributeIsRegisteredForProperties(): void
    {
        $reflection = new \ReflectionClass(Type::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        $this->assertNotEmpty($attributes);
        $this->assertSame([\Attribute::TARGET_PROPERTY], $attributes[0]->getArguments());
    }

    public function testAttributeStoresTargetClass(): void
    {
        $property = new \ReflectionProperty(DummyView::class, 'images');
        $attributes = $property->getAttributes(Type::class);

        $this->assertCount(1, $attributes);
        $instance = $attributes[0]->newInstance();

        $this->assertSame(DummyImageView::class, $instance->class);
    }

    public function testAttributeRejectsNonExistentClass(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');
        new Type('NonExistentClass');
    }

    public function testAttributeRejectsClassNotImplementingViewInterface(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must implement ViewInterface');
        new Type(\stdClass::class);
    }

    public function testCachedDefaultsToFalse(): void
    {
        $type = new Type(DummyImageView::class);

        $this->assertFalse($type->cached);
    }

    public function testCachedRequiresSourceCacheSignature(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must implement SourceCacheSignatureInterface');
        new Type(DummyImageView::class, cached: true);
    }

    public function testCachedAcceptsSourceSignedView(): void
    {
        $type = new Type(DummySignedImageView::class, cached: true);

        $this->assertTrue($type->cached);
    }
}

final class DummyView
{
    #[Type(DummyImageView::class)]
    public array $images = [];
}

final class DummyImageView implements ViewInterface
{
}

final class DummySignedImageView implements SourceCacheSignatureInterface
{
    public static function createCacheSignature(object $source): string
    {
        return 'dummy';
    }
}
