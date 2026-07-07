<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Utils;

use ChamberOrchestra\ViewBundle\Attribute\Type;
use ChamberOrchestra\ViewBundle\Utils\BindUtils;
use ChamberOrchestra\ViewBundle\View\BindView;
use ChamberOrchestra\ViewBundle\View\IterableView;
use PHPUnit\Framework\TestCase;

final class BindUtilsTest extends TestCase
{
    protected function setUp(): void
    {
        BindView::setBindUtils(null);
    }

    protected function tearDown(): void
    {
        BindView::setBindUtils(null);
    }

    public function testConstructorAcceptsConfigParameters(): void
    {
        $bindUtils = new BindUtils('build-123', true, '/tmp/share');

        // Verify the instance was created successfully by using it
        $source = new class {
            public string $name = 'test';
        };
        $target = new class {
            public ?string $name = null;
        };

        $bindUtils->sync($target, $source);

        self::assertSame('test', $target->name);
    }

    public function testSyncCopiesMissingValuesAndLeavesExistingOnes(): void
    {
        $bindUtils = new BindUtils();

        $source = new class {
            public string $name = 'Alice';
            public int $age = 30;
        };

        $target = new class {
            public ?string $name = null;
            public int $age = 5;
        };

        $bindUtils->sync($target, $source);

        self::assertSame('Alice', $target->name);
        self::assertSame(5, $target->age, 'Existing non-null value must not be overridden');
    }

    public function testSyncMapsViewAndIterableViewProperties(): void
    {
        $bindUtils = new BindUtils();
        BindView::setBindUtils($bindUtils);

        $child = new class {
            public string $id = 'child-id';
        };

        $source = new class($child) {
            public object $child;
            public array $children;

            public function __construct(object $child)
            {
                $this->child = $child;
                $this->children = [$child];
            }
        };

        $target = new class {
            public ?ChildView $child = null;
            #[Type(ChildView::class)]
            public IterableView $children;
        };

        $bindUtils->sync($target, $source);

        self::assertInstanceOf(ChildView::class, $target->child);
        self::assertSame($child, $target->child->source);

        self::assertInstanceOf(IterableView::class, $target->children);
        self::assertCount(1, $target->children->entries);
        self::assertInstanceOf(ChildView::class, $target->children->entries[0]);
        self::assertSame($child, $target->children->entries[0]->source);
    }

    public function testSyncSkipsIncompatibleOrUnsupportedTypes(): void
    {
        $bindUtils = new BindUtils();

        $source = new class {
            public object $incompatible;
            public string $union = 'value';

            public function __construct()
            {
                $this->incompatible = new SourceSubject();
            }
        };

        $target = new class {
            public ?TargetSubject $incompatible = null;
            public int|string|null $union = null;
        };

        $bindUtils->sync($target, $source);

        self::assertNull($target->incompatible);
        self::assertNull($target->union);
    }

    public function testSyncReadsPrivateSourcePropertiesThroughGetters(): void
    {
        $bindUtils = new BindUtils();

        $source = new class {
            private string $name = 'stored';

            public function getName(): string
            {
                return $this->name.'-via-getter';
            }
        };

        $target = new class {
            public ?string $name = null;
        };

        $bindUtils->sync($target, $source);

        self::assertSame('stored-via-getter', $target->name, 'Getters must take priority over direct property access');
    }

    public function testSyncReadsPrivateSourcePropertiesWithoutGetters(): void
    {
        $bindUtils = new BindUtils();

        $source = new class {
            private string $name = 'private-value'; // @phpstan-ignore property.unused
        };

        $target = new class {
            public ?string $name = null;
        };

        $bindUtils->sync($target, $source);

        self::assertSame('private-value', $target->name);
    }

    public function testSyncAllowsAutoConfigurableTargetTypes(): void
    {
        $bindUtils = new BindUtils();
        BindView::setBindUtils($bindUtils);

        $child = new class {
            public string $id = 'child-id';
        };

        $source = new class($child) {
            public object $child;

            public function __construct(object $child)
            {
                $this->child = $child;
            }
        };

        $target = new class {
            public ?ChildView $child = null;
        };

        $bindUtils->sync($target, $source);

        self::assertInstanceOf(ChildView::class, $target->child);
        self::assertSame($child, $target->child->source);
    }
}

final class ChildView extends BindView
{
    public function __construct(public object $source)
    {
        parent::__construct($source);
    }
}

final class SourceSubject
{
}

final class TargetSubject
{
}
