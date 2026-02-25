<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use ChamberOrchestra\ViewBundle\CacheWarmer\BindUtilsCacheWarmer;
use ChamberOrchestra\ViewBundle\CacheWarmer\ViewMetadataCacheWarmer;
use ChamberOrchestra\ViewBundle\Serializer\Normalizer\ViewNormalizer;
use ChamberOrchestra\ViewBundle\Utils\BindUtils;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Serializer\Normalizer\CustomNormalizer;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set(CustomNormalizer::class);
    $services->set(ViewNormalizer::class);

    // Register BindUtils as a DI service
    // Note: $buildId is set in ChamberOrchestraViewExtension (container.build_id is unavailable at load time)
    $services->set(BindUtils::class)
        ->arg('$debug', '%env(bool:APP_DEBUG)%')
        ->arg('$shareDir', '%kernel.share_dir%');

    // Configure cache warmers (ViewPass will inject View class names)
    // Note: $buildId is set in ChamberOrchestraViewExtension (container.build_id is unavailable at load time)
    $services->set('chamber_orchestra.view_bundle.cache_warmer.view_metadata', ViewMetadataCacheWarmer::class)
        ->autowire()
        ->arg('$viewClasses', []) // Populated by ViewPass
        ->arg('$shareDir', '%kernel.share_dir%');

    $services->set('chamber_orchestra.view_bundle.cache_warmer.bind_utils', BindUtilsCacheWarmer::class)
        ->arg('$viewClasses', []) // Populated by ViewPass
        ->arg('$shareDir', '%kernel.share_dir%');

    $services
        ->load('ChamberOrchestra\\ViewBundle\\', '../../*')
        ->exclude([
            '../../Attribute',
            '../../CacheWarmer',
            '../../DependencyInjection',
            '../../Exception',
            '../../PropertyAccessor',
            '../../Resources',
            '../../Serializer/Metadata/ViewClassMetadata.php',
            '../../Serializer/Metadata/ViewPropertyMetadata.php',
            '../../Utils',
            '../../View',
        ]);

    // Load View classes for discovery (tagged with 'chamber_orchestra.view' and 'container.excluded')
    // ViewPass will collect them and pass to cache warmers, then remove the definitions
    $services
        ->load('ChamberOrchestra\\ViewBundle\\View\\', '../../View/*')
        ->exclude([
            '../../View/ViewInterface.php',
            '../../View/ResponseViewInterface.php',
        ])
        ->tag('container.excluded')
        ->autowire(false)
        ->autoconfigure(true); // Applies #[AutoconfigureTag] from ViewInterface
};
