<?php

declare(strict_types=1);

namespace Tests\Unit\EventSubscriber;

use ChamberOrchestra\ViewBundle\EventSubscriber\SetVersionSubscriber;
use ChamberOrchestra\ViewBundle\Utils\BindUtils;
use PHPUnit\Framework\TestCase;

final class SetVersionSubscriberTest extends TestCase
{
    protected function setUp(): void
    {
        $this->resetBindUtils();
    }

    protected function tearDown(): void
    {
        $this->resetBindUtils();
    }

    public function testItConfiguresBindUtilsWithCacheWhenNotInDebug(): void
    {
        $subscriber = new SetVersionSubscriber('build-123', false);
        $subscriber();

        $this->assertSame('build-123', $this->getBindUtilsProperty('version'));
        $this->assertSame(24 * 3600, $this->getBindUtilsProperty('cacheLifetime'));
        $this->assertSame('view_bind', $this->getBindUtilsProperty('cacheNamespace'));
        $this->assertTrue($this->getBindUtilsProperty('configured'));
    }

    public function testItDisablesCacheWhenDebugIsEnabled(): void
    {
        $subscriber = new SetVersionSubscriber('debug-build', true);
        $subscriber();

        $this->assertSame('debug-build', $this->getBindUtilsProperty('version'));
        $this->assertSame(0, $this->getBindUtilsProperty('cacheLifetime'));
        $this->assertSame('view_bind', $this->getBindUtilsProperty('cacheNamespace'));
        $this->assertTrue($this->getBindUtilsProperty('configured'));
    }

    private function getBindUtilsProperty(string $property): mixed
    {
        return new \ReflectionProperty(BindUtils::class, $property)->getValue();
    }

    private function resetBindUtils(): void
    {
        foreach ([
            'configured' => false,
            'cacheNamespace' => 'bind_view',
            'cacheLifetime' => 0,
            'version' => '',
            'storage' => [],
        ] as $property => $value) {
            $ref = new \ReflectionProperty(BindUtils::class, $property);
            $ref->setValue(null, $value);
        }
    }
}
