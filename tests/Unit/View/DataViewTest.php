<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\View;

use ChamberOrchestra\ViewBundle\View\DataView;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class DataViewTest extends TestCase
{
    public function testItWrapsDataUnderDataKey(): void
    {
        $payload = ['foo' => 'bar'];
        $view = new DataView($payload);

        $normalizer = $this->createMock(NormalizerInterface::class);
        $normalizer
            ->expects(self::once())
            ->method('normalize')
            ->with(['data' => $payload], 'json', ['ctx' => true])
            ->willReturn(['data' => $payload]);

        $result = $view->normalize($normalizer, 'json', ['ctx' => true]);

        self::assertSame(['data' => $payload], $result);
    }
}
