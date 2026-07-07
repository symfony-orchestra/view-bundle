<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\View;

use ChamberOrchestra\ViewBundle\Cache\ViewResponseCache;
use ChamberOrchestra\ViewBundle\Localisation\LocalisationBridge;
use ChamberOrchestra\ViewBundle\Security\SecurityBridge;
use ChamberOrchestra\ViewBundle\Serializer\Metadata\ViewMetadataFactory;
use ChamberOrchestra\ViewBundle\Serializer\Normalizer\ViewNormalizer;
use ChamberOrchestra\ViewBundle\View\IterableView;
use ChamberOrchestra\ViewBundle\View\PrivateCachedView;
use ChamberOrchestra\ViewBundle\View\SourceCacheSignatureInterface;
use ChamberOrchestra\ViewBundle\View\View;
use ChamberOrchestra\ViewBundle\View\ViewInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\CustomNormalizer;
use Symfony\Component\Serializer\Serializer;

final class PrivateCachedViewTest extends TestCase
{
    protected function setUp(): void
    {
        SecurityBridge::setTokenStorage(null);
        LocalisationBridge::setRequestStack(null);
    }

    protected function tearDown(): void
    {
        SecurityBridge::setTokenStorage(null);
        LocalisationBridge::setRequestStack(null);
    }

    public function testSameContextAndSourceProduceTheSameSignature(): void
    {
        $entity = new PrivateCachedEntity(7, 'v1');

        $this->authenticate('alice');
        $this->switchLocale('de');

        self::assertSame(
            new PrivateCachedView($entity, PrivateCachedItemView::class)->getCacheSignature(),
            new PrivateCachedView($entity, PrivateCachedItemView::class)->getCacheSignature(),
        );
    }

    public function testEveryUserLocaleCombinationGetsItsOwnSignature(): void
    {
        $view = new PrivateCachedView(new PrivateCachedEntity(7, 'v1'), PrivateCachedItemView::class);

        $signatures = [];

        foreach (['alice', 'bob'] as $user) {
            foreach (['de', 'fr'] as $locale) {
                $this->authenticate($user);
                $this->switchLocale($locale);

                $signatures[] = $view->getCacheSignature();
            }
        }

        self::assertCount(4, \array_unique($signatures), 'Each user x locale combination must have an isolated entry');
    }

    public function testUserChangesTheSignatureIndependentlyOfLocale(): void
    {
        $view = new PrivateCachedView(new PrivateCachedEntity(7, 'v1'), PrivateCachedItemView::class);

        $this->switchLocale('de');

        $this->authenticate('alice');
        $alice = $view->getCacheSignature();

        $this->authenticate('bob');
        $bob = $view->getCacheSignature();

        self::assertNotSame($alice, $bob);
    }

    public function testLocaleChangesTheSignatureIndependentlyOfUser(): void
    {
        $view = new PrivateCachedView(new PrivateCachedEntity(7, 'v1'), PrivateCachedItemView::class);

        $this->authenticate('alice');

        $this->switchLocale('de');
        $german = $view->getCacheSignature();

        $this->switchLocale('fr');
        $french = $view->getCacheSignature();

        self::assertNotSame($german, $french);
    }

    public function testSourceStateStillInvalidatesTheSignature(): void
    {
        $this->authenticate('alice');
        $this->switchLocale('de');

        $before = new PrivateCachedView(new PrivateCachedEntity(7, 'v1'), PrivateCachedItemView::class);
        $after = new PrivateCachedView(new PrivateCachedEntity(7, 'v2'), PrivateCachedItemView::class);

        self::assertNotSame($before->getCacheSignature(), $after->getCacheSignature());
    }

    public function testAnonymousAndDefaultLocaleEntriesAreStable(): void
    {
        $view = new PrivateCachedView(new PrivateCachedEntity(7, 'v1'), PrivateCachedItemView::class);

        $bare = $view->getCacheSignature();

        $this->authenticate('alice');
        $this->switchLocale('de');

        self::assertNotSame($view->getCacheSignature(), $bare);

        SecurityBridge::setTokenStorage(null);
        LocalisationBridge::setRequestStack(null);

        self::assertSame($bare, $view->getCacheSignature(), 'Requests without user and locale must share one entry');
    }

    public function testDelegatesFactoryAndTtl(): void
    {
        $inner = new class extends View {
        };

        $view = new PrivateCachedView(
            new PrivateCachedEntity(7, 'v1'),
            PrivateCachedItemView::class,
            static fn (): ViewInterface => $inner,
            ttl: 120,
        );

        self::assertSame($inner, $view->createView());
        self::assertSame(120, $view->getTtl());
    }

    public function testDefaultFactoryBuildsTheViewClassFromTheSource(): void
    {
        $entity = new PrivateCachedEntity(7, 'v1');

        $created = new PrivateCachedView($entity, PrivateCachedItemView::class)->createView();

        self::assertInstanceOf(PrivateCachedItemView::class, $created);
        self::assertSame($entity, $created->source);
    }

    public function testRejectsViewClassWithoutSourceSignature(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must implement');

        new PrivateCachedView(new PrivateCachedEntity(7, 'v1'), PrivatelessPlainView::class);
    }

    public function testCollectionEntriesAreCachedPerViewingContext(): void
    {
        PrivateListItemView::$constructed = 0;

        $serializer = new Serializer(
            [new CustomNormalizer(), new ViewNormalizer(new ViewMetadataFactory(), new ViewResponseCache(new ArrayAdapter()))],
            [new JsonEncoder()],
        );

        $entities = [new PrivateCachedEntity(1, 'v1'), new PrivateCachedEntity(2, 'v1')];
        $makeList = static fn (): IterableView => new IterableView($entities, static fn (object $e): PrivateCachedView => new PrivateCachedView($e, PrivateListItemView::class));

        $this->authenticate('alice');
        $this->switchLocale('de');

        $json = $serializer->serialize($makeList(), 'json');

        self::assertSame('[{"id":1},{"id":2}]', $json);
        self::assertSame(2, PrivateListItemView::$constructed);

        $serializer->serialize($makeList(), 'json');

        self::assertSame(2, PrivateListItemView::$constructed, 'The same user must be served from the per-item cache');

        $this->authenticate('bob');
        $serializer->serialize($makeList(), 'json');

        self::assertSame(4, PrivateListItemView::$constructed, 'Another user must build their own item entries');

        $this->authenticate('alice');
        $serializer->serialize($makeList(), 'json');

        self::assertSame(4, PrivateListItemView::$constructed, 'Switching back must reuse the first user\'s entries');
    }

    private function authenticate(string $identifier): void
    {
        $storage = new TokenStorage();
        $storage->setToken(new UsernamePasswordToken(new InMemoryUser($identifier, null), 'main'));

        SecurityBridge::setTokenStorage($storage);
    }

    private function switchLocale(string $locale): void
    {
        $request = new Request();
        $request->setLocale($locale);

        $stack = new RequestStack();
        $stack->push($request);

        LocalisationBridge::setRequestStack($stack);
    }
}

final class PrivateCachedEntity
{
    public function __construct(
        public int $id,
        public string $version,
    ) {
    }
}

final class PrivateCachedItemView extends View implements SourceCacheSignatureInterface
{
    public function __construct(public object $source)
    {
    }

    public static function createCacheSignature(object $source): string
    {
        \assert($source instanceof PrivateCachedEntity);

        return \sprintf('private_cached_item_%d_%s', $source->id, $source->version);
    }
}

final class PrivatelessPlainView extends View
{
}

final class PrivateListItemView extends View implements SourceCacheSignatureInterface
{
    public static int $constructed = 0;

    public int $id;

    public function __construct(object $source)
    {
        \assert($source instanceof PrivateCachedEntity);
        ++self::$constructed;
        $this->id = $source->id;
    }

    public static function createCacheSignature(object $source): string
    {
        \assert($source instanceof PrivateCachedEntity);

        return \sprintf('private_list_item_%d_%s', $source->id, $source->version);
    }
}
