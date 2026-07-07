<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ViewBundle\EventSubscriber;

use ChamberOrchestra\ViewBundle\Localisation\LocalisationBridge;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Injects the DI-managed request stack into the LocalisationBridge static bridge
 * on every request, mirroring SetVersionSubscriber and SetSecuritySubscriber.
 */
#[AsEventListener(RequestEvent::class, priority: 256)]
readonly class SetLocalisationSubscriber
{
    public function __construct(
        private ?RequestStack $requestStack = null,
    ) {
    }

    public function __invoke(): void
    {
        LocalisationBridge::setRequestStack($this->requestStack);
    }
}
