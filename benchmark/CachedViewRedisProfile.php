<?php

declare(strict_types=1);

namespace Benchmark;

use ChamberOrchestra\ViewBundle\Attribute\Type;
use ChamberOrchestra\ViewBundle\Cache\ViewResponseCache;
use ChamberOrchestra\ViewBundle\Serializer\Metadata\ViewMetadataFactory;
use ChamberOrchestra\ViewBundle\Serializer\Normalizer\ViewNormalizer;
use ChamberOrchestra\ViewBundle\Utils\BindUtils;
use ChamberOrchestra\ViewBundle\View\BindView;
use ChamberOrchestra\ViewBundle\View\CachedBindView;
use ChamberOrchestra\ViewBundle\View\CachedView;
use ChamberOrchestra\ViewBundle\View\DataView;
use ChamberOrchestra\ViewBundle\View\IterableView;
use ChamberOrchestra\ViewBundle\View\SourceCacheSignatureInterface;
use PhpBench\Attributes as Bench;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\CustomNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * Optional CachedView benchmark against a real Redis pool.
 *
 * Deliberately named without the "Bench.php" suffix so `composer bench` skips it;
 * it requires a running Redis server (default redis://127.0.0.1:6379, override
 * via the CACHED_VIEW_BENCH_REDIS_DSN environment variable). Run explicitly:
 *
 *   vendor/bin/phpbench run benchmark/CachedViewRedisProfile.php --report=aggregate
 */
class CachedViewRedisProfile
{
    private const int LIST_SIZE = 100;
    private const int CACHE_LIFETIME_SECONDS = 3600;

    private Serializer $serializer;
    private ViewResponseCache $redisCache;
    private RedisProfileEntity $entity;
    private RedisProfileListContainer $listContainer;

    /** @var list<RedisProfileEntity> */
    private array $entityList;

    public function __construct()
    {
        if (!\class_exists(\Redis::class)) {
            throw new \RuntimeException('The redis extension is not loaded; load it or run without --php-disable-ini.');
        }

        $dsn = \getenv('CACHED_VIEW_BENCH_REDIS_DSN') ?: 'redis://127.0.0.1:6379';

        try {
            $client = RedisAdapter::createConnection($dsn, ['timeout' => 0.5]);
            $client->ping();
        } catch (\Throwable $e) {
            throw new \RuntimeException(\sprintf('Redis is not reachable on "%s": %s', $dsn, $e->getMessage()), 0, $e);
        }

        BindView::setBindUtils(new BindUtils('bench-build-id'));

        $this->serializer = new Serializer(
            [new CustomNormalizer(), new ViewNormalizer(new ViewMetadataFactory()), new ObjectNormalizer()],
            [new JsonEncoder()],
        );

        $this->entity = new RedisProfileEntity();
        $this->entityList = array_map(static fn (int $id): RedisProfileEntity => new RedisProfileEntity($id), range(1, self::LIST_SIZE));
        $this->listContainer = new RedisProfileListContainer($this->entityList);

        // Entries expire on their own, so the benchmark leaves no permanent keys behind
        $this->redisCache = new ViewResponseCache(new RedisAdapter($client, 'cached_view_bench', self::CACHE_LIFETIME_SECONDS));

        // Warm the pool so the hit benchmarks measure hits only
        $this->redisCache->get($this->createCachedView()->getCacheSignature(), null, $this->produceJson(...));
        $this->redisCache->get($this->createCachedListView()->getCacheSignature(), null, $this->produceListJson(...));
    }

    /**
     * The uncached request cost, for comparison within the same run.
     */
    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(5)]
    public function benchFullPipelineWithoutCache(): void
    {
        $this->produceJson();
    }

    /**
     * CachedView served from Redis (small 5-property payload).
     */
    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(5)]
    public function benchRedisPoolHit(): void
    {
        $this->redisCache->get($this->createCachedView()->getCacheSignature(), null, $this->produceJson(...));
    }

    /**
     * The uncached request cost for a 100-item collection payload.
     */
    #[Bench\Revs(100)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(5)]
    public function benchFullPipelineWithoutCacheLargeList(): void
    {
        $this->produceListJson();
    }

    /**
     * The 100-item collection payload served from Redis.
     */
    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(5)]
    public function benchRedisPoolHitLargeList(): void
    {
        $this->redisCache->get($this->createCachedListView()->getCacheSignature(), null, $this->produceListJson(...));
    }

    private function createCachedView(): CachedView
    {
        return new CachedView($this->entity, RedisProfileUserView::class);
    }

    private function createCachedListView(): CachedView
    {
        return new CachedView($this->listContainer, RedisProfileListView::class);
    }

    private function produceJson(): string
    {
        return $this->serializer->serialize(
            new DataView(new RedisProfileUserView($this->entity)),
            'json',
            ['json_encode_options' => JsonResponse::DEFAULT_ENCODING_OPTIONS],
        );
    }

    private function produceListJson(): string
    {
        return $this->serializer->serialize(
            new DataView(new IterableView($this->entityList, RedisProfileUserView::class)),
            'json',
            ['json_encode_options' => JsonResponse::DEFAULT_ENCODING_OPTIONS],
        );
    }
}

class RedisProfileEntity
{
    public string $name = 'John Doe';
    public int $age = 30;
    public string $email = 'john@example.com';
    public string $phone = '555-1234';
    public string $address = '123 Main St';

    public function __construct(public int $id = 1)
    {
    }
}

class RedisProfileUserView extends BindView implements SourceCacheSignatureInterface
{
    public ?string $name = null;
    public ?int $age = null;
    public ?string $email = null;
    public ?string $phone = null;
    public ?string $address = null;

    public static function createCacheSignature(object $source): string
    {
        \assert($source instanceof RedisProfileEntity);

        return 'redis_profile_user_'.$source->id;
    }
}

class RedisProfileListContainer
{
    /**
     * @param list<RedisProfileEntity> $items
     */
    public function __construct(
        public array $items,
        public int $version = 1,
    ) {
    }
}

class RedisProfileListView extends CachedBindView
{
    #[Type(RedisProfileUserView::class)]
    public ?IterableView $items = null;

    public static function createCacheSignature(object $source): string
    {
        \assert($source instanceof RedisProfileListContainer);

        return 'redis_profile_list_'.$source->version;
    }
}
