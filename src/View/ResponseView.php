<?php
declare(strict_types=1);

/*
 * This file is part of the SymfonyOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SymfonyOrchestra\ViewBundle\View;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Normalizer\NormalizableInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class ResponseView extends View implements NormalizableInterface
{
    public function getStatus(): int
    {
        return Response::HTTP_OK;
    }

    public function getHeaders(): array
    {
        return ['Content-Type' => 'application/json'];
    }

    public function normalize(NormalizerInterface $normalizer, string $format = null, array $context = []): array|string|int|float|bool
    {
        return $normalizer->normalize([], $format, $context);
    }
}