<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ViewBundle\CacheWarmer;

use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\VarExporter\VarExporter;
use ChamberOrchestra\ViewBundle\PropertyAccessor\ReflectionService;

final readonly class BindUtilsCacheWarmer implements CacheWarmerInterface
{
    /**
     * @param array<class-string> $viewClasses
     */
    public function __construct(
        private array $viewClasses,
    ) {
    }

    public function isOptional(): bool
    {
        return true;  // Optional since it only optimizes View-to-View mappings
    }

    /**
     * @return list<string>
     */
    public function warmUp(string $cacheDir, ?string $buildId = null): array
    {
        $reflectionService = new ReflectionService();
        $mappings = [];

        // Pre-compute View-to-View property mappings
        foreach ($this->viewClasses as $targetClass) {
            foreach ($this->viewClasses as $sourceClass) {
                $cacheKey = $targetClass . '@' . $sourceClass;

                try {
                    $mappings[$cacheKey] = $this->computeIntersection(
                        $reflectionService,
                        $targetClass,
                        $sourceClass
                    );
                } catch (\Throwable) {
                    // Skip mappings that can't be computed
                    continue;
                }
            }
        }

        // Generate optimized PHP file
        $code = "<?php\n\nreturn " . VarExporter::export($mappings) . ";\n";
        $path = $cacheDir . '/bind_utils_mappings.php';

        if (!\is_dir($cacheDir)) {
            \mkdir($cacheDir, 0777, true);
        }

        \file_put_contents($path, $code);

        return [$path];
    }

    /**
     * Simplified version of BindUtils::getIntersectedProperties() for cache warming
     *
     * @return array<string, array{0: array, 1: array}>
     */
    private function computeIntersection(
        ReflectionService $reflectionService,
        string $targetClassName,
        string $sourceClassName
    ): array {
        $targetProperties = $reflectionService->getReflectionProperties($targetClassName);
        $sourceProperties = $reflectionService->getReflectionProperties($sourceClassName);

        $intersection = [];
        $commonProperties = \array_intersect_key($targetProperties, $sourceProperties);

        foreach ($commonProperties as $propertyName => $targetProperty) {
            $sourceProperty = $sourceProperties[$propertyName];

            $targetType = $targetProperty->getType();
            $sourceType = $sourceProperty->getType();

            if ($targetType === null || $sourceType === null) {
                continue;
            }

            if (!$this->isReflectionTypeValidForInitialization($targetType, $sourceType)) {
                continue;
            }

            // Store serialized reflection data
            $intersection[$propertyName] = [
                ['class' => $targetProperty->getDeclaringClass()->getName(), 'name' => $propertyName],
                ['class' => $sourceProperty->getDeclaringClass()->getName(), 'name' => $propertyName],
            ];
        }

        return $intersection;
    }

    /**
     * Copied from BindUtils for cache warming
     */
    private function isReflectionTypeValidForInitialization(\ReflectionType $targetType, \ReflectionType $sourceType): bool
    {
        if (!$targetType instanceof \ReflectionNamedType) {
            return false;
        }

        $sourceTypes = $sourceType instanceof \ReflectionUnionType ? $sourceType->getTypes() : [$sourceType];

        if ($targetType->isBuiltin()) {
            foreach ($sourceTypes as $sourceType) {
                if ($sourceType->isBuiltin()) {
                    return true;
                }
            }
            return false;
        }

        // For View classes, we can't check ViewInterface/BindView/IterableView here
        // since we're in cache warming context, so allow all non-builtin matches
        foreach ($sourceTypes as $sourceType) {
            if (\is_a($targetType->getName(), $sourceType->getName(), true)) {
                return true;
            }
        }

        return false;
    }
}
