<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ViewBundle\EventSubscriber;

use ChamberOrchestra\ViewBundle\Utils\BindUtils;
use ChamberOrchestra\ViewBundle\View\BindView;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;

#[AsEventListener(RequestEvent::class, priority: 256)]
readonly class SetVersionSubscriber
{
    public function __construct(
        private BindUtils $bindUtils,
    ) {
    }

    public function __invoke(): void
    {
        BindView::setBindUtils($this->bindUtils);
    }
}
