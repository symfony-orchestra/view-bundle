<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\View;

use ChamberOrchestra\ViewBundle\Cache\ViewResponseCache;
use ChamberOrchestra\ViewBundle\Serializer\Metadata\ViewMetadataFactory;
use ChamberOrchestra\ViewBundle\Serializer\Normalizer\ViewNormalizer;
use ChamberOrchestra\ViewBundle\View\BindView;
use ChamberOrchestra\ViewBundle\View\CachedBindView;
use ChamberOrchestra\ViewBundle\View\CachedView;
use ChamberOrchestra\ViewBundle\View\IterableView;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\CustomNormalizer;
use Symfony\Component\Serializer\Serializer;

final class IterableViewCachedEntriesTest extends TestCase
{
    protected function setUp(): void
    {
        BindView::setBindUtils(null);
    }

    protected function tearDown(): void
    {
        BindView::setBindUtils(null);
    }

    public function testCachedViewEntriesAreCheapDescriptorsUntilSerialized(): void
    {
        $alice = new IterableCachedEntity(1, 'Alice');
        $bob = new IterableCachedEntity(2, 'Bob');

        $view = new IterableView([$alice, $bob], static fn (object $e): CachedView => new CachedView($e, IterableCachedItemView::class));

        self::assertContainsOnlyInstancesOf(CachedView::class, $view->entries);
        self::assertSame(0, $alice->getterCalls, 'Mapping to CachedView must not read the source');
        self::assertSame(0, $bob->getterCalls);

        $json = $this->createSerializer(new ViewResponseCache(new ArrayAdapter()))->serialize($view, 'json');

        self::assertSame('[{"id":1,"name":"Alice"},{"id":2,"name":"Bob"}]', $json);
        self::assertSame(1, $alice->getterCalls, 'Serialization must bind each item exactly once');
        self::assertSame(1, $bob->getterCalls);
    }

    public function testCachedViewEntriesAreServedFromThePerItemCacheAcrossSerializations(): void
    {
        $serializer = $this->createSerializer(new ViewResponseCache(new ArrayAdapter()));
        $map = static fn (object $e): CachedView => new CachedView($e, IterableCachedItemView::class);

        $serializer->serialize(new IterableView([new IterableCachedEntity(1, 'Alice'), new IterableCachedEntity(2, 'Bob')], $map), 'json');

        $alice = new IterableCachedEntity(1, 'Alice');
        $bob = new IterableCachedEntity(2, 'Bob');

        $json = $serializer->serialize(new IterableView([$alice, $bob], $map), 'json');

        self::assertSame('[{"id":1,"name":"Alice"},{"id":2,"name":"Bob"}]', $json);
        self::assertSame(0, $alice->getterCalls, 'Cached items must not be bound again');
        self::assertSame(0, $bob->getterCalls);
    }

    public function testCachedBindViewEntriesBindLazilyAndOnEverySerialization(): void
    {
        $serializer = $this->createSerializer(new ViewResponseCache(new ArrayAdapter()));

        $alice = new IterableCachedEntity(1, 'Alice');
        $view = new IterableView([$alice], IterableCachedItemView::class);

        self::assertContainsOnlyInstancesOf(IterableCachedItemView::class, $view->entries);
        self::assertSame(0, $alice->getterCalls, 'CachedBindView construction must not bind');

        $json = $serializer->serialize($view, 'json');

        self::assertSame('[{"id":1,"name":"Alice"}]', $json);
        self::assertSame(1, $alice->getterCalls);

        // Direct CachedBindView entries carry no per-item payload cache: a fresh
        // list over the same data binds again (wrap in CachedView to avoid that)
        $freshAlice = new IterableCachedEntity(1, 'Alice');
        $serializer->serialize(new IterableView([$freshAlice], IterableCachedItemView::class), 'json');

        self::assertSame(1, $freshAlice->getterCalls);
    }

    public function testMixedCachedViewAndCachedBindViewEntries(): void
    {
        $serializer = $this->createSerializer(new ViewResponseCache(new ArrayAdapter()));

        // Odd ids become CachedView descriptors, even ids direct CachedBindView instances
        $map = static fn (IterableCachedEntity $e): CachedView|IterableCachedItemView => 1 === $e->id % 2
            ? new CachedView($e, IterableCachedItemView::class)
            : new IterableCachedItemView($e);

        $json = $serializer->serialize(new IterableView([new IterableCachedEntity(1, 'Alice'), new IterableCachedEntity(2, 'Bob')], $map), 'json');

        self::assertSame('[{"id":1,"name":"Alice"},{"id":2,"name":"Bob"}]', $json);

        $alice = new IterableCachedEntity(1, 'Alice');
        $bob = new IterableCachedEntity(2, 'Bob');

        $json = $serializer->serialize(new IterableView([$alice, $bob], $map), 'json');

        self::assertSame('[{"id":1,"name":"Alice"},{"id":2,"name":"Bob"}]', $json);
        self::assertSame(0, $alice->getterCalls, 'The CachedView entry must be served from the per-item cache');
        self::assertSame(1, $bob->getterCalls, 'The direct CachedBindView entry must be bound per serialization');
    }

    private function createSerializer(ViewResponseCache $cache): Serializer
    {
        return new Serializer(
            [new CustomNormalizer(), new ViewNormalizer(new ViewMetadataFactory(), $cache)],
            [new JsonEncoder()],
        );
    }
}

final class IterableCachedEntity
{
    public int $getterCalls = 0;

    public function __construct(
        public int $id,
        private readonly string $name,
        public string $version = '1',
    ) {
    }

    public function getName(): string
    {
        ++$this->getterCalls;

        return $this->name;
    }
}

final class IterableCachedItemView extends CachedBindView
{
    public ?int $id = null;
    public ?string $name = null;

    public static function createCacheSignature(object $source): string
    {
        \assert($source instanceof IterableCachedEntity);

        // Signatures must be cheap and read no counted getters: id + version marker
        return \sprintf('iterable_cached_item_%d_%s', $source->id, $source->version);
    }
}
