<?php

declare(strict_types=1);

namespace Tests\Integrational\EventSubscriber;

use ChamberOrchestra\ViewBundle\EventSubscriber\ViewSubscriber;
use ChamberOrchestra\ViewBundle\View\View;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Serializer\SerializerInterface;

final class ViewSubscriberTest extends KernelTestCase
{
    public function testHandlesViewAndSetsJsonResponse(): void
    {
        static::bootKernel();
        $container = static::getContainer();

        $subscriber = $container->get(ViewSubscriber::class);
        $serializer = $container->get(SerializerInterface::class);

        $view = new class extends View {
            public string $foo = 'bar';
        };

        $event = new ViewEvent(
            $container->get('kernel'),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            $view
        );

        $subscriber($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->headers->get('Content-Type'));
        self::assertSame('{"data":{"foo":"bar"}}', $response->getContent());
    }

    public function testIgnoresNonViewResults(): void
    {
        static::bootKernel();
        $container = static::getContainer();

        $subscriber = $container->get(ViewSubscriber::class);

        $event = new ViewEvent(
            $container->get('kernel'),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            ['not-a-view']
        );

        $subscriber($event);

        self::assertNull($event->getResponse());
    }
}
