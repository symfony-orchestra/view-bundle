<?php
declare(strict_types=1);

/*
 * This file is part of the SymfonyOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SymfonyOrchestra\ViewBundle\View;

use Symfony\Component\Serializer\Normalizer\NormalizableInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class IterableView extends View implements NormalizableInterface
{
    public function __construct(public iterable $entries = [], callable|string|null $map = null)
    {
        if (\is_string($map)) {
            if (!\class_exists($map)) {
                throw new \RuntimeException(\sprintf('Mapping class %s does not exists', $map));
            }

            $map = static fn(object $v) => new $map($v);
        }
        $this->entries = \array_values(\array_map($map ?? [static::class, 'map'], \is_array($entries) ? $entries : \iterator_to_array($entries)));
    }

    protected static function map(array|object $value): ViewInterface
    {
        throw new \RuntimeException(\sprintf('%s should be defined or mapping closure should be passed for value %s', __METHOD__, \get_class($value)));
    }

    public function normalize(NormalizerInterface $normalizer, string $format = null, array $context = []): array|string|int|float|bool
    {
        return $normalizer->normalize($this->entries);
    }
}