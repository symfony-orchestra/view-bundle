<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\View;

use ChamberOrchestra\ViewBundle\PropertyAccessor\ReflectionService;
use ChamberOrchestra\ViewBundle\Utils\BindUtils;
use ChamberOrchestra\ViewBundle\View\BindView;
use PHPUnit\Framework\TestCase;

final class BindViewTest extends TestCase
{
    protected function setUp(): void
    {
        $this->resetStaticState();
    }

    protected function tearDown(): void
    {
        $this->resetStaticState();
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

    private function resetStaticState(): void
    {
        foreach ([
            'configured' => false,
            'cacheNamespace' => 'bind_view',
            'cacheLifetime' => 0,
            'version' => '',
            'storage' => [],
        ] as $prop => $value) {
            new \ReflectionProperty(BindUtils::class, $prop)->setValue(null, $value);
        }

        new \ReflectionProperty(ReflectionService::class, 'storage')->setValue(null, []);
    }
}
