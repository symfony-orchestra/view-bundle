<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ViewBundle\Serializer\Metadata;

final readonly class ViewPropertyMetadata
{
    public function __construct(
        public string $name,
        public ?\ReflectionType $type = null,
        public bool $nullable = true,
        public bool $hasDefaultValue = false,
    ) {
    }
}
