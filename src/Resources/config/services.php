<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Serializer\Normalizer\CustomNormalizer;
use ChamberOrchestra\ViewBundle\Serializer\Normalizer\ViewNormalizer;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->public(false);

    $services->set(CustomNormalizer::class);
    $services->set(ViewNormalizer::class);

    $services
        ->load('ChamberOrchestra\\ViewBundle\\', '../../*')
        ->exclude([
            '../../Exception',
            '../../PropertyAccessor',
            '../../Resources',
            '../../Utils',
            '../../View',
        ]);

};
