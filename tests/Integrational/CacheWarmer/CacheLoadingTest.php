<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Integrational\CacheWarmer;

use ChamberOrchestra\ViewBundle\CacheWarmer\BindUtilsCacheWarmer;
use ChamberOrchestra\ViewBundle\CacheWarmer\ViewMetadataCacheWarmer;
use ChamberOrchestra\ViewBundle\Serializer\Metadata\ViewMetadataFactory;
use ChamberOrchestra\ViewBundle\Utils\BindUtils;
use ChamberOrchestra\ViewBundle\View\BindView;
use ChamberOrchestra\ViewBundle\View\View;
use PHPUnit\Framework\TestCase;

final class CacheLoadingTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = \sys_get_temp_dir().'/view_bundle_integration_'.\uniqid();
        \mkdir($this->cacheDir, 0o777, true);
        BindView::setBindUtils(null);
    }

    protected function tearDown(): void
    {
        BindView::setBindUtils(null);

        if (\is_dir($this->cacheDir)) {
            \array_map('unlink', \glob($this->cacheDir.'/*'));
            \rmdir($this->cacheDir);
        }
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

        $propNames = \array_map(static fn ($p) => $p->name, $metadata->properties);
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
        $dummyObj = (object) ['name' => '', 'id' => 0];
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
        $key = $viewClass::class.'@'.$viewClass::class;
        self::assertArrayHasKey($key, $cached);
        self::assertArrayHasKey('name', $cached[$key]);
        self::assertArrayHasKey('id', $cached[$key]);

        // Create BindUtils with matching build ID and share dir
        $bindUtils = new BindUtils('test-build-id', false, $this->cacheDir);
        BindView::setBindUtils($bindUtils);

        // Verify warmed cache path includes build ID via reflection
        $reflection = new \ReflectionClass(BindUtils::class);
        $warmPathProp = $reflection->getProperty('warmCachePath');
        $warmCachePath = $warmPathProp->getValue($bindUtils);
        self::assertSame($this->cacheDir.'/bind_utils_mappings_test-build-id.php', $warmCachePath);
    }

    public function testBindUtilsFallsBackToReflectionWhenCacheMissing(): void
    {
        // Create BindUtils with empty cache dir (no warmed cache)
        $bindUtils = new BindUtils('test-build-id', false, $this->cacheDir);

        // Verify no cache file exists
        self::assertFileDoesNotExist($this->cacheDir.'/bind_utils_mappings_test-build-id.php');

        // Verify BindUtils still works with reflection fallback
        $source = new class {
            public string $value = 'test';
        };

        $target = new class {
            public ?string $value = null;  // Nullable property with null value
        };

        // Sync using BindUtils - should use reflection fallback
        $bindUtils->sync($target, $source);

        self::assertSame('test', $target->value);
    }

    public function testWarmedCacheIsLoadedOnlyOnce(): void
    {
        $dummyObj = (object) ['shared' => ''];
        $viewClass = new class($dummyObj) extends BindView {
            public string $shared = '';
        };

        // Warm up cache with build ID
        $warmer = new BindUtilsCacheWarmer([$viewClass::class], '', 'test-build-id');
        $warmer->warmUp($this->cacheDir);

        // Create BindUtils with matching build ID
        $bindUtils = new BindUtils('test-build-id', false, $this->cacheDir);
        BindView::setBindUtils($bindUtils);

        // Trigger cache loading by using BindUtils
        $source1 = new class {
            public string $shared = 'test';
        };
        $target1 = new class {
            public ?string $shared = null;  // Nullable with null value
        };
        $bindUtils->sync($target1, $source1);

        // Verify cache was loaded into instance property
        $reflection = new \ReflectionClass(BindUtils::class);
        $warmedCacheProp = $reflection->getProperty('warmedCache');
        $warmedCache = $warmedCacheProp->getValue($bindUtils);
        self::assertNotNull($warmedCache, 'Warmed cache should be loaded');

        // Delete cache file
        \unlink($this->cacheDir.'/bind_utils_mappings_test-build-id.php');

        // Second sync - should still work (cache already loaded in memory)
        $source2 = new class {
            public string $shared = 'second';
        };
        $target2 = new class {
            public ?string $shared = null;  // Nullable with null value
        };
        $bindUtils->sync($target2, $source2);

        self::assertSame('second', $target2->shared);
    }

    public function testCacheFilesAreCreatedWithBuildIdSuffix(): void
    {
        $testView = new class extends View {
            public string $name = 'test';
        };

        $dummyObj = (object) ['name' => ''];
        $bindView = new class($dummyObj) extends BindView {
            public string $name = '';
        };

        $buildId = 'abc123';

        // Warm metadata cache
        $metadataWarmer = new ViewMetadataCacheWarmer(new ViewMetadataFactory(), [$testView::class], '', $buildId);
        $metadataFiles = $metadataWarmer->warmUp($this->cacheDir);

        // Warm bind utils cache
        $bindWarmer = new BindUtilsCacheWarmer([$bindView::class], '', $buildId);
        $bindFiles = $bindWarmer->warmUp($this->cacheDir);

        // Verify filenames contain build ID
        self::assertSame($this->cacheDir."/view_metadata_{$buildId}.php", $metadataFiles[0]);
        self::assertSame($this->cacheDir."/bind_utils_mappings_{$buildId}.php", $bindFiles[0]);
        self::assertFileExists($metadataFiles[0]);
        self::assertFileExists($bindFiles[0]);

        // Verify no files without build ID suffix exist
        self::assertFileDoesNotExist($this->cacheDir.'/view_metadata.php');
        self::assertFileDoesNotExist($this->cacheDir.'/bind_utils_mappings.php');
    }

    public function testDifferentBuildsProduceSeparateCacheFiles(): void
    {
        $testView = new class extends View {
            public string $name = 'test';
        };

        $dummyObj = (object) ['name' => ''];
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
        self::assertFileExists($this->cacheDir."/view_metadata_{$buildIdA}.php");
        self::assertFileExists($this->cacheDir."/view_metadata_{$buildIdB}.php");
        self::assertFileExists($this->cacheDir."/bind_utils_mappings_{$buildIdA}.php");
        self::assertFileExists($this->cacheDir."/bind_utils_mappings_{$buildIdB}.php");

        // Verify factory with build A reads build A's cache
        $factoryA = new ViewMetadataFactory($this->cacheDir, $buildIdA);
        $metadataA = $factoryA->getMetadata($testView::class);
        self::assertSame($testView::class, $metadataA->className);

        // Verify factory with build B reads build B's cache (not A's)
        $factoryB = new ViewMetadataFactory($this->cacheDir, $buildIdB);
        $metadataB = $factoryB->getMetadata($testView::class);
        self::assertSame($testView::class, $metadataB->className);

        // Verify BindUtils with build A reads build A's file
        $bindUtils = new BindUtils($buildIdA, false, $this->cacheDir);
        $reflection = new \ReflectionClass(BindUtils::class);
        $warmPathProp = $reflection->getProperty('warmCachePath');
        self::assertSame($this->cacheDir."/bind_utils_mappings_{$buildIdA}.php", $warmPathProp->getValue($bindUtils));
    }
}
