<?php

declare(strict_types=1);

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
