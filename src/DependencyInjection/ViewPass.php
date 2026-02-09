<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ViewBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

/**
 * Collects View classes and passes them to cache warmers.
 */
final class ViewPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $viewClasses = [];

        // Retrieve concrete services tagged with "chamber_orchestra.view" tag
        foreach ($container->getDefinitions() as $id => $definition) {
            if (!$definition->hasTag('chamber_orchestra.view')) {
                continue;
            }

            if (!$definition->hasTag('container.excluded')) {
                throw new InvalidArgumentException(\sprintf('The resource "%s" tagged "chamber_orchestra.view" is missing the "container.excluded" tag.', $id));
            }

            $class = $container->getParameterBag()->resolveValue($definition->getClass());
            if (!$class || $definition->isAbstract()) {
                throw new InvalidArgumentException(\sprintf('The resource "%s" tagged "chamber_orchestra.view" must have a class and not be abstract.', $id));
            }

            $viewClasses[] = $class;

            // Remove definition - Views are not actual services
            $container->removeDefinition($id);
        }

        // Pass collected View classes to cache warmers
        if ($container->hasDefinition('chamber_orchestra.view_bundle.cache_warmer.view_metadata')) {
            $container->getDefinition('chamber_orchestra.view_bundle.cache_warmer.view_metadata')
                ->setArgument('$viewClasses', $viewClasses);
        }

        if ($container->hasDefinition('chamber_orchestra.view_bundle.cache_warmer.bind_utils')) {
            $container->getDefinition('chamber_orchestra.view_bundle.cache_warmer.bind_utils')
                ->setArgument('$viewClasses', $viewClasses);
        }
    }
}
