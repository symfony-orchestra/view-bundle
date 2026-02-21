<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Integrational\View;

use ChamberOrchestra\ViewBundle\View\DataView;
use ChamberOrchestra\ViewBundle\View\View;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\SerializerInterface;

final class DataViewTest extends KernelTestCase
{
    public function testSerializeWrapsData(): void
    {
        $payload = new class extends View {
            public string $name = 'orchestra';
        };

        static::bootKernel();
        $serializer = static::getContainer()->get(SerializerInterface::class);

        $json = $serializer->serialize(new DataView($payload), 'json');

        self::assertJson($json);
        self::assertSame('{"data":{"name":"orchestra"}}', $json);
    }
}
