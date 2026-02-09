<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ChamberOrchestra\ViewBundle\Serializer\Metadata\ViewMetadataFactory;
use ChamberOrchestra\ViewBundle\Serializer\Normalizer\ViewNormalizer;
use ChamberOrchestra\ViewBundle\View\View;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

// Create test view
$testView = new class extends View {
    public string $id = 'user-123';
    public string $name = 'John Doe';
    public int $age = 30;
    public string $email = 'john@example.com';
    public ?string $phone = '555-1234';
    public ?string $address = null;
};

$viewNormalizer = new ViewNormalizer(new ViewMetadataFactory());
$serializer = new Serializer([
    $viewNormalizer,
    new DateTimeNormalizer(),
    new ObjectNormalizer(),
]);

// Warm up
for ($i = 0; $i < 100; $i++) {
    $serializer->normalize($testView, 'json');
}

// Benchmark
$iterations = 10000;
$start = microtime(true);

for ($i = 0; $i < $iterations; $i++) {
    $serializer->normalize($testView, 'json');
}

$end = microtime(true);
$totalTime = $end - $start;
$avgTime = ($totalTime / $iterations) * 1000; // Convert to milliseconds

echo "Performance Test Results:\n";
echo "========================\n";
echo "Total iterations: " . number_format($iterations) . "\n";
echo "Total time: " . number_format($totalTime, 4) . " seconds\n";
echo "Average time per normalization: " . number_format($avgTime, 4) . " ms\n";
echo "Operations per second: " . number_format($iterations / $totalTime, 2) . "\n";
