<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ViewBundle\DependencyInjection;

use ChamberOrchestra\ViewBundle\Cache\ViewResponseCache;
use ChamberOrchestra\ViewBundle\Serializer\Metadata\ViewMetadataFactory;
use ChamberOrchestra\ViewBundle\Utils\BindUtils;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\Reference;

class ChamberOrchestraViewExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        /** @var array{response_cache: array{enabled: bool, pool: string, default_ttl: int}} $config */
        $config = $this->processConfiguration(new Configuration(), $configs);

        new PhpFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'))->load('services.php');
        $this->registerViewCache($container);
        $this->configureResponseCache($container, $config['response_cache']);
    }

    /**
     * @param array{enabled: bool, pool: string, default_ttl: int} $config
     */
    private function configureResponseCache(ContainerBuilder $container, array $config): void
    {
        $definition = $container->getDefinition(ViewResponseCache::class);

        $definition->setArgument('$pool', $config['enabled'] ? new Reference($config['pool'], ContainerInterface::NULL_ON_INVALID_REFERENCE) : null);
        $definition->setArgument('$defaultTtl', $config['default_ttl']);
    }

    private function registerViewCache(ContainerBuilder $container): void
    {
        $buildId = new Parameter('container.build_id');

        $container->getDefinition(BindUtils::class)->setArgument('$buildId', $buildId);
        $container->getDefinition(ViewMetadataFactory::class)->setArgument('$buildId', $buildId);
        $container->getDefinition('chamber_orchestra.view_bundle.cache_warmer.view_metadata')->setArgument('$buildId', $buildId);
        $container->getDefinition('chamber_orchestra.view_bundle.cache_warmer.bind_utils')->setArgument('$buildId', $buildId);
    }
}
