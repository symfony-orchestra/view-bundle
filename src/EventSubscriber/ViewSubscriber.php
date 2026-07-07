<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ViewBundle\EventSubscriber;

use ChamberOrchestra\ViewBundle\Cache\ViewResponseCache;
use ChamberOrchestra\ViewBundle\View\CacheableViewInterface;
use ChamberOrchestra\ViewBundle\View\CachedViewInterface;
use ChamberOrchestra\ViewBundle\View\DataView;
use ChamberOrchestra\ViewBundle\View\ResponseViewInterface;
use ChamberOrchestra\ViewBundle\View\ViewInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\Serializer\SerializerInterface;

#[AsEventListener(ViewEvent::class)]
readonly class ViewSubscriber
{
    private const SERIALIZATION_FORMAT = 'json';

    private ViewResponseCache $viewResponseCache;

    public function __construct(
        private SerializerInterface $serializer,
        ?ViewResponseCache $viewResponseCache = null,
    ) {
        $this->viewResponseCache = $viewResponseCache ?? new ViewResponseCache();
    }

    public function __invoke(ViewEvent $event): void
    {
        $result = $event->getControllerResult();

        if (!$result instanceof ViewInterface) {
            return;
        }

        $view = $this->wrap($result);

        $json = $result instanceof CacheableViewInterface
            ? $this->viewResponseCache->get(
                $result->getCacheSignature(),
                $result instanceof CachedViewInterface ? $result->getTtl() : null,
                fn (): string => $this->serialize($view),
            )
            : $this->serialize($view);

        $event->setResponse(new JsonResponse($json, $view->getStatus(), $view->getHeaders(), true));
    }

    private function wrap(ViewInterface $view): ViewInterface&ResponseViewInterface
    {
        return $view instanceof ResponseViewInterface ? $view : new DataView($view);
    }

    private function serialize(ViewInterface $view): string
    {
        return $this->serializer->serialize(
            $view,
            self::SERIALIZATION_FORMAT,
            ['json_encode_options' => JsonResponse::DEFAULT_ENCODING_OPTIONS]
        );
    }
}
