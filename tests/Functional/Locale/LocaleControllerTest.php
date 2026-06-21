<?php

namespace App\Tests\Functional\Locale;

use App\Controller\LocaleController;
use App\Service\Http\SafeRedirectResolver;
use App\Tests\Support\LocaleConfigurationServiceTestFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class LocaleControllerTest extends TestCase
{
    /**
     * @brief Ensure locale switch persists locale and redirects to referer.
     * @param void No input parameter.
     * @return void
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function testSwitchPersistsLocaleAndRedirectsBack(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/');
        $controller = new LocaleController(
            LocaleConfigurationServiceTestFactory::create(),
            new SafeRedirectResolver($urlGenerator),
            ['fr', 'en', 'de', 'lt', 'no'],
        );
        $request = Request::create('/locale/de', 'GET', server: ['HTTP_HOST' => 'example.test']);
        $request->headers->set('referer', 'http://example.test/dashboard');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $response = $controller->switch('de', $request);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/dashboard', $response->headers->get('Location'));
        self::assertSame('de', $request->getSession()->get('_locale'));
        self::assertStringContainsString('site_locale=de', (string) $response->headers->get('set-cookie'));
    }

    /**
     * @brief External referer must redirect to safe fallback instead of open redirect.
     *
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testSwitchRejectsExternalReferer(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->with('app_home')->willReturn('/');
        $controller = new LocaleController(
            LocaleConfigurationServiceTestFactory::create(),
            new SafeRedirectResolver($urlGenerator),
            ['fr', 'en', 'de', 'lt', 'no'],
        );
        $request = Request::create('/locale/en', 'GET', server: ['HTTP_HOST' => 'example.test']);
        $request->headers->set('referer', 'https://evil.example/phish');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $response = $controller->switch('en', $request);

        self::assertSame('/', $response->headers->get('Location'));
    }
}
