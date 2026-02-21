<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\View;

use ChamberOrchestra\ViewBundle\View\View;
use ChamberOrchestra\ViewBundle\View\ViewInterface;
use PHPUnit\Framework\TestCase;

final class ViewTest extends TestCase
{
    public function testAbstractViewImplementsInterface(): void
    {
        $view = new class extends View {
        };

        self::assertInstanceOf(ViewInterface::class, $view);
    }
}
