<?php

declare(strict_types=1);

namespace Benchmark;

use ChamberOrchestra\ViewBundle\CacheWarmer\BindUtilsCacheWarmer;
use ChamberOrchestra\ViewBundle\CacheWarmer\ViewMetadataCacheWarmer;
use ChamberOrchestra\ViewBundle\Serializer\Metadata\ViewMetadataFactory;
use ChamberOrchestra\ViewBundle\View\BindView;
use ChamberOrchestra\ViewBundle\View\View;
use PhpBench\Attributes as Bench;

class CacheWarmupBench
{
    private string $simpleViewClass;
    private string $bindViewClass;
    private object $bindSource;

    /** @var list<class-string> */
    private array $viewClasses;

    public function __construct()
    {
        BindView::setBindUtils(null);

        $simpleView = new class extends View {
            public string $name = 'Test';
            public int $count = 0;
            public ?string $optional = null;
        };
        $this->simpleViewClass = $simpleView::class;

        $this->bindSource = (object) ['name' => 'Test', 'value' => 123];
        $bindView = new class($this->bindSource) extends BindView {
            public string $name = '';
            public int $value = 0;
        };
        $this->bindViewClass = $bindView::class;

        $this->viewClasses = [$this->simpleViewClass, $this->bindViewClass];
    }

    #[Bench\Revs(100)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(3)]
    #[Bench\Groups(['warmup'])]
    public function benchViewMetadataWarmUp(): void
    {
        $tempDir = sys_get_temp_dir() . '/view_bench_' . bin2hex(random_bytes(8));
        mkdir($tempDir, 0777, true);

        $warmer = new ViewMetadataCacheWarmer(new ViewMetadataFactory(), $this->viewClasses);
        $warmer->warmUp($tempDir);

        array_map('unlink', glob($tempDir . '/*'));
        rmdir($tempDir);
    }

    #[Bench\Revs(100)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(3)]
    #[Bench\Groups(['warmup'])]
    public function benchBindUtilsWarmUp(): void
    {
        $tempDir = sys_get_temp_dir() . '/view_bench_' . bin2hex(random_bytes(8));
        mkdir($tempDir, 0777, true);

        $warmer = new BindUtilsCacheWarmer($this->viewClasses);
        $warmer->warmUp($tempDir);

        array_map('unlink', glob($tempDir . '/*'));
        rmdir($tempDir);
    }

    #[Bench\Revs(1000)]
    #[Bench\Iterations(5)]
    #[Bench\Warmup(5)]
    #[Bench\Groups(['metadata'])]
    public function benchMetadataLookup(): void
    {
        $factory = new ViewMetadataFactory();
        $factory->getMetadata($this->simpleViewClass);
    }
}
