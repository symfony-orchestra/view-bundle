<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ViewBundle\EventSubscriber;

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

    public function __construct(
        private SerializerInterface $serializer,
    ) {
    }

    public function __invoke(ViewEvent $event): void
    {
        if (!($view = $event->getControllerResult()) instanceof ViewInterface) {
            return;
        }

        $view = $view instanceof ResponseViewInterface ? $view : new DataView($view);

        $json = $this->serializer->serialize(
            $view,
            self::SERIALIZATION_FORMAT,
            ['json_encode_options' => JsonResponse::DEFAULT_ENCODING_OPTIONS]
        );

        $event->setResponse(new JsonResponse($json, $view->getStatus(), $view->getHeaders(), true));
    }
}