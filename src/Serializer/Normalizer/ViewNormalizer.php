<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ViewBundle\Serializer\Normalizer;

use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use ChamberOrchestra\ViewBundle\Serializer\Metadata\ViewMetadataFactory;
use ChamberOrchestra\ViewBundle\View\ViewInterface;

class ViewNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    public function __construct(
        private readonly ViewMetadataFactory $metadataFactory,
    ) {
    }

    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $metadata = $this->metadataFactory->getMetadata($data::class);
        $collection = [];

        foreach ($metadata->getProperties() as $property) {
            $value = $data->{$property->name};

            // Skip null values efficiently (no reflection needed)
            if ($value === null) {
                continue;
            }

            $collection[$property->name] = $this->normalizer->normalize($value, $format, $context);
        }

        return $collection;
    }

    public function getSupportedTypes(string|null $format): array
    {
        return [ViewInterface::class => true];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof ViewInterface;
    }
}