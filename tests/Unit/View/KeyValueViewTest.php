<?php

declare(strict_types=1);

namespace Tests\Unit\View;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use ChamberOrchestra\ViewBundle\View\KeyValueView;

final class KeyValueViewTest extends TestCase
{
    public function testItReturnsKeyWithViewArray(): void
    {
        $payload = ['page' => 1];
        $view = new KeyValueView('meta', $payload);

        $normalizer = $this->createMock(NormalizerInterface::class);
        $normalizer
            ->expects(self::once())
            ->method('normalize')
            ->with($payload, null, [])
            ->willReturn($payload);

        $result = $view->normalize($normalizer);

        self::assertSame(['meta' => ['page' => 1]], $result);
    }
}
