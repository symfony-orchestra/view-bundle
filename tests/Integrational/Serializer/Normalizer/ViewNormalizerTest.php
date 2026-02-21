<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Integrational\Serializer\Normalizer;

use ChamberOrchestra\ViewBundle\View\View;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\SerializerInterface;

final class ViewNormalizerTest extends KernelTestCase
{
    public function testNormalizesViewWithNestedNullFiltering(): void
    {
        static::bootKernel();
        $serializer = static::getContainer()->get(SerializerInterface::class);

        $view = new class extends View {
            public string $name = 'orchestra';
            public ?string $nullable = null;
            public object $child;

            public function __construct()
            {
                $this->child = (object) ['id' => 10, 'optional' => null];
            }
        };

        $result = $serializer->normalize($view, 'json');

        self::assertSame([
            'name' => 'orchestra',
            'child' => ['id' => 10, 'optional' => null],
        ], $result);
    }
}
