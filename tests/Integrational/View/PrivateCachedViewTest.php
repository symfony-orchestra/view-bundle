<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Integrational\View;

use ChamberOrchestra\ViewBundle\EventSubscriber\SetLocalisationSubscriber;
use ChamberOrchestra\ViewBundle\EventSubscriber\SetSecuritySubscriber;
use ChamberOrchestra\ViewBundle\EventSubscriber\ViewSubscriber;
use ChamberOrchestra\ViewBundle\Localisation\LocalisationBridge;
use ChamberOrchestra\ViewBundle\Security\SecurityBridge;
use ChamberOrchestra\ViewBundle\View\IterableView;
use ChamberOrchestra\ViewBundle\View\LocalisationAwareTrait;
use ChamberOrchestra\ViewBundle\View\PrivateCachedView;
use ChamberOrchestra\ViewBundle\View\SecurityAwareTrait;
use ChamberOrchestra\ViewBundle\View\SourceCacheSignatureInterface;
use ChamberOrchestra\ViewBundle\View\View;
use ChamberOrchestra\ViewBundle\View\ViewInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * Drives private (per user, per locale) PrivateCachedView results through the real
 * container exactly like a controller action. The locale flows through the
 * container's request stack and the container-wired SetLocalisationSubscriber; the
 * user is authenticated on the SecurityBridge directly (the TestKernel has no
 * SecurityBundle, so no token storage service exists).
 */
final class PrivateCachedViewTest extends KernelTestCase
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

    public function testEachUserAndLocaleGetsAnIsolatedPrivatePayload(): void
    {
        $article = $this->createArticle();

        self::assertSame(
            '{"data":{"id":1,"title":"Hallo","canEdit":true}}',
            $this->dispatchAction('alice', 'de', new PrivateCachedView($article, PrivateArticleView::class))->getContent(),
        );

        self::assertSame(
            '{"data":{"id":1,"title":"Bonjour","canEdit":true}}',
            $this->dispatchAction('alice', 'fr', new PrivateCachedView($article, PrivateArticleView::class))->getContent(),
            'The same user in another locale must get a localised payload',
        );

        self::assertSame(
            '{"data":{"id":1,"title":"Hallo","canEdit":false}}',
            $this->dispatchAction('bob', 'de', new PrivateCachedView($article, PrivateArticleView::class))->getContent(),
            'Another user must never receive the author\'s cached payload',
        );
    }

    public function testRepeatedRequestIsServedFromThePrivateCache(): void
    {
        $article = $this->createArticle();
        PrivateArticleView::$constructed = 0;

        $this->dispatchAction('alice', 'de', new PrivateCachedView($article, PrivateArticleView::class));

        self::assertSame(1, PrivateArticleView::$constructed);

        $response = $this->dispatchAction('alice', 'de', new PrivateCachedView($article, PrivateArticleView::class));

        self::assertSame('{"data":{"id":1,"title":"Hallo","canEdit":true}}', $response->getContent());
        self::assertSame(1, PrivateArticleView::$constructed, 'A repeated request in the same context must be served from the cache');

        $this->dispatchAction('alice', 'fr', new PrivateCachedView($article, PrivateArticleView::class));

        self::assertSame(2, PrivateArticleView::$constructed, 'Another locale must build its own entry');

        $this->dispatchAction('bob', 'de', new PrivateCachedView($article, PrivateArticleView::class));

        self::assertSame(3, PrivateArticleView::$constructed, 'Another user must build their own entry');
    }

    public function testAnonymousRequestsShareTheAnonymousEntryPerLocale(): void
    {
        $article = $this->createArticle();
        PrivateArticleView::$constructed = 0;

        $first = $this->dispatchAction(null, 'de', new PrivateCachedView($article, PrivateArticleView::class));
        $second = $this->dispatchAction(null, 'de', new PrivateCachedView($article, PrivateArticleView::class));

        self::assertSame('{"data":{"id":1,"title":"Hallo","canEdit":false}}', $first->getContent());
        self::assertSame($first->getContent(), $second->getContent());
        self::assertSame(1, PrivateArticleView::$constructed);
    }

    public function testSourceChangeInvalidatesTheEntriesOfAllViewingContexts(): void
    {
        $article = $this->createArticle();
        PrivateArticleView::$constructed = 0;

        $this->dispatchAction('alice', 'de', new PrivateCachedView($article, PrivateArticleView::class));
        $this->dispatchAction('bob', 'de', new PrivateCachedView($article, PrivateArticleView::class));

        self::assertSame(2, PrivateArticleView::$constructed);

        // The entity was modified: same id, new version and title
        $updated = new PrivateArticleEntity(1, ['de' => 'Servus', 'fr' => 'Salut', 'en' => 'Hi'], 'alice', $article->version.'_v2');

        $aliceResponse = $this->dispatchAction('alice', 'de', new PrivateCachedView($updated, PrivateArticleView::class));
        $bobResponse = $this->dispatchAction('bob', 'de', new PrivateCachedView($updated, PrivateArticleView::class));

        self::assertSame('{"data":{"id":1,"title":"Servus","canEdit":true}}', $aliceResponse->getContent());
        self::assertSame('{"data":{"id":1,"title":"Servus","canEdit":false}}', $bobResponse->getContent());
        self::assertSame(4, PrivateArticleView::$constructed, 'A source change must rebuild the entries of every viewing context');
    }

    public function testActionWithPrivateCachedCollectionEntriesIsScopedPerUser(): void
    {
        $articles = [$this->createArticle(), new PrivateArticleEntity(2, ['de' => 'Welt', 'fr' => 'Monde', 'en' => 'World'], 'bob', \bin2hex(\random_bytes(8)))];
        PrivateArticleView::$constructed = 0;

        $makeList = static fn (): ViewInterface => new IterableView($articles, static fn (object $a): PrivateCachedView => new PrivateCachedView($a, PrivateArticleView::class));

        $response = $this->dispatchAction('alice', 'de', $makeList());

        self::assertSame(
            '{"data":[{"id":1,"title":"Hallo","canEdit":true},{"id":2,"title":"Welt","canEdit":false}]}',
            $response->getContent(),
        );
        self::assertSame(2, PrivateArticleView::$constructed);

        $this->dispatchAction('alice', 'de', $makeList());

        self::assertSame(2, PrivateArticleView::$constructed, 'The same user must be served from the per-item cache');

        $response = $this->dispatchAction('bob', 'de', $makeList());

        self::assertSame(
            '{"data":[{"id":1,"title":"Hallo","canEdit":false},{"id":2,"title":"Welt","canEdit":true}]}',
            $response->getContent(),
            'Another user must see their own personalised items, never the first user\'s cached ones',
        );
        self::assertSame(4, PrivateArticleView::$constructed);
    }

    public function testBridgeSubscribersAreRegisteredInTheContainer(): void
    {
        static::bootKernel();
        $container = static::getContainer();

        $container->get(SetSecuritySubscriber::class)();
        $container->get(SetLocalisationSubscriber::class)();

        self::assertNull(SecurityBridge::getUser(), 'Without SecurityBundle the bridge must resolve to the anonymous user');
        self::assertNull(LocalisationBridge::getLocale(), 'Without a current request the bridge must resolve to no locale');
    }

    private function createArticle(): PrivateArticleEntity
    {
        return new PrivateArticleEntity(
            1,
            ['de' => 'Hallo', 'fr' => 'Bonjour', 'en' => 'Hello'],
            'alice',
            \bin2hex(\random_bytes(8)),
        );
    }

    private function dispatchAction(?string $userIdentifier, string $locale, ViewInterface $controllerResult): Response
    {
        static::bootKernel();
        $container = static::getContainer();

        if (null !== $userIdentifier) {
            $storage = new TokenStorage();
            $storage->setToken(new UsernamePasswordToken(new InMemoryUser($userIdentifier, null), 'main'));
            SecurityBridge::setTokenStorage($storage);
        } else {
            SecurityBridge::setTokenStorage(null);
        }

        $request = new Request();
        $request->setLocale($locale);

        $requestStack = $container->get(RequestStack::class);
        $requestStack->push($request);

        try {
            $container->get(SetLocalisationSubscriber::class)();

            $event = new ViewEvent(
                $container->get('kernel'),
                $request,
                HttpKernelInterface::MAIN_REQUEST,
                $controllerResult
            );

            $container->get(ViewSubscriber::class)($event);

            $response = $event->getResponse();
            self::assertNotNull($response);

            return $response;
        } finally {
            $requestStack->pop();
        }
    }
}

final class PrivateArticleEntity
{
    /**
     * @param array<string, string> $titles
     */
    public function __construct(
        public int $id,
        public array $titles,
        public string $authorIdentifier,
        public string $version,
    ) {
    }
}

final class PrivateArticleView extends View implements SourceCacheSignatureInterface
{
    use LocalisationAwareTrait;
    use SecurityAwareTrait;

    public static int $constructed = 0;

    public int $id;
    public string $title;
    public bool $canEdit;

    public function __construct(PrivateArticleEntity $article)
    {
        ++self::$constructed;
        $this->id = $article->id;
        // Both the locale and the user come from the static bridges — nothing is passed in
        $this->title = $article->titles[self::getLocale() ?? 'en'];
        $this->canEdit = self::getUserIdentifier() === $article->authorIdentifier;
    }

    public static function createCacheSignature(object $source): string
    {
        \assert($source instanceof PrivateArticleEntity);

        return \sprintf('private_article_%d_%s', $source->id, $source->version);
    }
}
