<?php

declare(strict_types=1);

/*
 * This file is part of the SymfonyOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SymfonyOrchestra\ViewBundle\EventSubscriber;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\Serializer\SerializerInterface;
use SymfonyOrchestra\ViewBundle\View\DataView;
use SymfonyOrchestra\ViewBundle\View\ResponseView;
use SymfonyOrchestra\ViewBundle\View\ViewInterface;

#[AsEventListener(ViewEvent::class)]
readonly class ViewSubscriber
{
    public function __construct(
        private SerializerInterface $serializer,
    )
    {
    }

    public function __invoke(ViewEvent $event): void
    {
        if (!($view = $event->getControllerResult()) instanceof ViewInterface) {
            return;
        }

        $json = $this->serializer->serialize(
            $view = $view instanceof ResponseView ? $view : new DataView($view),
            'json',
            ['json_encode_options' => JsonResponse::DEFAULT_ENCODING_OPTIONS]
        );

        $event->setResponse(new JsonResponse($json, $view->getStatus(), $view->getHeaders(), true));
    }
}