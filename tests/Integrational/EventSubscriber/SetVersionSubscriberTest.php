<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Integrational\EventSubscriber;

use ChamberOrchestra\ViewBundle\EventSubscriber\SetVersionSubscriber;
use ChamberOrchestra\ViewBundle\PropertyAccessor\ReflectionService;
use ChamberOrchestra\ViewBundle\Utils\BindUtils;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SetVersionSubscriberTest extends KernelTestCase
{
    protected function setUp(): void
    {
        $this->resetBindUtils();
    }

    protected function tearDown(): void
    {
        $this->resetBindUtils();
        static::ensureKernelShutdown();
    }

    public function testSubscriberConfiguresBindUtilsWithContainerBuildId(): void
    {
        $kernel = static::bootKernel();
        $container = static::getContainer();

        /** @var SetVersionSubscriber $subscriber */
        $subscriber = $container->get(SetVersionSubscriber::class);

        $subscriber();

        $buildId = $container->getParameter('container.build_id');

        self::assertSame($buildId, $this->getBindUtilsProperty('version'));
        self::assertSame($container->getParameter('kernel.debug') ? 0 : 24 * 3600, $this->getBindUtilsProperty('cacheLifetime'));
        self::assertSame('view_bind', $this->getBindUtilsProperty('cacheNamespace'));
        self::assertTrue($this->getBindUtilsProperty('configured'));
    }

    private function getBindUtilsProperty(string $property): mixed
    {
        return new \ReflectionProperty(BindUtils::class, $property)->getValue();
    }

    private function resetBindUtils(): void
    {
        foreach ([
            'configured' => false,
            'cacheNamespace' => 'bind_view',
            'cacheLifetime' => 0,
            'version' => '',
            'storage' => [],
        ] as $property => $value) {
            new \ReflectionProperty(BindUtils::class, $property)->setValue(null, $value);
        }

        new \ReflectionProperty(ReflectionService::class, 'storage')->setValue(null, []);
    }
}
