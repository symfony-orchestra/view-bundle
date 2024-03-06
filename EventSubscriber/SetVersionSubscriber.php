<?php

declare(strict_types=1);

/*
 * This file is part of the SymfonyOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SymfonyOrchestra\ViewBundle\EventSubscriber;

use SymfonyOrchestra\ViewBundle\Utils\BindUtils;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;

#[AsEventListener(RequestEvent::class, priority: 256)]
readonly class SetVersionSubscriber
{
    public function __construct(
        private string $buildId = '',
        #[Autowire('%env(bool:APP_DEBUG)%')]
        private bool $debug = false
    )
    {
    }

    public function __invoke(): void
    {
        BindUtils::configure($this->buildId, $this->debug ? 0 : 24 * 3600, 'view_bind');
    }
}