<?php

declare(strict_types=1);

/*
 * This file is part of the SymfonyOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SymfonyOrchestra\ViewBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Parameter;
use SymfonyOrchestra\ViewBundle\EventSubscriber\SetVersionSubscriber;

class SymfonyOrchestraViewExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        (new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config')))->load('services.yaml');
        $this->registerViewCache($container);
    }

    private function registerViewCache(ContainerBuilder $container): void
    {
        $container->getDefinition(SetVersionSubscriber::class)->setArgument('$buildId', new Parameter('container.build_id'));
    }
}
