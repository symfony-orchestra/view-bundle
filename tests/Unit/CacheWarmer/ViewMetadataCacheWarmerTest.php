<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\CacheWarmer;

use ChamberOrchestra\ViewBundle\CacheWarmer\ViewMetadataCacheWarmer;
use ChamberOrchestra\ViewBundle\Serializer\Metadata\ViewMetadataFactory;
use ChamberOrchestra\ViewBundle\View\View;
use PHPUnit\Framework\TestCase;

final class ViewMetadataCacheWarmerTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = \sys_get_temp_dir().'/view_bundle_test_'.\uniqid();
        \mkdir($this->cacheDir, 0o777, true);
    }

    protected function tearDown(): void
    {
        if (\is_dir($this->cacheDir)) {
            \array_map('unlink', \glob($this->cacheDir.'/*'));
            \rmdir($this->cacheDir);
        }
    }

    public function testIsNotOptional(): void
    {
        $warmer = new ViewMetadataCacheWarmer(new ViewMetadataFactory(), [], '', 'test-build');
        self::assertFalse($warmer->isOptional());
    }

    public function testWarmUpGeneratesCacheFile(): void
    {
        $testViewClass = new class extends View {
            public string $foo = 'bar';
            public ?int $nullable = null;
        };

        $viewClasses = [$testViewClass::class];
        $factory = new ViewMetadataFactory();
        $warmer = new ViewMetadataCacheWarmer($factory, $viewClasses, '', 'test-build');

        $files = $warmer->warmUp($this->cacheDir);

        self::assertCount(1, $files);
        self::assertSame($this->cacheDir.'/view_metadata_test-build.php', $files[0]);
        self::assertFileExists($files[0]);
    }

    public function testGeneratedCacheContainsCorrectMetadata(): void
    {
        $testViewClass = new class extends View {
            public string $name = 'test';
            public ?string $optional = null;
            public int $count = 0;
        };

        $viewClasses = [$testViewClass::class];
        $factory = new ViewMetadataFactory();
        $warmer = new ViewMetadataCacheWarmer($factory, $viewClasses, '', 'test-build');

        $files = $warmer->warmUp($this->cacheDir);
        $cached = require $files[0];

        self::assertIsArray($cached);
        self::assertArrayHasKey($testViewClass::class, $cached);

        $metadata = $cached[$testViewClass::class];
        self::assertArrayHasKey('className', $metadata);
        self::assertArrayHasKey('properties', $metadata);
        self::assertSame($testViewClass::class, $metadata['className']);

        // Check properties
        $properties = $metadata['properties'];
        self::assertCount(3, $properties);

        $propNames = \array_column($properties, 'name');
        self::assertContains('name', $propNames);
        self::assertContains('optional', $propNames);
        self::assertContains('count', $propNames);

        // Check nullable flags
        foreach ($properties as $prop) {
            if ('optional' === $prop['name']) {
                self::assertTrue($prop['nullable']);
            } elseif ('name' === $prop['name'] || 'count' === $prop['name']) {
                self::assertFalse($prop['nullable']);
            }
        }
    }

    public function testHandlesEmptyViewClasses(): void
    {
        $factory = new ViewMetadataFactory();
        $warmer = new ViewMetadataCacheWarmer($factory, [], '', 'test-build');

        $files = $warmer->warmUp($this->cacheDir);

        self::assertCount(1, $files);
        self::assertFileExists($files[0]);

        $cached = require $files[0];
        self::assertSame([], $cached);
    }

    public function testHandlesMultipleViewClasses(): void
    {
        $view1 = new class extends View {
            public string $prop1 = 'a';
        };

        $view2 = new class extends View {
            public int $prop2 = 1;
        };

        $viewClasses = [$view1::class, $view2::class];
        $factory = new ViewMetadataFactory();
        $warmer = new ViewMetadataCacheWarmer($factory, $viewClasses, '', 'test-build');

        $files = $warmer->warmUp($this->cacheDir);
        $cached = require $files[0];

        self::assertCount(2, $cached);
        self::assertArrayHasKey($view1::class, $cached);
        self::assertArrayHasKey($view2::class, $cached);
    }
}
