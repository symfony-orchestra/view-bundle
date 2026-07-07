<?php

declare(strict_types=1);

namespace Benchmark;

use ChamberOrchestra\ViewBundle\Attribute\Type;
use ChamberOrchestra\ViewBundle\Cache\ViewResponseCache;
use ChamberOrchestra\ViewBundle\Localisation\LocalisationBridge;
use ChamberOrchestra\ViewBundle\Security\SecurityBridge;
use ChamberOrchestra\ViewBundle\Serializer\Metadata\ViewMetadataFactory;
use ChamberOrchestra\ViewBundle\Serializer\Normalizer\ViewNormalizer;
use ChamberOrchestra\ViewBundle\Utils\BindUtils;
use ChamberOrchestra\ViewBundle\View\AutoCacheSignatureTrait;
use ChamberOrchestra\ViewBundle\View\BindView;
use ChamberOrchestra\ViewBundle\View\CacheableViewInterface;
use ChamberOrchestra\ViewBundle\View\CachedBindView;
use ChamberOrchestra\ViewBundle\View\CachedView;
use ChamberOrchestra\ViewBundle\View\DataView;
use ChamberOrchestra\ViewBundle\View\IterableView;
use ChamberOrchestra\ViewBundle\View\PrivateCachedView;
use ChamberOrchestra\ViewBundle\View\SourceCacheSignatureInterface;
use PhpBench\Attributes as Bench;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\CustomNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class CachedViewBench
{
    private const int LIST_SIZE = 100;

    private Serializer $serializer;
    private ViewResponseCache $arrayCache;
    private ViewResponseCache $filesystemCache;
    private CachedViewBenchEntity $entity;
    private CachedViewBenchListContainer $listContainer;

    /** @var list<CachedViewBenchEntity> */
    private array $entityList;

    public function __construct()
    {
        BindView::setBindUtils(new BindUtils('bench-build-id'));

        // Authenticated request context for the PrivateCachedView benchmarks,
        // injected the same way SetSecuritySubscriber / SetLocalisationSubscriber do
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken(new InMemoryUser('bench-user', null), 'main'));
        SecurityBridge::setTokenStorage($tokenStorage);

        $request = new Request();
        $request->setLocale('en');
        $requestStack = new RequestStack();
        $requestStack->push($request);
        LocalisationBridge::setRequestStack($requestStack);

        $this->entity = new CachedViewBenchEntity();
        $this->entityList = array_map(static fn (int $id): CachedViewBenchEntity => new CachedViewBenchEntity($id), range(1, self::LIST_SIZE));
        $this->listContainer = new CachedViewBenchListContainer($this->entityList);

        $this->arrayCache = new ViewResponseCache(new ArrayAdapter());
        $this->filesystemCache = new ViewResponseCache(
            new FilesystemAdapter('cached_view_bench', 0, sys_get_temp_dir().'/view_bundle_bench_cache'),
        );

        // Per-item CachedView entries resolve through the normalizer's cache
        $this->serializer = new Serializer(
            [new CustomNormalizer(), new ViewNormalizer(new ViewMetadataFactory(), $this->arrayCache), new ObjectNormalizer()],
            [new JsonEncoder()],
        );

        // Warm both pools so the hit benchmarks measure hits only
        $this->arrayCache->get($this->createCachedView()->getCacheSignature(), null, $this->produceJson(...));
        $this->filesystemCache->get($this->createCachedView()->getCacheSignature(), null, $this->produceJson(...));
        $this->arrayCache->get($this->createCachedListView()->getCacheSignature(), null, $this->produceCachedBindListJson(...));
        $this->filesystemCache->get($this->createCachedListView()->getCacheSignature(), null, $this->produceCachedBindListJson(...));

        $autoSignedList = new CachedViewBenchAutoSignedListView($this->entityList, CachedViewBenchUserView::class);
        $this->arrayCache->get($autoSignedList->getCacheSignature(), null, $this->produceListJson(...));
        $this->filesystemCache->get($autoSignedList->getCacheSignature(), null, $this->produceListJson(...));

        $cachedBindList = new CachedViewBenchCachedBindListView($this->listContainer);
        $this->arrayCache->get($cachedBindList->getCacheSignature(), null, $this->produceCachedBindListJson(...));
        $this->filesystemCache->get($cachedBindList->getCacheSignature(), null, $this->produceCachedBindListJson(...));

        // Warm the per-item entries
        $this->producePerItemCachedListJson();

        $this->arrayCache->get($this->createPrivateCachedView()->getCacheSignature(), null, $this->produceJson(...));
        $this->filesystemCache->get($this->createPrivateCachedView()->getCacheSignature(), null, $this->produceJson(...));
        $this->arrayCache->get($this->createPrivateCachedListView()->getCacheSignature(), null, $this->produceCachedBindListJson(...));
        $this->filesystemCache->get($this->createPrivateCachedListView()->getCacheSignature(), null, $this->produceCachedBindListJson(...));
    }

    /**
     * The uncached request cost: build the BindView, wrap in DataView, normalize and json_encode.
     */
    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(5)]
    public function benchFullPipelineWithoutCache(): void
    {
        $this->produceJson();
    }

    /**
     * CachedView served from an in-memory pool (best case: signature hash + pool lookup).
     */
    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(5)]
    public function benchArrayPoolHit(): void
    {
        $this->arrayCache->get($this->createCachedView()->getCacheSignature(), null, $this->produceJson(...));
    }

    /**
     * CachedView served from a filesystem pool (realistic cache.app hit including I/O).
     */
    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(5)]
    public function benchFilesystemPoolHit(): void
    {
        $this->filesystemCache->get($this->createCachedView()->getCacheSignature(), null, $this->produceJson(...));
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
     * The 100-item collection payload served from an in-memory pool.
     */
    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(5)]
    public function benchArrayPoolHitLargeList(): void
    {
        $this->arrayCache->get($this->createCachedListView()->getCacheSignature(), null, $this->produceListJson(...));
    }

    /**
     * The 100-item collection payload served from a filesystem pool.
     */
    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(5)]
    public function benchFilesystemPoolHitLargeList(): void
    {
        $this->filesystemCache->get($this->createCachedListView()->getCacheSignature(), null, $this->produceListJson(...));
    }

    /**
     * Auto-signature path for the 100-item collection: build the views (binding still runs),
     * hash the values via AutoCacheSignatureTrait, and serve the JSON from an in-memory pool.
     * Compare against benchFullPipelineWithoutCacheLargeList to see what the hash-based hit saves.
     */
    #[Bench\Revs(100)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(5)]
    public function benchAutoSignatureArrayPoolHitLargeList(): void
    {
        $view = new CachedViewBenchAutoSignedListView($this->entityList, CachedViewBenchUserView::class);

        $this->arrayCache->get($view->getCacheSignature(), null, $this->produceListJson(...));
    }

    /**
     * Auto-signature path for the 100-item collection against a filesystem pool.
     */
    #[Bench\Revs(100)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(5)]
    public function benchAutoSignatureFilesystemPoolHitLargeList(): void
    {
        $view = new CachedViewBenchAutoSignedListView($this->entityList, CachedViewBenchUserView::class);

        $this->filesystemCache->get($view->getCacheSignature(), null, $this->produceListJson(...));
    }

    /**
     * The collection itself is not cached, but every item is a CachedView resolved from
     * the per-item normalized cache: 100 pool lookups + json_encode per request.
     * The middle ground between no caching and whole-list caching.
     */
    #[Bench\Revs(100)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(5)]
    public function benchPerItemCachedListArrayPool(): void
    {
        $this->producePerItemCachedListJson();
    }

    /**
     * CachedBindView with a 100-item #[Type] collection served from an in-memory pool:
     * the signature comes from the source, so a hit skips binding AND serialization.
     */
    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(5)]
    public function benchCachedBindViewArrayPoolHitLargeList(): void
    {
        $view = new CachedViewBenchCachedBindListView($this->listContainer);

        $this->arrayCache->get($view->getCacheSignature(), null, $this->produceCachedBindListJson(...));
    }

    /**
     * CachedBindView with a 100-item #[Type] collection served from a filesystem pool.
     */
    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(5)]
    public function benchCachedBindViewFilesystemPoolHitLargeList(): void
    {
        $view = new CachedViewBenchCachedBindListView($this->listContainer);

        $this->filesystemCache->get($view->getCacheSignature(), null, $this->produceCachedBindListJson(...));
    }

    /**
     * PrivateCachedView (user + locale scoped signature) served from an in-memory pool.
     * The extra cost over CachedView is resolving the identity and locale from the bridges.
     */
    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(5)]
    public function benchPrivateCachedViewArrayPoolHit(): void
    {
        $view = $this->createPrivateCachedView();

        $this->arrayCache->get($view->getCacheSignature(), null, $this->produceJson(...));
    }

    /**
     * PrivateCachedView served from a filesystem pool.
     */
    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(5)]
    public function benchPrivateCachedViewFilesystemPoolHit(): void
    {
        $view = $this->createPrivateCachedView();

        $this->filesystemCache->get($view->getCacheSignature(), null, $this->produceJson(...));
    }

    /**
     * PrivateCachedView over the 100-item #[Type] collection served from an in-memory pool.
     */
    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(5)]
    public function benchPrivateCachedViewArrayPoolHitLargeList(): void
    {
        $view = $this->createPrivateCachedListView();

        $this->arrayCache->get($view->getCacheSignature(), null, $this->produceCachedBindListJson(...));
    }

    /**
     * PrivateCachedView over the 100-item #[Type] collection served from a filesystem pool.
     */
    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(5)]
    public function benchPrivateCachedViewFilesystemPoolHitLargeList(): void
    {
        $view = $this->createPrivateCachedListView();

        $this->filesystemCache->get($view->getCacheSignature(), null, $this->produceCachedBindListJson(...));
    }

    private function createCachedView(): CachedView
    {
        // Mirrors controller usage: a fresh CachedView descriptor on every request
        return new CachedView($this->entity, CachedViewBenchUserView::class);
    }

    private function createPrivateCachedView(): PrivateCachedView
    {
        return new PrivateCachedView($this->entity, CachedViewBenchUserView::class);
    }

    private function createPrivateCachedListView(): PrivateCachedView
    {
        return new PrivateCachedView($this->listContainer, CachedViewBenchCachedBindListView::class);
    }

    private function createCachedListView(): CachedView
    {
        return new CachedView($this->listContainer, CachedViewBenchCachedBindListView::class);
    }

    private function produceJson(): string
    {
        return $this->serializer->serialize(
            new DataView(new CachedViewBenchUserView($this->entity)),
            'json',
            ['json_encode_options' => JsonResponse::DEFAULT_ENCODING_OPTIONS],
        );
    }

    private function produceListJson(): string
    {
        return $this->serializer->serialize(
            new DataView(new IterableView($this->entityList, CachedViewBenchUserView::class)),
            'json',
            ['json_encode_options' => JsonResponse::DEFAULT_ENCODING_OPTIONS],
        );
    }

    private function produceCachedBindListJson(): string
    {
        // ViewNormalizer triggers the deferred binding during serialization
        return $this->serializer->serialize(
            new DataView(new CachedViewBenchCachedBindListView($this->listContainer)),
            'json',
            ['json_encode_options' => JsonResponse::DEFAULT_ENCODING_OPTIONS],
        );
    }

    private function producePerItemCachedListJson(): string
    {
        return $this->serializer->serialize(
            new DataView(new IterableView($this->entityList, static fn (object $v): CachedView => new CachedView($v, CachedViewBenchUserView::class))),
            'json',
            ['json_encode_options' => JsonResponse::DEFAULT_ENCODING_OPTIONS],
        );
    }
}

class CachedViewBenchEntity
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

class CachedViewBenchUserView extends BindView implements SourceCacheSignatureInterface
{
    public ?string $name = null;
    public ?int $age = null;
    public ?string $email = null;
    public ?string $phone = null;
    public ?string $address = null;

    public static function createCacheSignature(object $source): string
    {
        \assert($source instanceof CachedViewBenchEntity);

        return 'cached_view_bench_user_'.$source->id;
    }
}

class CachedViewBenchAutoSignedListView extends IterableView implements CacheableViewInterface
{
    use AutoCacheSignatureTrait;
}

class CachedViewBenchListContainer
{
    /**
     * @param list<CachedViewBenchEntity> $items
     */
    public function __construct(
        public array $items,
        public int $version = 1,
    ) {
    }
}

class CachedViewBenchCachedBindListView extends CachedBindView
{
    #[Type(CachedViewBenchUserView::class)]
    public ?IterableView $items = null;

    public static function createCacheSignature(object $source): string
    {
        \assert($source instanceof CachedViewBenchListContainer);

        return 'cached_view_bench_bind_list_'.$source->version;
    }
}
