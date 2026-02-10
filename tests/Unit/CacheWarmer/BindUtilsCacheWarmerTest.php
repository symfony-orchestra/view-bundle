<?php

declare(strict_types=1);

namespace Tests\Unit\CacheWarmer;

use PHPUnit\Framework\TestCase;
use ChamberOrchestra\ViewBundle\CacheWarmer\BindUtilsCacheWarmer;
use ChamberOrchestra\ViewBundle\View\BindView;

final class BindUtilsCacheWarmerTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = \sys_get_temp_dir() . '/view_bundle_test_' . \uniqid();
        \mkdir($this->cacheDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (\is_dir($this->cacheDir)) {
            \array_map('unlink', \glob($this->cacheDir . '/*'));
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
        $testView = new class((object)[]) extends BindView {
            public string $name = '';
            public int $age = 0;
        };

        $viewClasses = [$testView::class];
        $warmer = new BindUtilsCacheWarmer($viewClasses, '', 'test-build');

        $files = $warmer->warmUp($this->cacheDir);

        self::assertCount(1, $files);
        self::assertSame($this->cacheDir . '/bind_utils_mappings_test-build.php', $files[0]);
        self::assertFileExists($files[0]);
    }

    public function testGeneratedCacheContainsPropertyMappings(): void
    {
        $testView1 = new class((object)[]) extends BindView {
            public string $name = '';
            public int $id = 0;
        };

        $testView2 = new class((object)[]) extends BindView {
            public string $name = '';
            public string $email = '';
        };

        $viewClasses = [$testView1::class, $testView2::class];
        $warmer = new BindUtilsCacheWarmer($viewClasses, '', 'test-build');

        $files = $warmer->warmUp($this->cacheDir);
        $cached = require $files[0];

        self::assertIsArray($cached);

        // Should have mappings for View1->View2
        $key = $testView1::class . '@' . $testView2::class;
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
        $view1 = new class((object)[]) extends BindView {
            public string $shared = '';
        };

        $view2 = new class((object)[]) extends BindView {
            public string $shared = '';
        };

        $view3 = new class((object)[]) extends BindView {
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
                $key = $target . '@' . $source;
                self::assertArrayHasKey($key, $cached, "Missing mapping for $key");
            }
        }
    }
}
