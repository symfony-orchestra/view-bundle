<?php

declare(strict_types=1);

namespace Dev\ViewBundle\View;

use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class DataView extends ResponseView
{
    public function __construct(public readonly ViewInterface|array $data)
    {
    }

    public function normalize(NormalizerInterface $normalizer, string $format = null, array $context = []): array|string|int|float|bool
    {
        return $normalizer->normalize(['data' => $this->data], $format, $context);
    }
}