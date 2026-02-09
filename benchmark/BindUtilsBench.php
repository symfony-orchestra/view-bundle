<?php

declare(strict_types=1);

namespace Benchmark;

use ChamberOrchestra\ViewBundle\Utils\BindUtils;
use ChamberOrchestra\ViewBundle\View\BindView;

class BindUtilsBench
{
    private object $source;
    private string $viewClass;

    public function __construct()
    {
        // Configure BindUtils
        BindUtils::configure('bench-build-id', 0, 'bench');

        // Create source object
        $this->source = new class {
            public string $name = 'John Doe';
            public int $age = 30;
            public string $email = 'john@example.com';
            public string $phone = '555-1234';
            public string $address = '123 Main St';
        };

        // Create target view class
        $this->viewClass = new class($this->source) extends BindView {
            public string $name = '';
            public int $age = 0;
            public string $email = '';
            public string $phone = '';
            public string $address = '';
        };

        $this->viewClass = $this->viewClass::class;
    }

    /**
     * @Revs(1000)
     * @Iterations(5)
     */
    public function benchBindViewConstruction(): void
    {
        new ($this->viewClass)($this->source);
    }

    /**
     * @Revs(1000)
     * @Iterations(5)
     */
    public function benchSyncDirectly(): void
    {
        $target = new \stdClass();
        $target->name = null;
        $target->age = null;
        $target->email = null;

        BindUtils::instance()->sync($target, $this->source);
    }
}
