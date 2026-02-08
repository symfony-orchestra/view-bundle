<?php

declare(strict_types=1);

namespace Tests\Unit\View;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use ChamberOrchestra\ViewBundle\View\IterableView;
use ChamberOrchestra\ViewBundle\View\View;

final class IterableViewTest extends TestCase
{
    public function testItMapsEntriesWithCallable(): void
    {
        $view = new IterableView([1, 2], static fn(int $value) => $value * 10);

        self::assertSame([10, 20], $view->entries);
    }

    public function testItMapsEntriesWithClassString(): void
    {
        $items = [(object)['id' => 1], (object)['id' => 2]];
        $view = new IterableView($items, DummyChildView::class);

        self::assertCount(2, $view->entries);
        self::assertInstanceOf(DummyChildView::class, $view->entries[0]);
        self::assertSame($items[0], $view->entries[0]->source);
    }

    public function testItThrowsWhenNoMapProvided(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('should be defined or mapping closure should be passed');

        new IterableView([new \stdClass()]);
    }

    public function testItThrowsWhenClassStringDoesNotExist(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Mapping class');

        new IterableView([new \stdClass()], 'MissingViewClass');
    }

    public function testItThrowsWhenClassStringDoesNotImplementViewInterface(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('must implement ViewInterface');

        new IterableView([new \stdClass()], \stdClass::class);
    }

    public function testNormalizeDelegatesEntries(): void
    {
        $view = new IterableView([1, 2], static fn(int $v) => $v + 1);

        $normalizer = $this->createMock(NormalizerInterface::class);
        $normalizer
            ->expects(self::once())
            ->method('normalize')
            ->with([2, 3])
            ->willReturn(['ok']);

        $result = $view->normalize($normalizer);

        self::assertSame(['ok'], $result);
    }
}

final class DummyChildView extends View
{
    public function __construct(public object $source)
    {
    }
}
