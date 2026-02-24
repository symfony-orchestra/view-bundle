<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Integrational\View;

use ChamberOrchestra\ViewBundle\View\BindView;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\SerializerInterface;

final class BindViewTest extends KernelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        BindView::setBindUtils(null);
    }

    protected function tearDown(): void
    {
        BindView::setBindUtils(null);
        static::ensureKernelShutdown();
    }

    public function testSerializerNormalizesBoundProperties(): void
    {
        static::bootKernel();
        $source = new class {
            public string $title = 'hello';
            public int $count = 3;
        };

        $view = new class($source) extends BindView {
            public string $title;
            public int $count;
        };

        $serializer = static::getContainer()->get(SerializerInterface::class);
        $result = $serializer->normalize($view, 'json');

        self::assertSame(['title' => 'hello', 'count' => 3], $result);
    }
}
