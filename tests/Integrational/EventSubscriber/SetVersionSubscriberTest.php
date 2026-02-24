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
use ChamberOrchestra\ViewBundle\View\BindView;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SetVersionSubscriberTest extends KernelTestCase
{
    protected function setUp(): void
    {
        BindView::setBindUtils(null);
    }

    protected function tearDown(): void
    {
        BindView::setBindUtils(null);
        static::ensureKernelShutdown();
    }

    public function testSubscriberConfiguresBindViewWithBindUtils(): void
    {
        static::bootKernel();
        $container = static::getContainer();

        /** @var SetVersionSubscriber $subscriber */
        $subscriber = $container->get(SetVersionSubscriber::class);
        $subscriber();

        // Verify BindView works after subscriber invocation
        $source = new class {
            public string $name = 'integration-test';
        };

        $view = new class($source) extends BindView {
            public string $name;
        };

        self::assertSame('integration-test', $view->name);
    }
}
