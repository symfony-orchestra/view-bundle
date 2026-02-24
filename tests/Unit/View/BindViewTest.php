<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\View;

use ChamberOrchestra\ViewBundle\View\BindView;
use PHPUnit\Framework\TestCase;

final class BindViewTest extends TestCase
{
    protected function setUp(): void
    {
        BindView::setBindUtils(null);
    }

    protected function tearDown(): void
    {
        BindView::setBindUtils(null);
    }

    public function testItMapsPropertiesFromSource(): void
    {
        $source = new class {
            public string $name = 'orchestra';
            public int $count = 5;
        };

        $view = new class($source) extends BindView {
            public string $name;
            public ?int $count = null;
        };

        self::assertSame('orchestra', $view->name);
        self::assertSame(5, $view->count);
    }
}
