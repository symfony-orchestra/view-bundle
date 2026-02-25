<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ViewBundle\CacheWarmer;

use ChamberOrchestra\ViewBundle\Serializer\Metadata\ViewMetadataFactory;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\VarExporter\VarExporter;

final readonly class ViewMetadataCacheWarmer implements CacheWarmerInterface
{
    /**
     * @param array<class-string> $viewClasses
     */
    public function __construct(
        private ViewMetadataFactory $metadataFactory,
        private array $viewClasses,
        private string $shareDir = '',
        private string $buildId = '',
    ) {
    }

    public function isOptional(): bool
    {
        return false;
    }

    /**
     * @return list<string>
     */
    public function warmUp(string $cacheDir, ?string $buildId = null): array
    {
        $metadata = [];

        foreach ($this->viewClasses as $className) {
            $classMetadata = $this->metadataFactory->getMetadata($className);

            // Serialize metadata to array format for export (skip type info - not needed)
            $properties = [];
            foreach ($classMetadata->properties as $property) {
                $properties[] = [
                    'name' => $property->name,
                    'nullable' => $property->nullable,
                    'hasDefaultValue' => $property->hasDefaultValue,
                ];
            }

            $metadata[$className] = [
                'className' => $classMetadata->className,
                'properties' => $properties,
            ];
        }

        // Generate optimized PHP file in the share directory, versioned by build ID
        $outputDir = '' !== $this->shareDir ? $this->shareDir : $cacheDir;
        $filename = '' !== $this->buildId ? "view_metadata_{$this->buildId}.php" : 'view_metadata.php';
        $code = "<?php\n\nreturn ".VarExporter::export($metadata).";\n";
        $path = $outputDir.'/'.$filename;

        if (!\is_dir($outputDir)) {
            \mkdir($outputDir, 0o777, true);
        }

        \file_put_contents($path, $code);

        return [$path];
    }
}
