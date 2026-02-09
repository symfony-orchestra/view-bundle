<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ViewBundle\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use ChamberOrchestra\ViewBundle\Utils\BindUtils;

#[AsEventListener(RequestEvent::class, priority: 256)]
readonly class SetVersionSubscriber
{
    private const int CACHE_LIFETIME_SECONDS = 86400; // 24 hours

    public function __construct(
        private string $buildId = '',
        #[Autowire('%env(bool:APP_DEBUG)%')]
        private bool $debug = false,
        #[Autowire('%kernel.share_dir%')]
        private ?string $shareDir = null,
    )
    {
    }

    public function __invoke(): void
    {
        BindUtils::configure(
            $this->buildId,
            $this->debug ? 0 : self::CACHE_LIFETIME_SECONDS,
            'view_bind',
            $this->shareDir
        );
    }
}