<?php

namespace App\Tests\Functional\Theme;

use App\EventSubscriber\ThemeSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class ThemeSubscriberTest extends TestCase
{
    /**
     * @brief Ensure theme is resolved from session first.
     * @return void
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function testResolvesThemeFromSessionBeforeCookie(): void
    {
        $subscriber = new ThemeSubscriber('light', 'light');
        $request = Request::create('/');
        $request->cookies->set('site_theme', 'dark');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $request->getSession()->set('_theme', 'light');
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        self::assertSame('light', $request->attributes->get('app_theme'));
    }

    /**
     * @brief Ensure theme is resolved from cookie when session missing.
     * @return void
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function testResolvesThemeFromCookieBeforeDefault(): void
    {
        $subscriber = new ThemeSubscriber('light', 'light');
        $request = Request::create('/', 'GET', [], ['site_theme' => 'dark']);
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        self::assertSame('dark', $request->attributes->get('app_theme'));
    }

    /**
     * @brief Ensure default theme is used when no preference exists.
     * @return void
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function testFallsBackToDefaultTheme(): void
    {
        $subscriber = new ThemeSubscriber('light', 'light');
        $request = Request::create('/');
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        self::assertSame('light', $request->attributes->get('app_theme'));
    }
}
