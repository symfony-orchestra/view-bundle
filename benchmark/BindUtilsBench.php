<?php

declare(strict_types=1);

namespace Benchmark;

use ChamberOrchestra\ViewBundle\Utils\BindUtils;
use ChamberOrchestra\ViewBundle\View\BindView;
use PhpBench\Attributes as Bench;

class BindUtilsBench
{
    private object $source;
    private string $viewClass;

    public function __construct()
    {
        BindUtils::configure('bench-build-id', 0, 'bench');

        $this->source = new class {
            public string $name = 'John Doe';
            public int $age = 30;
            public string $email = 'john@example.com';
            public string $phone = '555-1234';
            public string $address = '123 Main St';
        };

        $view = new class($this->source) extends BindView {
            public string $name = '';
            public int $age = 0;
            public string $email = '';
            public string $phone = '';
            public string $address = '';
        };

        $this->viewClass = $view::class;
    }

    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(5)]
    public function benchBindViewConstruction(): void
    {
        new ($this->viewClass)($this->source);
    }

    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(5)]
    public function benchSyncDirectly(): void
    {
        $target = new \stdClass();
        $target->name = null;
        $target->age = null;
        $target->email = null;

        BindUtils::instance()->sync($target, $this->source);
    }
}
