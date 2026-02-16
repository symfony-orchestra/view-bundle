<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ViewBundle\CacheWarmer;

use ChamberOrchestra\ViewBundle\PropertyAccessor\ReflectionService;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\VarExporter\VarExporter;

final readonly class BindUtilsCacheWarmer implements CacheWarmerInterface
{
    /**
     * @param array<class-string> $viewClasses
     */
    public function __construct(
        private array $viewClasses,
        private string $shareDir = '',
        private string $buildId = '',
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
                $cacheKey = $targetClass.'@'.$sourceClass;

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

        // Generate optimized PHP file in the share directory, versioned by build ID
        $outputDir = '' !== $this->shareDir ? $this->shareDir : $cacheDir;
        $filename = '' !== $this->buildId ? "bind_utils_mappings_{$this->buildId}.php" : 'bind_utils_mappings.php';
        $code = "<?php\n\nreturn ".VarExporter::export($mappings).";\n";
        $path = $outputDir.'/'.$filename;

        if (!\is_dir($outputDir)) {
            \mkdir($outputDir, 0777, true);
        }

        \file_put_contents($path, $code);

        return [$path];
    }

    /**
     * Simplified version of BindUtils::getIntersectedProperties() for cache warming.
     *
     * @param class-string $targetClassName
     * @param class-string $sourceClassName
     *
     * @return array<string, array{0: array{class: class-string, name: string}, 1: array{class: class-string, name: string}}>
     */
    private function computeIntersection(
        ReflectionService $reflectionService,
        string $targetClassName,
        string $sourceClassName,
    ): array {
        $targetProperties = $reflectionService->getReflectionProperties($targetClassName);
        $sourceProperties = $reflectionService->getReflectionProperties($sourceClassName);

        $intersection = [];
        $commonProperties = \array_intersect_key($targetProperties, $sourceProperties);

        foreach ($commonProperties as $propertyName => $targetProperty) {
            $sourceProperty = $sourceProperties[$propertyName];

            $targetType = $targetProperty->getType();
            $sourceType = $sourceProperty->getType();

            if (null === $targetType || null === $sourceType) {
                continue;
            }

            if (!$this->isReflectionTypeValidForInitialization($targetType, $sourceType)) {
                continue;
            }

            // Store serialized reflection data
            /** @var class-string $targetClass */
            $targetClass = $targetProperty->getDeclaringClass()->getName();
            /** @var class-string $sourceClass */
            $sourceClass = $sourceProperty->getDeclaringClass()->getName();
            $intersection[$propertyName] = [
                ['class' => $targetClass, 'name' => $propertyName],
                ['class' => $sourceClass, 'name' => $propertyName],
            ];
        }

        return $intersection;
    }

    /**
     * Copied from BindUtils for cache warming.
     */
    private function isReflectionTypeValidForInitialization(\ReflectionType $targetType, \ReflectionType $sourceType): bool
    {
        if (!$targetType instanceof \ReflectionNamedType) {
            return false;
        }

        $sourceTypes = $sourceType instanceof \ReflectionUnionType ? $sourceType->getTypes() : [$sourceType];

        if ($targetType->isBuiltin()) {
            foreach ($sourceTypes as $st) {
                if ($st instanceof \ReflectionNamedType && $st->isBuiltin()) {
                    return true;
                }
            }

            return false;
        }

        // For View classes, we can't check ViewInterface/BindView/IterableView here
        // since we're in cache warming context, so allow all non-builtin matches
        foreach ($sourceTypes as $st) {
            if ($st instanceof \ReflectionNamedType && \is_a($targetType->getName(), $st->getName(), true)) {
                return true;
            }
        }

        return false;
    }
}
