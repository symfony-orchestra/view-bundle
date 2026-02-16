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
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class DataView extends ResponseView
{
    /**
     * @param ViewInterface|array<mixed> $data
     * @param array<string, mixed>       $headers
     */
    public function __construct(
        public readonly ViewInterface|array $data,
        int $status = Response::HTTP_OK,
        array $headers = ['Content-Type' => 'application/json'],
    ) {
        parent::__construct($status, $headers);
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<mixed>|string|int|float|bool|\ArrayObject<int|string, mixed>|null
     */
    public function normalize(NormalizerInterface $normalizer, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        return $normalizer->normalize(['data' => $this->data], $format, $context);
    }
}
