<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\View;

use ChamberOrchestra\ViewBundle\Attribute\Type;
use ChamberOrchestra\ViewBundle\Cache\ViewResponseCache;
use ChamberOrchestra\ViewBundle\Serializer\Metadata\ViewMetadataFactory;
use ChamberOrchestra\ViewBundle\Serializer\Normalizer\ViewNormalizer;
use ChamberOrchestra\ViewBundle\Utils\BindUtils;
use ChamberOrchestra\ViewBundle\View\CachedView;
use ChamberOrchestra\ViewBundle\View\IterableView;
use ChamberOrchestra\ViewBundle\View\SourceCacheSignatureInterface;
use ChamberOrchestra\ViewBundle\View\View;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\CustomNormalizer;
use Symfony\Component\Serializer\Serializer;

final class CachedCollectionTest extends TestCase
{
    protected function setUp(): void
    {
        CachedCollectionItemView::$constructed = 0;
    }

    public function testCachedTypeAttributeMapsElementsToCachedViewsWithoutBuildingThem(): void
    {
        $bindUtils = new BindUtils();

        $source = new class {
            /** @var list<CachedCollectionItemEntity> */
            public array $items = [];
        };
        $source->items = [new CachedCollectionItemEntity(1, 'Alice'), new CachedCollectionItemEntity(2, 'Bob')];

        $target = new class {
            #[Type(CachedCollectionItemView::class, cached: true)]
            public ?IterableView $items = null;
        };

        $bindUtils->sync($target, $source);

        self::assertInstanceOf(IterableView::class, $target->items);
        self::assertCount(2, $target->items->entries);
        self::assertContainsOnlyInstancesOf(CachedView::class, $target->items->entries);
        self::assertSame(0, CachedCollectionItemView::$constructed, 'Element views must not be built during binding');
    }

    public function testCollectionItemsAreServedFromThePerItemCache(): void
    {
        $serializer = $this->createSerializer(new ViewResponseCache(new ArrayAdapter()));

        $alice = new CachedCollectionItemEntity(1, 'Alice');
        $bob = new CachedCollectionItemEntity(2, 'Bob');

        $json = $serializer->serialize($this->createCachedCollection($alice, $bob), 'json');

        self::assertSame('[{"id":1,"name":"Alice"},{"id":2,"name":"Bob"}]', $json);
        self::assertSame(2, CachedCollectionItemView::$constructed);

        $json = $serializer->serialize($this->createCachedCollection($alice, $bob), 'json');

        self::assertSame('[{"id":1,"name":"Alice"},{"id":2,"name":"Bob"}]', $json);
        self::assertSame(2, CachedCollectionItemView::$constructed, 'Cached items must not be rebuilt');

        $json = $serializer->serialize($this->createCachedCollection($alice, $bob, new CachedCollectionItemEntity(3, 'Carol')), 'json');

        self::assertSame('[{"id":1,"name":"Alice"},{"id":2,"name":"Bob"},{"id":3,"name":"Carol"}]', $json);
        self::assertSame(3, CachedCollectionItemView::$constructed, 'Only the new item must be built');
    }

    public function testChangedItemProducesAFreshPayload(): void
    {
        $serializer = $this->createSerializer(new ViewResponseCache(new ArrayAdapter()));

        $serializer->serialize($this->createCachedCollection(new CachedCollectionItemEntity(1, 'Alice')), 'json');

        $json = $serializer->serialize($this->createCachedCollection(new CachedCollectionItemEntity(1, 'Alice-Renamed')), 'json');

        self::assertSame('[{"id":1,"name":"Alice-Renamed"}]', $json);
        self::assertSame(2, CachedCollectionItemView::$constructed, 'A changed item must be rebuilt');
    }

    private function createSerializer(ViewResponseCache $cache): Serializer
    {
        return new Serializer(
            [new CustomNormalizer(), new ViewNormalizer(new ViewMetadataFactory(), $cache)],
            [new JsonEncoder()],
        );
    }

    private function createCachedCollection(CachedCollectionItemEntity ...$entities): IterableView
    {
        return new IterableView($entities, static fn (object $v): CachedView => new CachedView($v, CachedCollectionItemView::class));
    }
}

final class CachedCollectionItemEntity
{
    public function __construct(
        public int $id,
        public string $name,
    ) {
    }
}

final class CachedCollectionItemView extends View implements SourceCacheSignatureInterface
{
    public static int $constructed = 0;

    public int $id;
    public string $name;

    public function __construct(object $source)
    {
        \assert($source instanceof CachedCollectionItemEntity);
        ++self::$constructed;
        $this->id = $source->id;
        $this->name = $source->name;
    }

    public static function createCacheSignature(object $source): string
    {
        \assert($source instanceof CachedCollectionItemEntity);

        return \sprintf('cached_collection_item_%d_%s', $source->id, $source->name);
    }
}
