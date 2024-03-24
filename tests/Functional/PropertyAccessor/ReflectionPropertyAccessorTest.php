<?php
declare(strict_types=1);

/*
 * This file is part of the SymfonyOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Functional\PropertyAccessor;

use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use SymfonyOrchestra\ViewBundle\PropertyAccessor\ReflectionPropertyAccessor;
use SymfonyOrchestra\ViewBundle\PropertyAccessor\ReflectionService;

class ReflectionPropertyAccessorTest extends TestCase
{
    public function testSetValue(): void
    {
        $object = new \stdClass();
        $path = 'path';
        $value = 'value';

        $accessor = $this->createMock(PropertyAccessorInterface::class);
        $accessor->expects(self::once())->method('setValue')->with($object, $path, $value);

        $service = new ReflectionPropertyAccessor($accessor, new ReflectionService());
        $service->setValue($object, $path, $value);
    }
}