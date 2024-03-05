<?php

declare(strict_types=1);

namespace Dev\ViewBundle\DependencyInjection;

use Dev\ViewBundle\EventSubscriber\SetVersionSubscriber;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Parameter;

class DevViewExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        (new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config')))->load('services.yaml');
        $this->registerViewCache($container);
    }

    private function registerViewCache(ContainerBuilder $container): void
    {
        $container->getDefinition(SetVersionSubscriber::class)->setArgument('$buildId', $param = new Parameter('container.build_id'));
    }
}
