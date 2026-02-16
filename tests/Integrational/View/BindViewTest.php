<?php

declare(strict_types=1);

namespace Tests\Integrational\View;

use ChamberOrchestra\ViewBundle\PropertyAccessor\ReflectionService;
use ChamberOrchestra\ViewBundle\Utils\BindUtils;
use ChamberOrchestra\ViewBundle\View\BindView;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\SerializerInterface;

final class BindViewTest extends KernelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetStaticState();
    }

    protected function tearDown(): void
    {
        $this->resetStaticState();
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
