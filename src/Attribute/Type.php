<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ViewBundle\Attribute;

use ChamberOrchestra\ViewBundle\View\ViewInterface;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class Type
{
    public function __construct(public string $class)
    {
        if (!\class_exists($class)) {
            throw new \InvalidArgumentException(\sprintf('Class "%s" does not exist', $class));
        }

        if (!\is_a($class, ViewInterface::class, true)) {
            throw new \InvalidArgumentException(\sprintf('Class "%s" must implement ViewInterface', $class));
        }
    }
}
