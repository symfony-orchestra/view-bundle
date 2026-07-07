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
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('chamber_orchestra_view');

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('response_cache')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->info('Toggles JSON response caching for CachedView and CacheableViewInterface results')
                            ->defaultTrue()
                        ->end()
                        ->scalarNode('pool')
                            ->info('PSR-6 cache pool service id storing the serialized payloads')
                            ->cannotBeEmpty()
                            ->defaultValue('cache.app')
                        ->end()
                        ->integerNode('default_ttl')
                            ->info('Default cache lifetime in seconds; every entry expires — a non-expiring cache is not allowed')
                            ->min(1)
                            ->defaultValue(ViewResponseCache::DEFAULT_TTL_SECONDS)
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
