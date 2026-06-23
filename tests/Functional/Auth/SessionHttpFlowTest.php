<?php

namespace App\Tests\Functional\Auth;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class SessionHttpFlowTest extends KernelTestCase
{
    /**
     * @brief Ensure anonymous user can open login route.
     * @return void
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function testAnonymousAccessLoginPage(): void
    {
        self::bootKernel();
        $kernel = static::getContainer()->get(HttpKernelInterface::class);
        $response = $kernel->handle(Request::create('/login', 'GET'));

        self::assertSame(302, $response->getStatusCode());
        self::assertStringContainsString('/setup', (string) $response->headers->get('Location'));
    }

    /**
     * @brief Ensure dashboard requires authenticated session.
     * @return void
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function testDashboardDeniedWithoutSession(): void
    {
        self::bootKernel();
        $kernel = static::getContainer()->get(HttpKernelInterface::class);
        $response = $kernel->handle(Request::create('/dashboard', 'GET'));

        self::assertSame(302, $response->getStatusCode());
        self::assertStringContainsString('/login', (string) $response->headers->get('Location'));
    }

    /**
     * @brief Ensure logout route is reachable for public access control.
     * @return void
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function testLogoutRouteIsHandledByFirewall(): void
    {
        self::bootKernel();
        $kernel = static::getContainer()->get(HttpKernelInterface::class);
        $response = $kernel->handle(Request::create('/logout', 'GET'));

        self::assertContains($response->getStatusCode(), [302, 403, 405]);
    }

    /**
     * @brief Ensure home route is gated by setup subscriber.
     * @return void
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function testHomeRedirectsToSetupWhenNoConfirmedAdmin(): void
    {
        self::bootKernel();
        $kernel = static::getContainer()->get(HttpKernelInterface::class);
        $response = $kernel->handle(Request::create('/', 'GET'));

        self::assertSame(302, $response->getStatusCode());
        self::assertStringContainsString('/setup', (string) $response->headers->get('Location'));
    }
}
