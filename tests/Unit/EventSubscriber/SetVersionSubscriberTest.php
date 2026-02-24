<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\EventSubscriber;

use ChamberOrchestra\ViewBundle\EventSubscriber\SetVersionSubscriber;
use ChamberOrchestra\ViewBundle\Utils\BindUtils;
use ChamberOrchestra\ViewBundle\View\BindView;
use PHPUnit\Framework\TestCase;

final class SetVersionSubscriberTest extends TestCase
{
    protected function setUp(): void
    {
        BindView::setBindUtils(null);
    }

    protected function tearDown(): void
    {
        BindView::setBindUtils(null);
    }

    public function testItSetsBindUtilsOnBindView(): void
    {
        $bindUtils = new BindUtils('build-123');
        $subscriber = new SetVersionSubscriber($bindUtils);
        $subscriber();

        // Verify BindView received the BindUtils instance by using it
        $source = new class {
            public string $name = 'test';
        };

        $view = new class($source) extends BindView {
            public string $name;
        };

        self::assertSame('test', $view->name);
    }

    public function testItAcceptsBindUtilsViaConstructor(): void
    {
        $bindUtils = new BindUtils('build-456', true, '/tmp/share');
        $subscriber = new SetVersionSubscriber($bindUtils);

        // Just verifying construction works — no exception thrown
        self::assertInstanceOf(SetVersionSubscriber::class, $subscriber);
    }
}
