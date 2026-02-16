<?php

declare(strict_types=1);

namespace Tests\Unit\DependencyInjection;

use ChamberOrchestra\ViewBundle\DependencyInjection\ViewPass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

final class ViewPassTest extends TestCase
{
    public function testCollectsViewClassesAndPassesToCacheWarmers(): void
    {
        $container = new ContainerBuilder();

        // Register cache warmers
        $metadataWarmer = new Definition();
        $metadataWarmer->setArgument('$viewClasses', []);
        $container->setDefinition('chamber_orchestra.view_bundle.cache_warmer.view_metadata', $metadataWarmer);

        $bindUtilsWarmer = new Definition();
        $bindUtilsWarmer->setArgument('$viewClasses', []);
        $container->setDefinition('chamber_orchestra.view_bundle.cache_warmer.bind_utils', $bindUtilsWarmer);

        // Register View classes
        $view1 = new Definition('App\\View\\UserView');
        $view1->addTag('chamber_orchestra.view');
        $view1->addTag('container.excluded');
        $container->setDefinition('app.view.user', $view1);

        $view2 = new Definition('App\\View\\ProductView');
        $view2->addTag('chamber_orchestra.view');
        $view2->addTag('container.excluded');
        $container->setDefinition('app.view.product', $view2);

        $pass = new ViewPass();
        $pass->process($container);

        // Views should be removed
        self::assertFalse($container->hasDefinition('app.view.user'));
        self::assertFalse($container->hasDefinition('app.view.product'));

        // Class names should be passed to cache warmers
        $metadataClasses = $container->getDefinition('chamber_orchestra.view_bundle.cache_warmer.view_metadata')
            ->getArgument('$viewClasses');
        self::assertContains('App\\View\\UserView', $metadataClasses);
        self::assertContains('App\\View\\ProductView', $metadataClasses);

        $bindUtilsClasses = $container->getDefinition('chamber_orchestra.view_bundle.cache_warmer.bind_utils')
            ->getArgument('$viewClasses');
        self::assertContains('App\\View\\UserView', $bindUtilsClasses);
        self::assertContains('App\\View\\ProductView', $bindUtilsClasses);
    }

    public function testThrowsExceptionWhenMissingContainerExcludedTag(): void
    {
        $container = new ContainerBuilder();

        $view = new Definition('App\\View\\UserView');
        $view->addTag('chamber_orchestra.view');
        // Missing 'container.excluded' tag
        $container->setDefinition('app.view.user', $view);

        $pass = new ViewPass();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('missing the "container.excluded" tag');

        $pass->process($container);
    }

    public function testSkipsProcessingWhenNoViewsTagged(): void
    {
        $container = new ContainerBuilder();

        $metadataWarmer = new Definition();
        $metadataWarmer->setArgument('$viewClasses', []);
        $container->setDefinition('chamber_orchestra.view_bundle.cache_warmer.view_metadata', $metadataWarmer);

        $pass = new ViewPass();
        $pass->process($container);

        // Should set empty array
        $classes = $container->getDefinition('chamber_orchestra.view_bundle.cache_warmer.view_metadata')
            ->getArgument('$viewClasses');
        self::assertSame([], $classes);
    }

    public function testSkipsProcessingWhenCacheWarmersNotRegistered(): void
    {
        $container = new ContainerBuilder();

        $view = new Definition('App\\View\\UserView');
        $view->addTag('chamber_orchestra.view');
        $view->addTag('container.excluded');
        $container->setDefinition('app.view.user', $view);

        $pass = new ViewPass();
        $pass->process($container); // Should not throw

        // View should still be removed even without warmers
        self::assertFalse($container->hasDefinition('app.view.user'));
    }
}
