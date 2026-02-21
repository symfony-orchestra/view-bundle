<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Integrational\View;

use ChamberOrchestra\ViewBundle\View\KeyValueView;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\SerializerInterface;

final class KeyValueViewTest extends KernelTestCase
{
    public function testSerializeKeyValuePayload(): void
    {
        $view = new KeyValueView('meta', ['page' => 1]);
        static::bootKernel();
        $serializer = static::getContainer()->get(SerializerInterface::class);

        $result = $serializer->normalize($view, 'json');

        self::assertSame(['meta' => ['page' => 1]], $result);
    }
}
