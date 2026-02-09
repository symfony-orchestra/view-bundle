<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ViewBundle\Serializer\Metadata;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class ViewMetadataFactory
{
    /**
     * @var array<class-string, ViewClassMetadata>
     */
    private array $cache = [];

    /**
     * @var array<class-string, array>|null
     */
    private static ?array $warmedCache = null;

    private readonly ?string $warmCachePath;

    public function __construct(
        #[Autowire('%kernel.cache_dir%')]
        ?string $cacheDir = null,
    ) {
        $this->warmCachePath = $cacheDir ? $cacheDir . '/view_metadata.php' : null;
    }

    /**
     * @param class-string $className
     */
    public function getMetadata(string $className): ViewClassMetadata
    {
        return $this->cache[$className] ??= $this->load($className);
    }

    /**
     * @param class-string $className
     */
    private function load(string $className): ViewClassMetadata
    {
        // Try warmed cache first
        if ($warmed = $this->loadFromWarmedCache($className)) {
            return $warmed;
        }

        // Fall back to reflection
        $reflection = new \ReflectionClass($className);
        $properties = [];

        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            // Skip static properties
            if ($property->isStatic()) {
                continue;
            }

            $type = $property->getType();
            $properties[] = new ViewPropertyMetadata(
                name: $property->getName(),
                type: $type,
                nullable: $type?->allowsNull() ?? true,
                hasDefaultValue: $property->hasDefaultValue(),
            );
        }

        return new ViewClassMetadata($className, $properties);
    }

    /**
     * @param class-string $className
     */
    private function loadFromWarmedCache(string $className): ?ViewClassMetadata
    {
        if ($this->warmCachePath === null || !\file_exists($this->warmCachePath)) {
            return null;
        }

        // Load warmed cache file once
        if (self::$warmedCache === null) {
            self::$warmedCache = require $this->warmCachePath;
        }

        if (!isset(self::$warmedCache[$className])) {
            return null;
        }

        $data = self::$warmedCache[$className];
        $properties = [];

        foreach ($data['properties'] as $propData) {
            $properties[] = new ViewPropertyMetadata(
                name: $propData['name'],
                type: null,  // Type info not needed for normalization
                nullable: $propData['nullable'],
                hasDefaultValue: $propData['hasDefaultValue'],
            );
        }

        return new ViewClassMetadata($data['className'], $properties);
    }
}
