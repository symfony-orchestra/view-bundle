<?php
declare(strict_types=1);

/*
 * This file is part of the SymfonyOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SymfonyOrchestra\ViewBundle\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class Type
{
    public function __construct(public string $class)
    {
    }
}

