<?php

declare(strict_types=1);

namespace Dev\ViewBundle\View;

use Symfony\Component\Serializer\Normalizer\NormalizableInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class KeyValueView extends View implements NormalizableInterface
{
    public function __construct(
        private readonly string $key,
        private readonly array $view,
    )
    {
    }

    public function normalize(NormalizerInterface $normalizer, string $format = null, array $context = []): array
    {
        return [$this->key => $this->view];
    }
}