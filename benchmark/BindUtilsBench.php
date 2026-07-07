<?php

declare(strict_types=1);

namespace Benchmark;

use ChamberOrchestra\ViewBundle\Utils\BindUtils;
use ChamberOrchestra\ViewBundle\View\BindView;
use PhpBench\Attributes as Bench;

class BindUtilsBench
{
    private BindPublicPropsEntity $publicSource;
    private BindGetterEntity $getterSource;
    private BindPrivateEntity $privateSource;

    public function __construct()
    {
        BindView::setBindUtils(new BindUtils('bench-build-id'));

        $this->publicSource = new BindPublicPropsEntity();
        $this->getterSource = new BindGetterEntity();
        $this->privateSource = new BindPrivateEntity();
    }

    /**
     * Binds 5 properties from a source with public properties.
     */
    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(5)]
    public function benchBindPublicSource(): void
    {
        new BindBenchUserView($this->publicSource);
    }

    /**
     * Binds 5 properties from a source with private properties and getters (Doctrine entity style).
     */
    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(5)]
    public function benchBindGetterSource(): void
    {
        new BindBenchUserView($this->getterSource);
    }

    /**
     * Binds 5 properties from a source with private properties and no getters (reflection read path).
     */
    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(5)]
    public function benchBindPrivateSource(): void
    {
        new BindBenchUserView($this->privateSource);
    }

    /**
     * Target properties already hold non-null values, so every property is skipped.
     * Measures the pure per-property skip-check overhead.
     */
    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(5)]
    public function benchSkipPopulatedTarget(): void
    {
        new BindBenchPopulatedUserView($this->publicSource);
    }
}

class BindPublicPropsEntity
{
    public string $name = 'John Doe';
    public int $age = 30;
    public string $email = 'john@example.com';
    public string $phone = '555-1234';
    public string $address = '123 Main St';
}

class BindGetterEntity
{
    private string $name = 'John Doe';
    private int $age = 30;
    private string $email = 'john@example.com';
    private string $phone = '555-1234';
    private string $address = '123 Main St';

    public function getName(): string
    {
        return $this->name;
    }

    public function getAge(): int
    {
        return $this->age;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function getAddress(): string
    {
        return $this->address;
    }
}

class BindPrivateEntity
{
    private string $name = 'John Doe'; // @phpstan-ignore property.unused

    private int $age = 30; // @phpstan-ignore property.unused

    private string $email = 'john@example.com'; // @phpstan-ignore property.unused

    private string $phone = '555-1234'; // @phpstan-ignore property.unused

    private string $address = '123 Main St'; // @phpstan-ignore property.unused
}

class BindBenchUserView extends BindView
{
    public ?string $name = null;
    public ?int $age = null;
    public ?string $email = null;
    public ?string $phone = null;
    public ?string $address = null;
}

class BindBenchPopulatedUserView extends BindView
{
    public ?string $name = 'preset';
    public ?int $age = 1;
    public ?string $email = 'preset';
    public ?string $phone = 'preset';
    public ?string $address = 'preset';
}
