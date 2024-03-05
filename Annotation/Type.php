<?php

declare(strict_types=1);

namespace Dev\ViewBundle\Annotation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Type
{
    public function __construct(public string $class)
    {
    }
}

