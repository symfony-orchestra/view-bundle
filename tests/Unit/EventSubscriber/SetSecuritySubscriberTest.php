<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\EventSubscriber;

use ChamberOrchestra\ViewBundle\EventSubscriber\SetSecuritySubscriber;
use ChamberOrchestra\ViewBundle\Security\SecurityBridge;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class SetSecuritySubscriberTest extends TestCase
{
    protected function setUp(): void
    {
        SecurityBridge::setTokenStorage(null);
    }

    protected function tearDown(): void
    {
        SecurityBridge::setTokenStorage(null);
    }

    public function testItSetsTheTokenStorageOnTheBridge(): void
    {
        $storage = new TokenStorage();
        $storage->setToken(new UsernamePasswordToken(new InMemoryUser('alice', null), 'main'));

        $subscriber = new SetSecuritySubscriber($storage);
        $subscriber();

        self::assertSame('alice', SecurityBridge::getUserIdentifier());
    }

    public function testItWorksWithoutSecurity(): void
    {
        $subscriber = new SetSecuritySubscriber();
        $subscriber();

        self::assertNull(SecurityBridge::getUser());
    }
}
