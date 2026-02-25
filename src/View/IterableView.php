<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ViewBundle\View;

use Symfony\Component\Serializer\Normalizer\NormalizableInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class IterableView extends View implements NormalizableInterface
{
    /**
     * @param iterable<mixed> $entries
     */
    public function __construct(public iterable $entries = [], callable|string|null $map = null)
    {
        if (\is_string($map)) {
            if (!\class_exists($map)) {
                throw new \RuntimeException(\sprintf('Mapping class %s does not exist', $map));
            }

            if (!\is_a($map, ViewInterface::class, true)) {
                throw new \RuntimeException(\sprintf('Mapping class %s must implement ViewInterface', $map));
            }

            $map = static fn (object $v) => new $map($v);
        }

        $entriesArray = \is_array($entries) ? $entries : \iterator_to_array($entries);
        $this->entries = \array_values(\array_map($map ?? [static::class, 'map'], $entriesArray));
    }

    /**
     * @param array<mixed>|object $value
     */
    protected static function map(array|object $value): ViewInterface
    {
        $valueType = \is_object($value) ? $value::class : \gettype($value);
        throw new \RuntimeException(\sprintf('%s should be defined or mapping closure should be passed for value of type %s', __METHOD__, $valueType));
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<mixed>|string|int|float|bool|\ArrayObject<int|string, mixed>|null
     */
    public function normalize(NormalizerInterface $normalizer, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        return $normalizer->normalize($this->entries);
    }
}
