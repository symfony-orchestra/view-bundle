<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\View;

use ChamberOrchestra\ViewBundle\View\CachedView;
use ChamberOrchestra\ViewBundle\View\SourceCacheSignatureInterface;
use ChamberOrchestra\ViewBundle\View\View;
use ChamberOrchestra\ViewBundle\View\ViewInterface;
use PHPUnit\Framework\TestCase;

final class CachedViewTest extends TestCase
{
    public function testSignatureIsComputedFromTheSourceWithoutBuildingTheView(): void
    {
        $source = new CachedViewSourceEntity(7, 'Alice');
        $view = new CachedView($source, SignedUserView::class);

        self::assertSame('user_7', $view->getCacheSignature());
        self::assertSame(0, SignedUserView::$constructed, 'The view must not be built to compute the signature');
    }

    public function testDefaultFactoryBuildsTheViewClassFromTheSource(): void
    {
        $source = new CachedViewSourceEntity(7, 'Alice');
        $view = new CachedView($source, SignedUserView::class);

        $created = $view->createView();

        self::assertInstanceOf(SignedUserView::class, $created);
        self::assertSame($source, $created->source);
    }

    public function testExplicitFactoryOverridesTheDefault(): void
    {
        $inner = new class extends View {
        };

        $view = new CachedView(
            new CachedViewSourceEntity(7, 'Alice'),
            SignedUserView::class,
            static fn (): ViewInterface => $inner,
        );

        self::assertSame($inner, $view->createView());
    }

    public function testTtlDefaultsToNull(): void
    {
        $view = new CachedView(new CachedViewSourceEntity(7, 'Alice'), SignedUserView::class);

        self::assertNull($view->getTtl());

        $view = new CachedView(new CachedViewSourceEntity(7, 'Alice'), SignedUserView::class, ttl: 300);

        self::assertSame(300, $view->getTtl());
    }

    public function testRejectsViewClassWithoutSourceSignature(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must implement');

        new CachedView(new CachedViewSourceEntity(7, 'Alice'), PlainSignatureLessView::class);
    }

    protected function setUp(): void
    {
        SignedUserView::$constructed = 0;
    }
}

final class CachedViewSourceEntity
{
    public function __construct(
        public int $id,
        public string $name,
    ) {
    }
}

final class SignedUserView extends View implements SourceCacheSignatureInterface
{
    public static int $constructed = 0;

    public function __construct(public object $source)
    {
        ++self::$constructed;
    }

    public static function createCacheSignature(object $source): string
    {
        \assert($source instanceof CachedViewSourceEntity);

        return 'user_'.$source->id;
    }
}

final class PlainSignatureLessView extends View
{
}
