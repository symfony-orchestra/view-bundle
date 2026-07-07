<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\View;

use ChamberOrchestra\ViewBundle\Serializer\Metadata\ViewMetadataFactory;
use ChamberOrchestra\ViewBundle\Serializer\Normalizer\ViewNormalizer;
use ChamberOrchestra\ViewBundle\View\BindView;
use ChamberOrchestra\ViewBundle\View\CachedBindView;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

final class CachedBindViewTest extends TestCase
{
    protected function setUp(): void
    {
        BindView::setBindUtils(null);
    }

    protected function tearDown(): void
    {
        BindView::setBindUtils(null);
    }

    public function testConstructionDoesNotBind(): void
    {
        $source = new CachedBindSourceEntity();
        $view = new CachedBindUserView($source);

        self::assertSame(0, $source->getterCalls, 'The source must not be read before bind()');
        self::assertNull($view->name);
    }

    public function testCacheSignatureIsAvailableBeforeBinding(): void
    {
        $source = new CachedBindSourceEntity();
        $view = new CachedBindUserView($source);

        self::assertSame('user_7', $view->getCacheSignature());
        self::assertNull($view->name, 'Computing the signature must not trigger binding');
    }

    public function testBindSyncsOnceAndIsIdempotent(): void
    {
        $source = new CachedBindSourceEntity();
        $view = new CachedBindUserView($source);

        $view->bind();

        self::assertSame('Alice', $view->name);
        self::assertSame(1, $source->getterCalls);

        $view->bind();

        self::assertSame(1, $source->getterCalls, 'Repeated bind() calls must not re-sync');
    }

    public function testNormalizationTriggersBinding(): void
    {
        $serializer = new Serializer(
            [new ViewNormalizer(new ViewMetadataFactory()), new ObjectNormalizer()],
            [new JsonEncoder()],
        );

        $view = new CachedBindUserView(new CachedBindSourceEntity());

        self::assertSame('{"name":"Alice"}', $serializer->serialize($view, 'json'));
    }
}

final class CachedBindSourceEntity
{
    public int $getterCalls = 0;

    private string $name = 'Alice';

    public function getId(): int
    {
        return 7;
    }

    public function getName(): string
    {
        ++$this->getterCalls;

        return $this->name;
    }
}

final class CachedBindUserView extends CachedBindView
{
    public ?string $name = null;

    public static function createCacheSignature(object $source): string
    {
        \assert($source instanceof CachedBindSourceEntity);

        return 'user_'.$source->getId();
    }
}
