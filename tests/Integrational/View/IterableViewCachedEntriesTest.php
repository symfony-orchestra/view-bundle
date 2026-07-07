<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Integrational\View;

use ChamberOrchestra\ViewBundle\EventSubscriber\ViewSubscriber;
use ChamberOrchestra\ViewBundle\View\CachedBindView;
use ChamberOrchestra\ViewBundle\View\CachedView;
use ChamberOrchestra\ViewBundle\View\IterableView;
use ChamberOrchestra\ViewBundle\View\ViewInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Drives IterableView results with CachedView / CachedBindView entries through the
 * real container exactly like a controller action: ViewSubscriber, the framework
 * serializer and the cache.app pool.
 */
final class IterableViewCachedEntriesTest extends KernelTestCase
{
    public function testActionWithCachedViewEntriesServesItemsFromThePerItemCache(): void
    {
        $version = \bin2hex(\random_bytes(8));
        $map = static fn (object $e): CachedView => new CachedView($e, ActionCachedItemView::class);

        $alice = new ActionCachedItemEntity(1, 'Alice', $version);
        $bob = new ActionCachedItemEntity(2, 'Bob', $version);

        $response = $this->dispatchAction(new IterableView([$alice, $bob], $map));

        self::assertSame('{"data":[{"id":1,"name":"Alice"},{"id":2,"name":"Bob"}]}', $response->getContent());
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, $alice->getterCalls, 'First request must bind each item once');
        self::assertSame(1, $bob->getterCalls);

        // Second request over fresh entities with unchanged state: items come from the cache
        $alice = new ActionCachedItemEntity(1, 'Alice', $version);
        $bob = new ActionCachedItemEntity(2, 'Bob', $version);

        $response = $this->dispatchAction(new IterableView([$alice, $bob], $map));

        self::assertSame('{"data":[{"id":1,"name":"Alice"},{"id":2,"name":"Bob"}]}', $response->getContent());
        self::assertSame(0, $alice->getterCalls, 'Unchanged items must be served from the per-item cache');
        self::assertSame(0, $bob->getterCalls);
    }

    public function testActionWithCachedViewEntriesRebuildsOnlyTheChangedItem(): void
    {
        $version = \bin2hex(\random_bytes(8));
        $map = static fn (object $e): CachedView => new CachedView($e, ActionCachedItemView::class);

        $this->dispatchAction(new IterableView([
            new ActionCachedItemEntity(1, 'Alice', $version),
            new ActionCachedItemEntity(2, 'Bob', $version),
        ], $map));

        $alice = new ActionCachedItemEntity(1, 'Alice', $version);
        $bob = new ActionCachedItemEntity(2, 'Bob-Renamed', $version.'_v2');

        $response = $this->dispatchAction(new IterableView([$alice, $bob], $map));

        self::assertSame('{"data":[{"id":1,"name":"Alice"},{"id":2,"name":"Bob-Renamed"}]}', $response->getContent());
        self::assertSame(0, $alice->getterCalls, 'The unchanged item must stay cached');
        self::assertSame(1, $bob->getterCalls, 'The changed item must be rebuilt');
    }

    public function testActionWithCachedBindViewEntriesBindsLazilyOnEveryRequest(): void
    {
        $version = \bin2hex(\random_bytes(8));

        $alice = new ActionCachedItemEntity(1, 'Alice', $version);
        $view = new IterableView([$alice], ActionCachedItemView::class);

        self::assertContainsOnlyInstancesOf(ActionCachedItemView::class, $view->entries);
        self::assertSame(0, $alice->getterCalls, 'Returning the view from the action must not bind yet');

        $response = $this->dispatchAction($view);

        self::assertSame('{"data":[{"id":1,"name":"Alice"}]}', $response->getContent());
        self::assertSame(1, $alice->getterCalls, 'Serialization must trigger the deferred binding');

        // Direct CachedBindView entries carry no per-item payload cache: the next request binds again
        $freshAlice = new ActionCachedItemEntity(1, 'Alice', $version);
        $response = $this->dispatchAction(new IterableView([$freshAlice], ActionCachedItemView::class));

        self::assertSame('{"data":[{"id":1,"name":"Alice"}]}', $response->getContent());
        self::assertSame(1, $freshAlice->getterCalls);
    }

    public function testActionWithMixedCachedViewAndCachedBindViewEntries(): void
    {
        $version = \bin2hex(\random_bytes(8));

        // Odd ids become CachedView descriptors, even ids direct CachedBindView instances
        $map = static fn (ActionCachedItemEntity $e): CachedView|ActionCachedItemView => 1 === $e->id % 2
            ? new CachedView($e, ActionCachedItemView::class)
            : new ActionCachedItemView($e);

        $this->dispatchAction(new IterableView([
            new ActionCachedItemEntity(1, 'Alice', $version),
            new ActionCachedItemEntity(2, 'Bob', $version),
        ], $map));

        $alice = new ActionCachedItemEntity(1, 'Alice', $version);
        $bob = new ActionCachedItemEntity(2, 'Bob', $version);

        $response = $this->dispatchAction(new IterableView([$alice, $bob], $map));

        self::assertSame('{"data":[{"id":1,"name":"Alice"},{"id":2,"name":"Bob"}]}', $response->getContent());
        self::assertSame(0, $alice->getterCalls, 'The CachedView entry must be served from the per-item cache');
        self::assertSame(1, $bob->getterCalls, 'The direct CachedBindView entry must be bound per request');
    }

    private function dispatchAction(ViewInterface $controllerResult): Response
    {
        static::bootKernel();
        $container = static::getContainer();

        $event = new ViewEvent(
            $container->get('kernel'),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            $controllerResult
        );

        $container->get(ViewSubscriber::class)($event);

        $response = $event->getResponse();
        self::assertNotNull($response);

        return $response;
    }
}

final class ActionCachedItemEntity
{
    public int $getterCalls = 0;

    public function __construct(
        public int $id,
        private readonly string $name,
        public string $version,
    ) {
    }

    public function getName(): string
    {
        ++$this->getterCalls;

        return $this->name;
    }
}

final class ActionCachedItemView extends CachedBindView
{
    public ?int $id = null;
    public ?string $name = null;

    public static function createCacheSignature(object $source): string
    {
        \assert($source instanceof ActionCachedItemEntity);

        return \sprintf('action_cached_item_%d_%s', $source->id, $source->version);
    }
}
