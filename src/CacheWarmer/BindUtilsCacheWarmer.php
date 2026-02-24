<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ViewBundle\CacheWarmer;

use ChamberOrchestra\ViewBundle\Attribute\BindsFrom;
use ChamberOrchestra\ViewBundle\PropertyAccessor\ReflectionService;
use ChamberOrchestra\ViewBundle\Utils\BindUtils;
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
            foreach ($this->getSourceClasses($targetClass) as $sourceClass) {
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
     * Returns the source classes to pair with a target class.
     * If the target has #[BindsFrom] attributes, only declared sources are used.
     * Otherwise, falls back to all view classes (backward-compatible N² behavior).
     *
     * @param class-string $targetClass
     *
     * @return array<class-string>
     */
    private function getSourceClasses(string $targetClass): array
    {
        $reflection = new \ReflectionClass($targetClass);
        $attributes = $reflection->getAttributes(BindsFrom::class);

        if ([] === $attributes) {
            return $this->viewClasses;
        }

        /** @var list<class-string> $sourceClasses */
        $sourceClasses = \array_map(
            fn (\ReflectionAttribute $attr) => $attr->newInstance()->class,
            $attributes
        );

        return $sourceClasses;
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

            if (!BindUtils::isReflectionTypeValidForInitialization($targetType, $sourceType)) {
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
}
