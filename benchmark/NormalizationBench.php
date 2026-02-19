<?php

declare(strict_types=1);

namespace Benchmark;

use ChamberOrchestra\ViewBundle\Serializer\Metadata\ViewMetadataFactory;
use ChamberOrchestra\ViewBundle\Serializer\Normalizer\ViewNormalizer;
use ChamberOrchestra\ViewBundle\View\View;
use PhpBench\Attributes as Bench;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class NormalizationBench
{
    private Serializer $serializer;
    private object $simpleView;
    private object $complexView;

    public function __construct()
    {
        $viewNormalizer = new ViewNormalizer(new ViewMetadataFactory());

        $this->serializer = new Serializer(
            [$viewNormalizer, new DateTimeNormalizer(), new ObjectNormalizer()],
            [new JsonEncoder()],
        );

        $this->simpleView = new class extends View {
            public string $name = 'John Doe';
            public int $age = 30;
            public string $email = 'john@example.com';
        };

        $this->complexView = new class extends View {
            public string $id = 'user-123';
            public string $firstName = 'John';
            public string $lastName = 'Doe';
            public string $email = 'john@example.com';
            public int $age = 30;
            public ?string $phone = '555-1234';
            public ?string $address = '123 Main St';
            public ?string $city = 'Springfield';
            public ?string $state = 'IL';
            public ?string $zip = '62701';
        };
    }

    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(5)]
    #[Bench\Groups(['normalize'])]
    public function benchNormalizeSimpleView(): void
    {
        $this->serializer->normalize($this->simpleView, 'json');
    }

    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(5)]
    #[Bench\Groups(['normalize'])]
    public function benchNormalizeComplexView(): void
    {
        $this->serializer->normalize($this->complexView, 'json');
    }

    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(5)]
    #[Bench\Groups(['serialize'])]
    public function benchSerializeSimpleView(): void
    {
        $this->serializer->serialize($this->simpleView, 'json');
    }

    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(5)]
    #[Bench\Groups(['serialize'])]
    public function benchSerializeComplexView(): void
    {
        $this->serializer->serialize($this->complexView, 'json');
    }
}
