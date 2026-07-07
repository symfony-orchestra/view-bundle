<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\EventSubscriber;

use ChamberOrchestra\ViewBundle\EventSubscriber\SetLocalisationSubscriber;
use ChamberOrchestra\ViewBundle\Localisation\LocalisationBridge;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class SetLocalisationSubscriberTest extends TestCase
{
    protected function setUp(): void
    {
        LocalisationBridge::setRequestStack(null);
    }

    protected function tearDown(): void
    {
        LocalisationBridge::setRequestStack(null);
    }

    public function testItSetsTheRequestStackOnTheBridge(): void
    {
        $request = new Request();
        $request->setLocale('de');

        $stack = new RequestStack();
        $stack->push($request);

        $subscriber = new SetLocalisationSubscriber($stack);
        $subscriber();

        self::assertSame('de', LocalisationBridge::getLocale());
    }

    public function testItWorksWithoutRequestStack(): void
    {
        $subscriber = new SetLocalisationSubscriber();
        $subscriber();

        self::assertNull(LocalisationBridge::getLocale());
    }
}
