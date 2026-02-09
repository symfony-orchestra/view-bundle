<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ChamberOrchestra\ViewBundle\Serializer\Metadata\ViewMetadataFactory;
use ChamberOrchestra\ViewBundle\Serializer\Normalizer\ViewNormalizer;
use ChamberOrchestra\ViewBundle\View\View;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

echo "Memory Usage Test\n";
echo "=================\n\n";

// Create test view
$testView = new class extends View {
    public string $id = 'user-123';
    public string $name = 'John Doe';
    public int $age = 30;
    public string $email = 'john@example.com';
};

$viewNormalizer = new ViewNormalizer(new ViewMetadataFactory());
$serializer = new Serializer([$viewNormalizer, new ObjectNormalizer()]);

// Measure baseline memory
$memoryStart = memory_get_usage();
$memoryPeakStart = memory_get_peak_usage();

// Run normalizations
$iterations = 10000;
for ($i = 0; $i < $iterations; $i++) {
    $result = $serializer->normalize($testView, 'json');
    unset($result);
}

// Measure final memory
$memoryEnd = memory_get_usage();
$memoryPeakEnd = memory_get_peak_usage();

echo "Iterations: " . number_format($iterations) . "\n";
echo "Memory usage (start): " . number_format($memoryStart / 1024 / 1024, 2) . " MB\n";
echo "Memory usage (end): " . number_format($memoryEnd / 1024 / 1024, 2) . " MB\n";
echo "Memory delta: " . number_format(($memoryEnd - $memoryStart) / 1024, 2) . " KB\n";
echo "Peak memory: " . number_format($memoryPeakEnd / 1024 / 1024, 2) . " MB\n";
echo "Avg memory per operation: " . number_format(($memoryEnd - $memoryStart) / $iterations, 2) . " bytes\n";
