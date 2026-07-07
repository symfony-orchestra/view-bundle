<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ViewBundle\EventSubscriber;

use ChamberOrchestra\ViewBundle\Security\SecurityBridge;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Injects the DI-managed token storage into the SecurityBridge static bridge on
 * every request, mirroring SetVersionSubscriber for BindUtils. The token storage
 * is optional: without symfony/security the bridge stays empty and views see
 * an anonymous user.
 */
#[AsEventListener(RequestEvent::class, priority: 256)]
readonly class SetSecuritySubscriber
{
    public function __construct(
        private ?TokenStorageInterface $tokenStorage = null,
    ) {
    }

    public function __invoke(): void
    {
        SecurityBridge::setTokenStorage($this->tokenStorage);
    }
}
