<?php

namespace App\Tests\Functional\Theme;

use App\Controller\ThemeController;
use App\Service\Http\SafeRedirectResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ThemeControllerTest extends TestCase
{
    /**
     * @brief Ensure theme switch persists and redirects to referer.
     * @param void No input parameter.
     * @return void
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function testSwitchPersistsThemeAndRedirectsBack(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/');
        $controller = new ThemeController(new SafeRedirectResolver($urlGenerator));
        $request = Request::create('/theme/dark', 'GET', server: ['HTTP_HOST' => 'example.test']);
        $request->headers->set('referer', 'http://example.test/dashboard');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $response = $controller->switch('dark', $request);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/dashboard', $response->headers->get('Location'));
        self::assertSame('dark', $request->getSession()->get('_theme'));
        self::assertStringContainsString('site_theme=dark', (string) $response->headers->get('set-cookie'));
    }
}
