<?php

declare(strict_types=1);

namespace Tests\Integrational\CacheWarmer;

use PHPUnit\Framework\TestCase;
use ChamberOrchestra\ViewBundle\CacheWarmer\BindUtilsCacheWarmer;
use ChamberOrchestra\ViewBundle\CacheWarmer\ViewMetadataCacheWarmer;
use ChamberOrchestra\ViewBundle\Serializer\Metadata\ViewMetadataFactory;
use ChamberOrchestra\ViewBundle\Utils\BindUtils;
use ChamberOrchestra\ViewBundle\View\BindView;
use ChamberOrchestra\ViewBundle\View\View;

final class CacheLoadingTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = \sys_get_temp_dir() . '/view_bundle_integration_' . \uniqid();
        \mkdir($this->cacheDir, 0777, true);

        // Reset BindUtils static state
        $reflection = new \ReflectionClass(BindUtils::class);
        $configured = $reflection->getProperty('configured');
        $configured->setValue(null, false);
        $storage = $reflection->getProperty('storage');
        $storage->setValue(null, []);
        $warmedCache = $reflection->getProperty('warmedCache');
        $warmedCache->setValue(null, null);
    }

    protected function tearDown(): void
    {
        if (\is_dir($this->cacheDir)) {
            \array_map('unlink', \glob($this->cacheDir . '/*'));
            \rmdir($this->cacheDir);
        }

        // Reset BindUtils
        $reflection = new \ReflectionClass(BindUtils::class);
        $configured = $reflection->getProperty('configured');
        $configured->setValue(null, false);
        $storage = $reflection->getProperty('storage');
        $storage->setValue(null, []);
        $warmedCache = $reflection->getProperty('warmedCache');
        $warmedCache->setValue(null, null);
    }

    public function testViewMetadataFactoryLoadsFromWarmedCache(): void
    {
        // Create test view
        $testView = new class extends View {
            public string $name = 'test';
            public int $count = 0;
        };

        // Warm up the cache with build ID
        $viewClasses = [$testView::class];
        $warmer = new ViewMetadataCacheWarmer(new ViewMetadataFactory(), $viewClasses, '', 'test-build-id');
        $warmer->warmUp($this->cacheDir);

        // Create factory with share dir and matching build ID
        $factory = new ViewMetadataFactory($this->cacheDir, 'test-build-id');

        // Load metadata - should come from warmed cache
        $metadata = $factory->getMetadata($testView::class);

        self::assertSame($testView::class, $metadata->className);
        self::assertCount(2, $metadata->properties);

        $propNames = \array_map(fn($p) => $p->name, $metadata->properties);
        self::assertContains('name', $propNames);
        self::assertContains('count', $propNames);
    }

    public function testViewMetadataFactoryFallsBackToReflectionWhenCacheMissing(): void
    {
        $testView = new class extends View {
            public string $fallback = 'test';
        };

        // Create factory pointing to empty share dir with build ID
        $factory = new ViewMetadataFactory($this->cacheDir, 'test-build-id');

        // Load metadata - should use reflection fallback
        $metadata = $factory->getMetadata($testView::class);

        self::assertSame($testView::class, $metadata->className);
        self::assertCount(1, $metadata->properties);
        self::assertSame('fallback', $metadata->properties[0]->name);
    }

    public function testBindUtilsLoadsFromWarmedCache(): void
    {
        // Create test view class with properties that have defaults for the dummy object
        $dummyObj = (object)['name' => '', 'id' => 0];
        $viewClass = new class($dummyObj) extends BindView {
            public string $name = '';
            public int $id = 0;
        };

        // Warm up the cache with build ID
        $viewClasses = [$viewClass::class];
        $warmer = new BindUtilsCacheWarmer($viewClasses, '', 'test-build-id');
        $files = $warmer->warmUp($this->cacheDir);

        // Verify cache file was created and contains mapping
        self::assertFileExists($files[0]);
        $cached = require $files[0];
        $key = $viewClass::class . '@' . $viewClass::class;
        self::assertArrayHasKey($key, $cached);
        self::assertArrayHasKey('name', $cached[$key]);
        self::assertArrayHasKey('id', $cached[$key]);

        // Configure BindUtils with matching build ID and share dir
        BindUtils::configure('test-build-id', 0, 'test', $this->cacheDir);

        // Verify warmed cache path includes build ID
        $reflection = new \ReflectionClass(BindUtils::class);
        $warmPathProp = $reflection->getProperty('warmCachePath');
        $warmCachePath = $warmPathProp->getValue();
        self::assertSame($this->cacheDir . '/bind_utils_mappings_test-build-id.php', $warmCachePath);
    }

    public function testBindUtilsFallsBackToReflectionWhenCacheMissing(): void
    {
        // Configure BindUtils with empty cache dir (no warmed cache)
        BindUtils::configure('test-build-id', 0, 'test', $this->cacheDir);

        // Verify no cache file exists
        self::assertFileDoesNotExist($this->cacheDir . '/bind_utils_mappings_test-build-id.php');

        // Verify BindUtils still works with reflection fallback
        $source = new class {
            public string $value = 'test';
        };

        $target = new class {
            public ?string $value = null;  // Nullable property with null value
        };

        // Sync using BindUtils - should use reflection fallback
        BindUtils::instance()->sync($target, $source);

        self::assertSame('test', $target->value);
    }

    public function testWarmedCacheIsLoadedOnlyOnce(): void
    {
        $dummyObj = (object)['shared' => ''];
        $viewClass = new class($dummyObj) extends BindView {
            public string $shared = '';
        };

        // Warm up cache with build ID
        $warmer = new BindUtilsCacheWarmer([$viewClass::class], '', 'test-build-id');
        $warmer->warmUp($this->cacheDir);

        // Configure BindUtils with matching build ID
        BindUtils::configure('test-build-id', 0, 'test', $this->cacheDir);

        // Trigger cache loading by using BindUtils
        $source1 = new class {
            public string $shared = 'test';
        };
        $target1 = new class {
            public ?string $shared = null;  // Nullable with null value
        };
        BindUtils::instance()->sync($target1, $source1);

        // Verify cache was loaded into static property
        $reflection = new \ReflectionClass(BindUtils::class);
        $warmedCacheProp = $reflection->getProperty('warmedCache');
        $warmedCache = $warmedCacheProp->getValue();
        self::assertNotNull($warmedCache, 'Warmed cache should be loaded');

        // Delete cache file
        \unlink($this->cacheDir . '/bind_utils_mappings_test-build-id.php');

        // Second sync - should still work (cache already loaded in memory)
        $source2 = new class {
            public string $shared = 'second';
        };
        $target2 = new class {
            public ?string $shared = null;  // Nullable with null value
        };
        BindUtils::instance()->sync($target2, $source2);

        self::assertSame('second', $target2->shared);
    }

    public function testCacheFilesAreCreatedWithBuildIdSuffix(): void
    {
        $testView = new class extends View {
            public string $name = 'test';
        };

        $dummyObj = (object)['name' => ''];
        $bindView = new class($dummyObj) extends BindView {
            public string $name = '';
        };

        $buildId = 'abc123';
        $viewClasses = [$testView::class, $bindView::class];

        // Warm metadata cache
        $metadataWarmer = new ViewMetadataCacheWarmer(new ViewMetadataFactory(), [$testView::class], '', $buildId);
        $metadataFiles = $metadataWarmer->warmUp($this->cacheDir);

        // Warm bind utils cache
        $bindWarmer = new BindUtilsCacheWarmer([$bindView::class], '', $buildId);
        $bindFiles = $bindWarmer->warmUp($this->cacheDir);

        // Verify filenames contain build ID
        self::assertSame($this->cacheDir . "/view_metadata_{$buildId}.php", $metadataFiles[0]);
        self::assertSame($this->cacheDir . "/bind_utils_mappings_{$buildId}.php", $bindFiles[0]);
        self::assertFileExists($metadataFiles[0]);
        self::assertFileExists($bindFiles[0]);

        // Verify no files without build ID suffix exist
        self::assertFileDoesNotExist($this->cacheDir . '/view_metadata.php');
        self::assertFileDoesNotExist($this->cacheDir . '/bind_utils_mappings.php');
    }

    public function testDifferentBuildsProduceSeparateCacheFiles(): void
    {
        $testView = new class extends View {
            public string $name = 'test';
        };

        $dummyObj = (object)['name' => ''];
        $bindView = new class($dummyObj) extends BindView {
            public string $name = '';
        };

        $buildIdA = 'build-aaa';
        $buildIdB = 'build-bbb';
        $viewClasses = [$testView::class];

        // Warm caches for build A
        $metadataWarmerA = new ViewMetadataCacheWarmer(new ViewMetadataFactory(), $viewClasses, '', $buildIdA);
        $metadataWarmerA->warmUp($this->cacheDir);
        $bindWarmerA = new BindUtilsCacheWarmer([$bindView::class], '', $buildIdA);
        $bindWarmerA->warmUp($this->cacheDir);

        // Warm caches for build B
        $metadataWarmerB = new ViewMetadataCacheWarmer(new ViewMetadataFactory(), $viewClasses, '', $buildIdB);
        $metadataWarmerB->warmUp($this->cacheDir);
        $bindWarmerB = new BindUtilsCacheWarmer([$bindView::class], '', $buildIdB);
        $bindWarmerB->warmUp($this->cacheDir);

        // Verify both sets of files exist independently
        self::assertFileExists($this->cacheDir . "/view_metadata_{$buildIdA}.php");
        self::assertFileExists($this->cacheDir . "/view_metadata_{$buildIdB}.php");
        self::assertFileExists($this->cacheDir . "/bind_utils_mappings_{$buildIdA}.php");
        self::assertFileExists($this->cacheDir . "/bind_utils_mappings_{$buildIdB}.php");

        // Verify factory with build A reads build A's cache
        $factoryA = new ViewMetadataFactory($this->cacheDir, $buildIdA);
        $metadataA = $factoryA->getMetadata($testView::class);
        self::assertSame($testView::class, $metadataA->className);

        // Verify factory with build B reads build B's cache (not A's)
        $factoryB = new ViewMetadataFactory($this->cacheDir, $buildIdB);
        $metadataB = $factoryB->getMetadata($testView::class);
        self::assertSame($testView::class, $metadataB->className);

        // Verify BindUtils with build A reads build A's file
        BindUtils::configure($buildIdA, 0, 'test', $this->cacheDir);
        $reflection = new \ReflectionClass(BindUtils::class);
        $warmPathProp = $reflection->getProperty('warmCachePath');
        self::assertSame($this->cacheDir . "/bind_utils_mappings_{$buildIdA}.php", $warmPathProp->getValue());
    }
}
