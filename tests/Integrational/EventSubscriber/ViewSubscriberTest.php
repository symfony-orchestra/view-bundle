<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Integrational\EventSubscriber;

use ChamberOrchestra\ViewBundle\EventSubscriber\ViewSubscriber;
use ChamberOrchestra\ViewBundle\View\CacheableViewInterface;
use ChamberOrchestra\ViewBundle\View\CachedBindView;
use ChamberOrchestra\ViewBundle\View\CachedView;
use ChamberOrchestra\ViewBundle\View\SourceCacheSignatureInterface;
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

    public function testCachedViewIsServedFromCacheOnSecondRequest(): void
    {
        static::bootKernel();
        $container = static::getContainer();

        $subscriber = $container->get(ViewSubscriber::class);
        $version = \bin2hex(\random_bytes(8));

        CachedViewItemView::$constructed = 0;

        $firstEvent = new ViewEvent(
            $container->get('kernel'),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            new CachedView(new CachedBindTestEntity($version), CachedViewItemView::class)
        );
        $subscriber($firstEvent);

        self::assertSame('{"data":{"name":"Alice"}}', $firstEvent->getResponse()?->getContent());
        self::assertSame(1, CachedViewItemView::$constructed);

        $secondEvent = new ViewEvent(
            $container->get('kernel'),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            new CachedView(new CachedBindTestEntity($version), CachedViewItemView::class)
        );
        $subscriber($secondEvent);

        self::assertSame('{"data":{"name":"Alice"}}', $secondEvent->getResponse()?->getContent());
        self::assertSame(1, CachedViewItemView::$constructed, 'The view must not be rebuilt when the payload is cached');
    }

    public function testCacheableViewIsServedFromCacheOnSecondRequest(): void
    {
        static::bootKernel();
        $container = static::getContainer();

        $subscriber = $container->get(ViewSubscriber::class);
        $signature = 'integration_cacheable_'.\bin2hex(\random_bytes(8));

        $firstEvent = new ViewEvent(
            $container->get('kernel'),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            new CacheableTestView('bar', $signature)
        );
        $subscriber($firstEvent);

        self::assertSame('{"data":{"foo":"bar"}}', $firstEvent->getResponse()?->getContent());

        // Same signature with different data: the cached payload must win
        $secondEvent = new ViewEvent(
            $container->get('kernel'),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            new CacheableTestView('changed', $signature)
        );
        $subscriber($secondEvent);

        self::assertSame('{"data":{"foo":"bar"}}', $secondEvent->getResponse()?->getContent());
    }

    public function testCachedBindViewIsServedFromCacheWithoutBinding(): void
    {
        static::bootKernel();
        $container = static::getContainer();

        $subscriber = $container->get(ViewSubscriber::class);
        $version = \bin2hex(\random_bytes(8));

        $firstSource = new CachedBindTestEntity($version);
        $firstEvent = new ViewEvent(
            $container->get('kernel'),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            new CachedBindTestView($firstSource)
        );
        $subscriber($firstEvent);

        self::assertSame('{"data":{"name":"Alice"}}', $firstEvent->getResponse()?->getContent());
        self::assertSame(1, $firstSource->getterCalls);

        $secondSource = new CachedBindTestEntity($version);
        $secondEvent = new ViewEvent(
            $container->get('kernel'),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            new CachedBindTestView($secondSource)
        );
        $subscriber($secondEvent);

        self::assertSame('{"data":{"name":"Alice"}}', $secondEvent->getResponse()?->getContent());
        self::assertSame(0, $secondSource->getterCalls, 'A cache hit must not bind the view');
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

final class CacheableTestView extends View implements CacheableViewInterface
{
    public function __construct(
        public string $foo,
        private readonly string $signature,
    ) {
    }

    public function getCacheSignature(): string
    {
        return $this->signature;
    }
}

final class CachedBindTestEntity
{
    public int $getterCalls = 0;

    private string $name = 'Alice';

    public function __construct(public string $version = '')
    {
    }

    public function getName(): string
    {
        ++$this->getterCalls;

        return $this->name;
    }
}

final class CachedBindTestView extends CachedBindView
{
    public ?string $name = null;

    public static function createCacheSignature(object $source): string
    {
        \assert($source instanceof CachedBindTestEntity);

        return 'integration_cached_bind_'.$source->version;
    }
}

final class CachedViewItemView extends View implements SourceCacheSignatureInterface
{
    public static int $constructed = 0;

    public string $name;

    public function __construct(object $source)
    {
        \assert($source instanceof CachedBindTestEntity);
        ++self::$constructed;
        $this->name = $source->getName();
    }

    public static function createCacheSignature(object $source): string
    {
        \assert($source instanceof CachedBindTestEntity);

        return 'integration_cached_view_'.$source->version;
    }
}
