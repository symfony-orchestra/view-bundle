<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\EventSubscriber;

use ChamberOrchestra\ViewBundle\EventSubscriber\ViewSubscriber;
use ChamberOrchestra\ViewBundle\View\DataView;
use ChamberOrchestra\ViewBundle\View\ResponseView;
use ChamberOrchestra\ViewBundle\View\View;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Serializer\SerializerInterface;

final class ViewSubscriberTest extends TestCase
{
    public function testItIgnoresNonViewResults(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::never())->method('serialize');

        $event = new ViewEvent(
            $this->createKernel(),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            ['not-a-view']
        );

        $subscriber = new ViewSubscriber($serializer);
        $subscriber($event);

        self::assertNull($event->getResponse());
    }

    public function testItWrapsNonResponseViewAndSetsJsonResponse(): void
    {
        $view = new class extends View {
        };

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer
            ->expects(self::once())
            ->method('serialize')
            ->with(
                self::callback(fn ($value) => $value instanceof DataView && $value->data === $view),
                'json',
                ['json_encode_options' => \JSON_HEX_TAG | \JSON_HEX_APOS | \JSON_HEX_AMP | \JSON_HEX_QUOT]
            )
            ->willReturn('{"data":{"foo":"bar"}}');

        $event = new ViewEvent(
            $this->createKernel(),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            $view
        );

        $subscriber = new ViewSubscriber($serializer);
        $subscriber($event);

        $response = $event->getResponse();

        self::assertNotNull($response);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->headers->get('Content-Type'));
        self::assertSame('{"data":{"foo":"bar"}}', $response->getContent());
    }

    public function testItUsesResponseViewStatusAndHeaders(): void
    {
        $view = new class extends ResponseView {
            public function getStatus(): int
            {
                return 201;
            }

            public function getHeaders(): array
            {
                return ['X-Test' => 'yes', 'Content-Type' => 'application/json'];
            }
        };

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer
            ->expects(self::once())
            ->method('serialize')
            ->with($view, 'json', ['json_encode_options' => \JSON_HEX_TAG | \JSON_HEX_APOS | \JSON_HEX_AMP | \JSON_HEX_QUOT])
            ->willReturn('{"ok":true}');

        $event = new ViewEvent(
            $this->createKernel(),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            $view
        );

        $subscriber = new ViewSubscriber($serializer);
        $subscriber($event);

        $response = $event->getResponse();

        self::assertNotNull($response);
        self::assertSame(201, $response->getStatusCode());
        self::assertSame('yes', $response->headers->get('X-Test'));
        self::assertSame('application/json', $response->headers->get('Content-Type'));
        self::assertSame('{"ok":true}', $response->getContent());
    }

    private function createKernel(): HttpKernelInterface
    {
        return new class implements HttpKernelInterface {
            public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
            {
                return new Response();
            }
        };
    }
}
