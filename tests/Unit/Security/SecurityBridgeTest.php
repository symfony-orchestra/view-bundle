<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Security;

use ChamberOrchestra\ViewBundle\Security\SecurityBridge;
use ChamberOrchestra\ViewBundle\View\SecurityAwareTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class SecurityBridgeTest extends TestCase
{
    protected function setUp(): void
    {
        SecurityBridge::setTokenStorage(null);
    }

    protected function tearDown(): void
    {
        SecurityBridge::setTokenStorage(null);
    }

    public function testReturnsNullWithoutTokenStorage(): void
    {
        self::assertNull(SecurityBridge::getUser());
        self::assertNull(SecurityBridge::getUserIdentifier());
    }

    public function testReturnsNullWithoutToken(): void
    {
        SecurityBridge::setTokenStorage(new TokenStorage());

        self::assertNull(SecurityBridge::getUser());
        self::assertNull(SecurityBridge::getUserIdentifier());
    }

    public function testExposesTheAuthenticatedUser(): void
    {
        $user = new InMemoryUser('alice', null);
        $storage = new TokenStorage();
        $storage->setToken(new UsernamePasswordToken($user, 'main'));

        SecurityBridge::setTokenStorage($storage);

        self::assertSame($user, SecurityBridge::getUser());
        self::assertSame('alice', SecurityBridge::getUserIdentifier());
    }

    public function testSecurityAwareTraitDelegatesToTheSharedBridge(): void
    {
        $consumer = new class {
            use SecurityAwareTrait;

            public function currentIdentifier(): ?string
            {
                return self::getUserIdentifier();
            }
        };

        self::assertNull($consumer->currentIdentifier());

        $storage = new TokenStorage();
        $storage->setToken(new UsernamePasswordToken(new InMemoryUser('alice', null), 'main'));
        SecurityBridge::setTokenStorage($storage);

        self::assertSame('alice', $consumer->currentIdentifier(), 'The trait must read the single shared bridge, not per-class state');
    }
}
