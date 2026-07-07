<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\View;

use ChamberOrchestra\ViewBundle\View\AutoCacheSignatureTrait;
use ChamberOrchestra\ViewBundle\View\CacheableViewInterface;
use ChamberOrchestra\ViewBundle\View\View;
use PHPUnit\Framework\TestCase;

final class AutoCacheSignatureTraitTest extends TestCase
{
    public function testSameValuesProduceTheSameSignature(): void
    {
        $first = new AutoSignedView(1, 'Alice');
        $second = new AutoSignedView(1, 'Alice');

        self::assertSame($first->getCacheSignature(), $second->getCacheSignature());
    }

    public function testDifferentValuesProduceDifferentSignatures(): void
    {
        $first = new AutoSignedView(1, 'Alice');
        $second = new AutoSignedView(1, 'Bob');

        self::assertNotSame($first->getCacheSignature(), $second->getCacheSignature());
    }

    public function testDifferentClassesWithSameValuesDoNotCollide(): void
    {
        $first = new AutoSignedView(1, 'Alice');
        $second = new OtherAutoSignedView(1, 'Alice');

        self::assertNotSame($first->getCacheSignature(), $second->getCacheSignature());
    }

    public function testNestedValuesAffectTheSignature(): void
    {
        $first = new AutoSignedView(1, 'Alice', ['roles' => ['admin']]);
        $second = new AutoSignedView(1, 'Alice', ['roles' => ['user']]);

        self::assertNotSame($first->getCacheSignature(), $second->getCacheSignature());
    }
}

class AutoSignedView extends View implements CacheableViewInterface
{
    use AutoCacheSignatureTrait;

    /**
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public int $id,
        public string $name,
        public array $meta = [],
    ) {
    }
}

final class OtherAutoSignedView extends AutoSignedView
{
}
