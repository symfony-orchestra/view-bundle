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

        // Warm up the cache
        $viewClasses = [$testView::class];
        $warmer = new ViewMetadataCacheWarmer(new ViewMetadataFactory(), $viewClasses);
        $warmer->warmUp($this->cacheDir);

        // Create factory with cache dir
        $factory = new ViewMetadataFactory($this->cacheDir);

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

        // Create factory pointing to empty cache dir
        $factory = new ViewMetadataFactory($this->cacheDir);

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

        // Warm up the cache
        $viewClasses = [$viewClass::class];
        $warmer = new BindUtilsCacheWarmer($viewClasses);
        $files = $warmer->warmUp($this->cacheDir);

        // Verify cache file was created and contains mapping
        self::assertFileExists($files[0]);
        $cached = require $files[0];
        $key = $viewClass::class . '@' . $viewClass::class;
        self::assertArrayHasKey($key, $cached);
        self::assertArrayHasKey('name', $cached[$key]);
        self::assertArrayHasKey('id', $cached[$key]);

        // Configure BindUtils with cache dir
        BindUtils::configure('test-build-id', 0, 'test', $this->cacheDir);

        // Verify warmed cache is actually loaded by checking static property
        $reflection = new \ReflectionClass(BindUtils::class);
        $warmPathProp = $reflection->getProperty('warmCachePath');
        $warmCachePath = $warmPathProp->getValue();
        self::assertSame($this->cacheDir . '/bind_utils_mappings.php', $warmCachePath);
    }

    public function testBindUtilsFallsBackToReflectionWhenCacheMissing(): void
    {
        // Configure BindUtils with empty cache dir (no warmed cache)
        BindUtils::configure('test-build-id', 0, 'test', $this->cacheDir);

        // Verify no cache file exists
        self::assertFileDoesNotExist($this->cacheDir . '/bind_utils_mappings.php');

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

        // Warm up cache
        $warmer = new BindUtilsCacheWarmer([$viewClass::class]);
        $warmer->warmUp($this->cacheDir);

        // Configure BindUtils
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
        \unlink($this->cacheDir . '/bind_utils_mappings.php');

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
}
