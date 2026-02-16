<?php

declare(strict_types=1);

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
