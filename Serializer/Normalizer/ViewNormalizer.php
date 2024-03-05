<?php

declare(strict_types=1);

namespace Dev\ViewBundle\Serializer\Normalizer;

use Dev\ViewBundle\View\ViewInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class ViewNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    public function normalize(mixed $object, string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $data = [];
        foreach ((array)$object as $k => $v) {
            if (null === $v) {
                continue;
            }
            $data[$k] = $this->normalizer->normalize($v, $format, $context);
        }
        return $data;
    }

    public function getSupportedTypes(string|null $format): array
    {
        return [ViewInterface::class => true];
    }

    public function supportsNormalization(mixed $data, string $format = null, array $context = []): bool
    {
        return $data instanceof ViewInterface;
    }
}