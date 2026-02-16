<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Integrational\PropertyAccessor;

use ChamberOrchestra\ViewBundle\PropertyAccessor\ReflectionPropertyAccessor;
use ChamberOrchestra\ViewBundle\PropertyAccessor\ReflectionService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

class ReflectionPropertyAccessorTest extends KernelTestCase
{
    public function testSetValue(): void
    {
        self::bootKernel();

        $object = new \stdClass();
        $path = 'path';
        $value = 'value';

        $accessor = $this->createMock(PropertyAccessorInterface::class);
        $accessor->expects(self::once())->method('setValue')->with($object, $path, $value);

        $service = new ReflectionPropertyAccessor($accessor, new ReflectionService());
        $service->setValue($object, $path, $value);
    }
}
