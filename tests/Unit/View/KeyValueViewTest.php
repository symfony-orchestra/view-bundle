<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\View;

use ChamberOrchestra\ViewBundle\View\KeyValueView;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class KeyValueViewTest extends TestCase
{
    public function testItReturnsKeyWithViewArray(): void
    {
        $payload = ['page' => 1];
        $view = new KeyValueView('meta', $payload);

        $normalizer = $this->createMock(NormalizerInterface::class);
        $normalizer
            ->expects(self::once())
            ->method('normalize')
            ->with($payload, null, [])
            ->willReturn($payload);

        $result = $view->normalize($normalizer);

        self::assertSame(['meta' => ['page' => 1]], $result);
    }
}
