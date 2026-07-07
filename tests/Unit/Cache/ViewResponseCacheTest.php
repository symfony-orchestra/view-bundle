<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Cache;

use ChamberOrchestra\ViewBundle\Cache\ViewResponseCache;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class ViewResponseCacheTest extends TestCase
{
    public function testProducesAndStoresOnMissThenServesFromCache(): void
    {
        $cache = new ViewResponseCache(new ArrayAdapter());

        $produced = 0;
        $produce = static function () use (&$produced): string {
            ++$produced;

            return '{"data":{"id":1}}';
        };

        self::assertSame('{"data":{"id":1}}', $cache->get('sig-1', null, $produce));
        self::assertSame('{"data":{"id":1}}', $cache->get('sig-1', null, $produce));
        self::assertSame(1, $produced, 'Second call must be served from cache without producing');
    }

    public function testDifferentSignaturesDoNotCollide(): void
    {
        $cache = new ViewResponseCache(new ArrayAdapter());

        self::assertSame('{"v":1}', $cache->get('sig-1', null, static fn (): string => '{"v":1}'));
        self::assertSame('{"v":2}', $cache->get('sig-2', null, static fn (): string => '{"v":2}'));
    }

    public function testSignatureWithPsr6ReservedCharactersIsAccepted(): void
    {
        $cache = new ViewResponseCache(new ArrayAdapter());
        $signature = 'user{1}@2024-01-01T00:00:00/rev(3)';

        self::assertSame('{"ok":true}', $cache->get($signature, null, static fn (): string => '{"ok":true}'));
        self::assertSame('{"ok":true}', $cache->get($signature, null, static fn (): string => '{"changed":true}'));
    }

    public function testWithoutPoolEveryCallProduces(): void
    {
        $cache = new ViewResponseCache();

        $produced = 0;
        $produce = static function () use (&$produced): string {
            ++$produced;

            return '{}';
        };

        $cache->get('sig-1', null, $produce);
        $cache->get('sig-1', null, $produce);

        self::assertSame(2, $produced);
    }

    public function testTtlExpiresEntries(): void
    {
        $cache = new ViewResponseCache(new ArrayAdapter());

        $cache->get('sig-1', -1, static fn (): string => '{"v":1}');

        self::assertSame('{"v":2}', $cache->get('sig-1', -1, static fn (): string => '{"v":2}'), 'Expired entry must be reproduced');
    }

    public function testDefaultTtlAppliesWhenNoExplicitTtlIsGiven(): void
    {
        $cache = new ViewResponseCache(new ArrayAdapter(), defaultTtl: -1);

        $cache->get('sig-1', null, static fn (): string => '{"v":1}');

        self::assertSame('{"v":2}', $cache->get('sig-1', null, static fn (): string => '{"v":2}'), 'Entry stored with the default TTL must expire');
    }

    public function testExplicitTtlOverridesDefaultTtl(): void
    {
        $cache = new ViewResponseCache(new ArrayAdapter(), defaultTtl: -1);

        $cache->get('sig-1', 3600, static fn (): string => '{"v":1}');

        self::assertSame('{"v":1}', $cache->get('sig-1', 3600, static fn (): string => '{"v":2}'), 'Entry stored with an explicit TTL must remain cached');
    }

    public function testJsonEntriesAlwaysExpireAfterOneDayByDefault(): void
    {
        $cache = new ViewResponseCache($this->createPoolExpecting(ViewResponseCache::DEFAULT_TTL_SECONDS));

        $cache->get('sig-1', null, static fn (): string => '{}');
    }

    public function testNormalizedEntriesAlwaysExpireAfterOneDayByDefault(): void
    {
        $cache = new ViewResponseCache($this->createPoolExpecting(ViewResponseCache::DEFAULT_TTL_SECONDS));

        $cache->getNormalized('sig-1', null, static fn (): array => []);
    }

    private function createPoolExpecting(int $expectedTtl): CacheItemPoolInterface
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item->method('isHit')->willReturn(false);
        $item->method('set')->willReturnSelf();
        $item
            ->expects(self::once())
            ->method('expiresAfter')
            ->with($expectedTtl)
            ->willReturnSelf();

        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->method('getItem')->willReturn($item);
        $pool->expects(self::once())->method('save')->willReturn(true);

        return $pool;
    }
}
