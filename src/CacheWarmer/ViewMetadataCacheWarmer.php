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
use ChamberOrchestra\ViewBundle\Serializer\Metadata\ViewMetadataFactory;

final readonly class ViewMetadataCacheWarmer implements CacheWarmerInterface
{
    /**
     * @param array<class-string> $viewClasses
     */
    public function __construct(
        private ViewMetadataFactory $metadataFactory,
        private array $viewClasses,
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
            foreach ($classMetadata->getProperties() as $property) {
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

        // Generate optimized PHP file
        $code = "<?php\n\nreturn " . VarExporter::export($metadata) . ";\n";
        $path = $cacheDir . '/view_metadata.php';

        if (!\is_dir($cacheDir)) {
            \mkdir($cacheDir, 0777, true);
        }

        \file_put_contents($path, $code);

        return [$path];
    }
}
