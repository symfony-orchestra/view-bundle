<?php

declare(strict_types=1);

namespace Dev\ViewBundle\View;

use Dev\ViewBundle\Utils\BindUtils;

abstract class BindView extends \stdClass implements ViewInterface
{
    public function __construct(object $object)
    {
        BindUtils::instance()->sync($this, $object);
    }
}