<?php

namespace App\Tests\Functional\Locale;

use App\EventSubscriber\LocaleSubscriber;
use App\Service\Locale\LocaleCodeNormalizer;
use App\Tests\Support\LocaleConfigurationServiceTestFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class LocaleSubscriberTest extends TestCase
{
    private LocaleCodeNormalizer $localeCodeNormalizer;

    protected function setUp(): void
    {
        $this->localeCodeNormalizer = new LocaleCodeNormalizer();
    }

    /**
     * @brief Ensure browser french locale is selected on first visit.
     * @return void
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function testUsesBrowserLocaleWhenSupported(): void
    {
        $subscriber = new LocaleSubscriber(
            LocaleConfigurationServiceTestFactory::create(),
            $this->localeCodeNormalizer,
            ['fr', 'en', 'de', 'lt', 'nb'],
            'en',
            'fr'
        );
        $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_LANGUAGE' => 'fr-FR,fr;q=0.9,en;q=0.8']);
        $request->setSession(new Session(new MockArraySessionStorage()));
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        self::assertSame('fr', $request->getLocale());
        self::assertSame('fr', $request->getSession()->get('_locale'));
    }

    /**
     * @brief Ensure unsupported browser locale falls back to english.
     * @return void
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function testFallsBackToDefaultLocaleWhenBrowserLocaleUnsupported(): void
    {
        $subscriber = new LocaleSubscriber(
            LocaleConfigurationServiceTestFactory::create(),
            $this->localeCodeNormalizer,
            ['fr', 'en', 'de', 'lt', 'nb'],
            'en',
            'fr'
        );
        $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_LANGUAGE' => 'es-ES,es;q=0.9']);
        $request->setSession(new Session(new MockArraySessionStorage()));
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        self::assertSame('en', $request->getLocale());
    }

    /**
     * @brief Ensure norwegian browser variants map to canonical nb locale.
     * @return void
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function testMapsNorwegianBrowserVariantToNb(): void
    {
        $subscriber = new LocaleSubscriber(
            LocaleConfigurationServiceTestFactory::create(),
            $this->localeCodeNormalizer,
            ['fr', 'en', 'de', 'lt', 'nb'],
            'en',
            'fr'
        );
        $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_LANGUAGE' => 'nb-NO,nb;q=0.9']);
        $request->setSession(new Session(new MockArraySessionStorage()));
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        self::assertSame('nb', $request->getLocale());
    }

    /**
     * @brief Legacy cookie site_locale=no must resolve to canonical nb.
     * @return void
     * @date 2026-07-05
     * @author Stephane H.
     */
    public function testLegacyCookieNoResolvesToNb(): void
    {
        $subscriber = new LocaleSubscriber(
            LocaleConfigurationServiceTestFactory::create(),
            $this->localeCodeNormalizer,
            ['fr', 'en', 'de', 'lt', 'nb'],
            'en',
            'fr'
        );
        $request = Request::create('/', 'GET', [], ['site_locale' => 'no']);
        $request->setSession(new Session(new MockArraySessionStorage()));
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        self::assertSame('nb', $request->getLocale());
    }
}
