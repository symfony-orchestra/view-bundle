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