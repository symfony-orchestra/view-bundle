<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ViewBundle\View;

use ChamberOrchestra\ViewBundle\Utils\BindUtils;

abstract class BindView extends \stdClass implements ViewInterface
{
    public function __construct(object $object)
    {
        BindUtils::instance()->sync($this, $object);
    }
}
