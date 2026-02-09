<?php

declare(strict_types=1);

namespace Tests\Unit\Serializer\Normalizer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use ChamberOrchestra\ViewBundle\Serializer\Metadata\ViewMetadataFactory;
use ChamberOrchestra\ViewBundle\Serializer\Normalizer\ViewNormalizer;
use ChamberOrchestra\ViewBundle\View\View;
use ChamberOrchestra\ViewBundle\View\ViewInterface;

final class ViewNormalizerTest extends TestCase
{
    public function testSupportsOnlyViews(): void
    {
        $normalizer = new ViewNormalizer(new ViewMetadataFactory());

        $view = new class extends View {
        };

        self::assertTrue($normalizer->supportsNormalization($view));
        self::assertFalse($normalizer->supportsNormalization(['not-a-view']));
        self::assertSame([ViewInterface::class => true], $normalizer->getSupportedTypes('json'));
    }

    public function testNormalizeDelegatesAndSkipsNullValues(): void
    {
        $view = new class extends View {
            public string $foo = 'value';
            public ?string $bar = null;
            public object $baz;

            public function __construct()
            {
                $this->baz = (object)['nested' => 'thing'];
            }
        };

        $inner = $this->createMock(NormalizerInterface::class);
        $inner
            ->expects(self::exactly(2))
            ->method('normalize')
            ->willReturnCallback(function (mixed $value, ?string $format, array $context) use ($view) {
                self::assertSame('json', $format);
                self::assertSame(['k' => 'v'], $context);

                if ('value' === $value) {
                    return 'normalized_foo';
                }

                self::assertSame($view->baz, $value);
                return ['nested' => 'normalized'];
            });

        $normalizer = new ViewNormalizer(new ViewMetadataFactory());
        $normalizer->setNormalizer($inner);

        $result = $normalizer->normalize($view, 'json', ['k' => 'v']);

        self::assertSame(
            ['foo' => 'normalized_foo', 'baz' => ['nested' => 'normalized']],
            $result
        );
    }
}
