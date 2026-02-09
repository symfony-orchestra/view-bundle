<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ChamberOrchestra\ViewBundle\CacheWarmer\BindUtilsCacheWarmer;
use ChamberOrchestra\ViewBundle\CacheWarmer\ViewMetadataCacheWarmer;
use ChamberOrchestra\ViewBundle\Serializer\Metadata\ViewMetadataFactory;
use ChamberOrchestra\ViewBundle\Utils\BindUtils;
use ChamberOrchestra\ViewBundle\View\BindView;
use ChamberOrchestra\ViewBundle\View\View;

echo "Cache Warmup Performance Test\n";
echo "==============================\n\n";

// Create test view classes
$testView1 = new class extends View {
    public string $name = 'Test';
    public int $count = 0;
    public ?string $optional = null;
};

$testView2 = new class((object)['name' => '', 'value' => 0]) extends BindView {
    public string $name = '';
    public int $value = 0;
};

$viewClasses = [$testView1::class, $testView2::class];

// Test WITHOUT cache warming
echo "1. Testing WITHOUT warmed cache:\n";
$tempDir = sys_get_temp_dir() . '/view_bundle_bench_' . uniqid();
mkdir($tempDir, 0777, true);

$factory = new ViewMetadataFactory(null); // No cache dir
$iterations = 1000;

$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $factory->getMetadata($testView1::class);
}
$timeWithoutCache = microtime(true) - $start;

echo "   Metadata lookups: {$iterations}\n";
echo "   Time: " . number_format($timeWithoutCache * 1000, 2) . " ms\n";
echo "   Avg per lookup: " . number_format(($timeWithoutCache / $iterations) * 1000000, 2) . " μs\n\n";

// Test WITH cache warming
echo "2. Testing WITH warmed cache:\n";

// Warm up the cache
$metadataWarmer = new ViewMetadataCacheWarmer(new ViewMetadataFactory(), $viewClasses);
$bindUtilsWarmer = new BindUtilsCacheWarmer($viewClasses);

echo "   Warming up caches...\n";
$metadataWarmer->warmUp($tempDir);
$bindUtilsWarmer->warmUp($tempDir);
echo "   ✓ Cache files generated\n\n";

// Test with warmed cache
$factoryWithCache = new ViewMetadataFactory($tempDir);
BindUtils::configure('bench-' . uniqid(), 0, 'bench', $tempDir);

$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $factoryWithCache->getMetadata($testView1::class);
}
$timeWithCache = microtime(true) - $start;

echo "   Metadata lookups: {$iterations}\n";
echo "   Time: " . number_format($timeWithCache * 1000, 2) . " ms\n";
echo "   Avg per lookup: " . number_format(($timeWithCache / $iterations) * 1000000, 2) . " μs\n\n";

// Calculate improvement
$improvement = (($timeWithoutCache - $timeWithCache) / $timeWithoutCache) * 100;
$speedup = $timeWithoutCache / $timeWithCache;

echo "3. Performance Improvement:\n";
echo "   Speedup: " . number_format($speedup, 2) . "x faster\n";
echo "   Improvement: " . number_format($improvement, 1) . "%\n";
echo "   Time saved: " . number_format(($timeWithoutCache - $timeWithCache) * 1000, 2) . " ms\n\n";

// Cleanup
array_map('unlink', glob($tempDir . '/*'));
rmdir($tempDir);

// Test BindUtils cache impact
echo "4. BindUtils Sync Performance:\n";

$source = (object)['name' => 'Test', 'value' => 123];
$iterations = 1000;

$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $view = new ($testView2::class)($source);
}
$bindTime = microtime(true) - $start;

echo "   BindView constructions: {$iterations}\n";
echo "   Time: " . number_format($bindTime * 1000, 2) . " ms\n";
echo "   Avg per sync: " . number_format(($bindTime / $iterations) * 1000000, 2) . " μs\n";

echo "\n✓ Benchmark complete!\n";
