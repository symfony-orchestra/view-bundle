<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\DependencyInjection;

use ChamberOrchestra\ViewBundle\DependencyInjection\ChamberOrchestraViewExtension;
use ChamberOrchestra\ViewBundle\Utils\BindUtils;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Parameter;

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
}
