<?php

declare(strict_types=1);

namespace Dev\ViewBundle\EventSubscriber;

use Dev\ViewBundle\View\DataView;
use Dev\ViewBundle\View\ResponseView;
use Dev\ViewBundle\View\ViewInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\Serializer\SerializerInterface;

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