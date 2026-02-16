<?php

declare(strict_types=1);

namespace Tests\Unit\View;

use ChamberOrchestra\ViewBundle\View\ResponseView;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class ResponseViewTest extends TestCase
{
    public function testDefaults(): void
    {
        $view = new ResponseView();

        self::assertSame(Response::HTTP_OK, $view->getStatus());
        self::assertSame(['Content-Type' => 'application/json'], $view->getHeaders());
    }

    public function testNormalizeReturnsEmptyPayload(): void
    {
        $view = new ResponseView();

        $normalizer = $this->createMock(NormalizerInterface::class);
        $normalizer
            ->expects(self::once())
            ->method('normalize')
            ->with([], 'json', ['ctx' => true])
            ->willReturn([]);

        $result = $view->normalize($normalizer, 'json', ['ctx' => true]);

        self::assertSame([], $result);
    }
}
