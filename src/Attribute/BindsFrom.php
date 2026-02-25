<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ViewBundle\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final readonly class BindsFrom
{
    public function __construct(public string $class)
    {
        if (!\class_exists($class)) {
            throw new \InvalidArgumentException(\sprintf('Class "%s" does not exist', $class));
        }
    }
}
