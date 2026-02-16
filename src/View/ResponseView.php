<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ViewBundle\View;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Normalizer\NormalizableInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class ResponseView extends View implements NormalizableInterface, ResponseViewInterface
{
    /**
     * @param array<string, mixed> $headers
     */
    public function __construct(
        protected readonly int $status = Response::HTTP_OK,
        protected readonly array $headers = ['Content-Type' => 'application/json'],
    ) {
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * @return array<string, mixed>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<mixed>|string|int|float|bool|\ArrayObject<int|string, mixed>|null
     */
    public function normalize(NormalizerInterface $normalizer, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        return $normalizer->normalize([], $format, $context);
    }
}
