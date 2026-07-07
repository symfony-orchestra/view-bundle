<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\DependencyInjection;

use ChamberOrchestra\ViewBundle\Cache\ViewResponseCache;
use ChamberOrchestra\ViewBundle\DependencyInjection\ChamberOrchestraViewExtension;
use ChamberOrchestra\ViewBundle\Utils\BindUtils;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Exception\InvalidTypeException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\Reference;

final class ChamberOrchestraViewExtensionTest extends TestCase
{
    public function testItLoadsServicesAndRegistersBuildIdParameter(): void
    {
        $container = new ContainerBuilder();
        $extension = new ChamberOrchestraViewExtension();
        $extension->load([], $container);

        $definition = $container->getDefinition(BindUtils::class);
        $argument = $definition->getArgument('$buildId');

        $this->assertInstanceOf(Parameter::class, $argument);
        $this->assertSame('container.build_id', (string) $argument);
    }

    public function testResponseCacheIsEnabledWithAppPoolAndOneDayTtlByDefault(): void
    {
        $container = new ContainerBuilder();
        new ChamberOrchestraViewExtension()->load([], $container);

        $definition = $container->getDefinition(ViewResponseCache::class);
        $pool = $definition->getArgument('$pool');

        $this->assertInstanceOf(Reference::class, $pool);
        $this->assertSame('cache.app', (string) $pool);
        $this->assertSame(ViewResponseCache::DEFAULT_TTL_SECONDS, $definition->getArgument('$defaultTtl'));
        $this->assertSame(86400, ViewResponseCache::DEFAULT_TTL_SECONDS, 'The default lifetime must be one day');
    }

    public function testResponseCacheCanBeDisabled(): void
    {
        $container = new ContainerBuilder();
        new ChamberOrchestraViewExtension()->load([
            ['response_cache' => ['enabled' => false]],
        ], $container);

        $definition = $container->getDefinition(ViewResponseCache::class);

        $this->assertNull($definition->getArgument('$pool'));
        $this->assertSame(ViewResponseCache::DEFAULT_TTL_SECONDS, $definition->getArgument('$defaultTtl'));
    }

    public function testNullDefaultTtlIsRejected(): void
    {
        $container = new ContainerBuilder();

        $this->expectException(InvalidTypeException::class);

        new ChamberOrchestraViewExtension()->load([
            ['response_cache' => ['default_ttl' => null]],
        ], $container);
    }

    public function testNonPositiveDefaultTtlIsRejected(): void
    {
        $container = new ContainerBuilder();

        $this->expectException(InvalidConfigurationException::class);

        new ChamberOrchestraViewExtension()->load([
            ['response_cache' => ['default_ttl' => 0]],
        ], $container);
    }

    public function testResponseCachePoolAndTtlAreConfigurable(): void
    {
        $container = new ContainerBuilder();
        new ChamberOrchestraViewExtension()->load([
            ['response_cache' => ['pool' => 'cache.views', 'default_ttl' => 600]],
        ], $container);

        $definition = $container->getDefinition(ViewResponseCache::class);
        $pool = $definition->getArgument('$pool');

        $this->assertInstanceOf(Reference::class, $pool);
        $this->assertSame('cache.views', (string) $pool);
        $this->assertSame(600, $definition->getArgument('$defaultTtl'));
    }
}
