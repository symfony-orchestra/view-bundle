<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\EventSubscriber;

use ChamberOrchestra\ViewBundle\Cache\ViewResponseCache;
use ChamberOrchestra\ViewBundle\EventSubscriber\ViewSubscriber;
use ChamberOrchestra\ViewBundle\Serializer\Metadata\ViewMetadataFactory;
use ChamberOrchestra\ViewBundle\Serializer\Normalizer\ViewNormalizer;
use ChamberOrchestra\ViewBundle\View\AutoCacheSignatureTrait;
use ChamberOrchestra\ViewBundle\View\BindView;
use ChamberOrchestra\ViewBundle\View\CacheableViewInterface;
use ChamberOrchestra\ViewBundle\View\CachedBindView;
use ChamberOrchestra\ViewBundle\View\CachedView;
use ChamberOrchestra\ViewBundle\View\DataView;
use ChamberOrchestra\ViewBundle\View\ResponseView;
use ChamberOrchestra\ViewBundle\View\SourceCacheSignatureInterface;
use ChamberOrchestra\ViewBundle\View\View;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\CustomNormalizer;
use Symfony\Component\Serializer\Serializer;
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
                self::callback(static fn ($value) => $value instanceof DataView && $value->data === $view),
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

    public function testCachedViewMissBuildsAndSerializesTheView(): void
    {
        SubscriberCachedViewUserView::$constructed = 0;

        $subscriber = new ViewSubscriber($this->createRealSerializer(), new ViewResponseCache(new ArrayAdapter()));

        $event = $this->createViewEvent(new CachedView(new SubscriberCachedViewEntity(1, 'Alice'), SubscriberCachedViewUserView::class));
        $subscriber($event);

        $response = $event->getResponse();

        self::assertNotNull($response);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->headers->get('Content-Type'));
        self::assertSame('{"data":{"name":"Alice"}}', $response->getContent());
        self::assertSame(1, SubscriberCachedViewUserView::$constructed);
    }

    public function testCachedViewHitServesJsonWithoutBuildingTheView(): void
    {
        SubscriberCachedViewUserView::$constructed = 0;

        $subscriber = new ViewSubscriber($this->createRealSerializer(), new ViewResponseCache(new ArrayAdapter()));

        $firstEvent = $this->createViewEvent(new CachedView(new SubscriberCachedViewEntity(1, 'Alice'), SubscriberCachedViewUserView::class));
        $subscriber($firstEvent);

        $secondEvent = $this->createViewEvent(new CachedView(new SubscriberCachedViewEntity(1, 'Alice'), SubscriberCachedViewUserView::class));
        $subscriber($secondEvent);

        self::assertSame(1, SubscriberCachedViewUserView::$constructed, 'The view must not be built on a cache hit');
        self::assertSame('{"data":{"name":"Alice"}}', $secondEvent->getResponse()?->getContent());
    }

    private function createRealSerializer(): Serializer
    {
        return new Serializer(
            [new CustomNormalizer(), new ViewNormalizer(new ViewMetadataFactory())],
            [new JsonEncoder()],
        );
    }

    public function testCacheableViewIsCachedAutomatically(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::once())->method('serialize')->willReturn('{"data":{"id":7}}');

        $createView = static fn (): CacheableViewInterface => new class extends View implements CacheableViewInterface {
            public int $id = 7;

            public function getCacheSignature(): string
            {
                return 'user_7_1700000000';
            }
        };

        $subscriber = new ViewSubscriber($serializer, new ViewResponseCache(new ArrayAdapter()));

        $firstEvent = $this->createViewEvent($createView());
        $subscriber($firstEvent);

        $secondEvent = $this->createViewEvent($createView());
        $subscriber($secondEvent);

        self::assertSame('{"data":{"id":7}}', $firstEvent->getResponse()?->getContent());
        self::assertSame('{"data":{"id":7}}', $secondEvent->getResponse()?->getContent(), 'Second response must be served from cache (serializer mocked to run once)');
        self::assertSame(200, $secondEvent->getResponse()->getStatusCode());
        self::assertSame('application/json', $secondEvent->getResponse()->headers->get('Content-Type'));
    }

    public function testAutoSignedViewIsCachedByValuesAndInvalidatedOnChange(): void
    {
        $createView = static fn (string $name): CacheableViewInterface => new class($name) extends View implements CacheableViewInterface {
            use AutoCacheSignatureTrait;

            public function __construct(public string $name)
            {
            }
        };

        $serialized = 0;
        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('serialize')->willReturnCallback(static function () use (&$serialized): string {
            ++$serialized;

            return '{"payload":'.$serialized.'}';
        });

        $subscriber = new ViewSubscriber($serializer, new ViewResponseCache(new ArrayAdapter()));

        $firstEvent = $this->createViewEvent($createView('Alice'));
        $subscriber($firstEvent);

        $repeatEvent = $this->createViewEvent($createView('Alice'));
        $subscriber($repeatEvent);

        self::assertSame(1, $serialized, 'Identical values must be served from cache');
        self::assertSame('{"payload":1}', $repeatEvent->getResponse()?->getContent());

        $changedEvent = $this->createViewEvent($createView('Bob'));
        $subscriber($changedEvent);

        self::assertSame(2, $serialized, 'Changed values must produce a fresh payload');
        self::assertSame('{"payload":2}', $changedEvent->getResponse()?->getContent());
    }

    public function testCachedBindViewIsNotBoundOnCacheHit(): void
    {
        BindView::setBindUtils(null);

        try {
            $serializer = new Serializer(
                [new CustomNormalizer(), new ViewNormalizer(new ViewMetadataFactory())],
                [new JsonEncoder()],
            );

            $subscriber = new ViewSubscriber($serializer, new ViewResponseCache(new ArrayAdapter()));

            $firstSource = new SubscriberCachedBindEntity();
            $firstEvent = $this->createViewEvent(new SubscriberCachedBindUserView($firstSource));
            $subscriber($firstEvent);

            self::assertSame('{"data":{"name":"Alice"}}', $firstEvent->getResponse()?->getContent());
            self::assertSame(1, $firstSource->getterCalls, 'First request must bind the view');

            $secondSource = new SubscriberCachedBindEntity();
            $secondEvent = $this->createViewEvent(new SubscriberCachedBindUserView($secondSource));
            $subscriber($secondEvent);

            self::assertSame('{"data":{"name":"Alice"}}', $secondEvent->getResponse()?->getContent());
            self::assertSame(0, $secondSource->getterCalls, 'A cache hit must not bind the view');
        } finally {
            BindView::setBindUtils(null);
        }
    }

    private function createViewEvent(mixed $controllerResult): ViewEvent
    {
        return new ViewEvent(
            $this->createKernel(),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            $controllerResult
        );
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

final class SubscriberCachedBindEntity
{
    public int $getterCalls = 0;

    private string $name = 'Alice';

    public function getName(): string
    {
        ++$this->getterCalls;

        return $this->name;
    }
}

final class SubscriberCachedBindUserView extends CachedBindView
{
    public ?string $name = null;

    public static function createCacheSignature(object $source): string
    {
        return 'subscriber_cached_bind_user_1';
    }
}

final class SubscriberCachedViewEntity
{
    public function __construct(
        public int $id,
        public string $name,
    ) {
    }
}

final class SubscriberCachedViewUserView extends View implements SourceCacheSignatureInterface
{
    public static int $constructed = 0;

    public string $name;

    public function __construct(object $source)
    {
        \assert($source instanceof SubscriberCachedViewEntity);
        ++self::$constructed;
        $this->name = $source->name;
    }

    public static function createCacheSignature(object $source): string
    {
        \assert($source instanceof SubscriberCachedViewEntity);

        return 'subscriber_cached_view_user_'.$source->id;
    }
}
