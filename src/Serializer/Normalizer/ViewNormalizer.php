<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ViewBundle\Serializer\Normalizer;

use ChamberOrchestra\ViewBundle\Cache\ViewResponseCache;
use ChamberOrchestra\ViewBundle\Serializer\Metadata\ViewMetadataFactory;
use ChamberOrchestra\ViewBundle\View\CachedBindView;
use ChamberOrchestra\ViewBundle\View\CachedViewInterface;
use ChamberOrchestra\ViewBundle\View\ViewInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class ViewNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private readonly ViewResponseCache $viewResponseCache;

    public function __construct(
        private readonly ViewMetadataFactory $metadataFactory,
        ?ViewResponseCache $viewResponseCache = null,
    ) {
        $this->viewResponseCache = $viewResponseCache ?? new ViewResponseCache();
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>|string|int|float|bool|\ArrayObject<string, mixed>|null
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        \assert($data instanceof ViewInterface);

        // Cached-view descriptors are not payloads: serve their normalized data from the
        // cache and only build + normalize the actual view when the entry misses
        if ($data instanceof CachedViewInterface) {
            /** @var array<string, mixed>|string|int|float|bool|\ArrayObject<string, mixed>|null $payload */
            $payload = $this->viewResponseCache->getNormalized(
                $data->getCacheSignature(),
                $data->getTtl(),
                fn (): mixed => $this->normalizer->normalize($data->createView(), $format, $context),
            );

            return $payload;
        }

        // Deferred binding: CachedBindView populates its properties only when actually serialized,
        // so a response served from the cache never pays for the binding
        if ($data instanceof CachedBindView) {
            $data->bind();
        }

        $metadata = $this->metadataFactory->getMetadata($data::class);
        $collection = [];

        foreach ($metadata->properties as $property) {
            $value = $data->{$property->name};

            // Skip null values efficiently (no reflection needed)
            if (null === $value) {
                continue;
            }

            $collection[$property->name] = $this->normalizer->normalize($value, $format, $context);
        }

        return $collection;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [ViewInterface::class => true];
    }

    /**
     * @param array<string, mixed> $context
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof ViewInterface;
    }
}
