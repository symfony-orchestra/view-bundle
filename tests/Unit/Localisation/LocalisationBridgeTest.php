<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Localisation;

use ChamberOrchestra\ViewBundle\Localisation\LocalisationBridge;
use ChamberOrchestra\ViewBundle\View\LocalisationAwareTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class LocalisationBridgeTest extends TestCase
{
    protected function setUp(): void
    {
        LocalisationBridge::setRequestStack(null);
    }

    protected function tearDown(): void
    {
        LocalisationBridge::setRequestStack(null);
    }

    public function testReturnsNullWithoutRequestStack(): void
    {
        self::assertNull(LocalisationBridge::getLocale());
    }

    public function testReturnsNullWithoutCurrentRequest(): void
    {
        LocalisationBridge::setRequestStack(new RequestStack());

        self::assertNull(LocalisationBridge::getLocale());
    }

    public function testExposesTheCurrentRequestLocale(): void
    {
        $request = new Request();
        $request->setLocale('de');

        $stack = new RequestStack();
        $stack->push($request);

        LocalisationBridge::setRequestStack($stack);

        self::assertSame('de', LocalisationBridge::getLocale());
    }

    public function testLocalisationAwareTraitDelegatesToTheSharedBridge(): void
    {
        $consumer = new class {
            use LocalisationAwareTrait;

            public function currentLocale(): ?string
            {
                return self::getLocale();
            }
        };

        self::assertNull($consumer->currentLocale());

        $request = new Request();
        $request->setLocale('fr');
        $stack = new RequestStack();
        $stack->push($request);
        LocalisationBridge::setRequestStack($stack);

        self::assertSame('fr', $consumer->currentLocale(), 'The trait must read the single shared bridge, not per-class state');
    }
}
