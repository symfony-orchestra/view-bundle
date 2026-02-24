<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\CacheWarmer;

use ChamberOrchestra\ViewBundle\Attribute\BindsFrom;
use ChamberOrchestra\ViewBundle\CacheWarmer\BindUtilsCacheWarmer;
use ChamberOrchestra\ViewBundle\View\BindView;
use PHPUnit\Framework\TestCase;

final class BindUtilsCacheWarmerTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = \sys_get_temp_dir().'/view_bundle_test_'.\uniqid();
        \mkdir($this->cacheDir, 0777, true);
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

    public function testIsOptional(): void
    {
        $warmer = new BindUtilsCacheWarmer([], '', 'test-build');
        self::assertTrue($warmer->isOptional());
    }

    public function testWarmUpGeneratesCacheFile(): void
    {
        $testView = new class((object) []) extends BindView {
            public string $name = '';
            public int $age = 0;
        };

        $viewClasses = [$testView::class];
        $warmer = new BindUtilsCacheWarmer($viewClasses, '', 'test-build');

        $files = $warmer->warmUp($this->cacheDir);

        self::assertCount(1, $files);
        self::assertSame($this->cacheDir.'/bind_utils_mappings_test-build.php', $files[0]);
        self::assertFileExists($files[0]);
    }

    public function testGeneratedCacheContainsPropertyMappings(): void
    {
        $testView1 = new class((object) []) extends BindView {
            public string $name = '';
            public int $id = 0;
        };

        $testView2 = new class((object) []) extends BindView {
            public string $name = '';
            public string $email = '';
        };

        $viewClasses = [$testView1::class, $testView2::class];
        $warmer = new BindUtilsCacheWarmer($viewClasses, '', 'test-build');

        $files = $warmer->warmUp($this->cacheDir);
        $cached = require $files[0];

        self::assertIsArray($cached);

        // Should have mappings for View1->View2
        $key = $testView1::class.'@'.$testView2::class;
        self::assertArrayHasKey($key, $cached);

        $mapping = $cached[$key];
        self::assertIsArray($mapping);

        // Both views have 'name' property - should be mapped
        self::assertArrayHasKey('name', $mapping);
        self::assertIsArray($mapping['name']);
        self::assertCount(2, $mapping['name']); // [targetData, sourceData]

        // 'id' and 'email' are not common - should not be mapped
        self::assertArrayNotHasKey('id', $mapping);
        self::assertArrayNotHasKey('email', $mapping);
    }

    public function testHandlesEmptyViewClasses(): void
    {
        $warmer = new BindUtilsCacheWarmer([], '', 'test-build');

        $files = $warmer->warmUp($this->cacheDir);

        self::assertCount(1, $files);
        self::assertFileExists($files[0]);

        $cached = require $files[0];
        self::assertSame([], $cached);
    }

    public function testPrecomputesAllViewToViewCombinations(): void
    {
        $view1 = new class((object) []) extends BindView {
            public string $shared = '';
        };

        $view2 = new class((object) []) extends BindView {
            public string $shared = '';
        };

        $view3 = new class((object) []) extends BindView {
            public string $shared = '';
        };

        $viewClasses = [$view1::class, $view2::class, $view3::class];
        $warmer = new BindUtilsCacheWarmer($viewClasses, '', 'test-build');

        $files = $warmer->warmUp($this->cacheDir);
        $cached = require $files[0];

        // Should have 3x3 = 9 mappings (including self-mappings)
        self::assertCount(9, $cached);

        // Check all combinations exist
        foreach ($viewClasses as $target) {
            foreach ($viewClasses as $source) {
                $key = $target.'@'.$source;
                self::assertArrayHasKey($key, $cached, "Missing mapping for $key");
            }
        }
    }

    public function testWarmUpUsesBindsFromAttributeWhenPresent(): void
    {
        $viewClasses = [CacheWarmerAnnotatedView::class, CacheWarmerUnannotatedView::class];
        $warmer = new BindUtilsCacheWarmer($viewClasses, '', 'test-build');

        $files = $warmer->warmUp($this->cacheDir);
        $cached = require $files[0];

        // Annotated view should only have mapping from declared source
        $annotatedFromSource = CacheWarmerAnnotatedView::class.'@'.CacheWarmerSourceEntity::class;
        self::assertArrayHasKey($annotatedFromSource, $cached);

        // Annotated view should NOT have mapping from unannotated view (not in BindsFrom)
        $annotatedFromUnannotated = CacheWarmerAnnotatedView::class.'@'.CacheWarmerUnannotatedView::class;
        self::assertArrayNotHasKey($annotatedFromUnannotated, $cached);
    }

    public function testMixedAnnotatedAndUnannotatedViews(): void
    {
        $viewClasses = [CacheWarmerAnnotatedView::class, CacheWarmerUnannotatedView::class];
        $warmer = new BindUtilsCacheWarmer($viewClasses, '', 'test-build');

        $files = $warmer->warmUp($this->cacheDir);
        $cached = require $files[0];

        // Unannotated view should have mappings from ALL view classes (N² fallback)
        $unannotatedFromAnnotated = CacheWarmerUnannotatedView::class.'@'.CacheWarmerAnnotatedView::class;
        $unannotatedFromUnannotated = CacheWarmerUnannotatedView::class.'@'.CacheWarmerUnannotatedView::class;
        self::assertArrayHasKey($unannotatedFromAnnotated, $cached);
        self::assertArrayHasKey($unannotatedFromUnannotated, $cached);

        // Annotated view should only map from its declared source
        $annotatedFromSource = CacheWarmerAnnotatedView::class.'@'.CacheWarmerSourceEntity::class;
        self::assertArrayHasKey($annotatedFromSource, $cached);

        // Count: annotated=1 (from source entity) + unannotated=2 (from all views)
        self::assertCount(3, $cached);
    }
}

class CacheWarmerSourceEntity
{
    public string $name = '';
}

#[BindsFrom(CacheWarmerSourceEntity::class)]
class CacheWarmerAnnotatedView extends BindView
{
    public string $name = '';

    public function __construct(object $object)
    {
        parent::__construct($object);
    }
}

class CacheWarmerUnannotatedView extends BindView
{
    public string $name = '';

    public function __construct(object $object)
    {
        parent::__construct($object);
    }
}
