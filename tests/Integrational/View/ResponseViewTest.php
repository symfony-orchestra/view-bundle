<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Integrational\View;

use ChamberOrchestra\ViewBundle\View\ResponseView;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\SerializerInterface;

final class ResponseViewTest extends KernelTestCase
{
    public function testSerializeReturnsEmptyPayload(): void
    {
        static::bootKernel();
        $serializer = static::getContainer()->get(SerializerInterface::class);

        $result = $serializer->normalize(new ResponseView(), 'json');

        self::assertSame([], $result);
    }
}
