<?php

namespace App\Tests\Functional\Invite;

use App\Controller\InvitationActivationController;
use App\Service\Auth\UserInvitationService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class InvitationActivationControllerTest extends TestCase
{
    /**
     * @brief Ensure invitation activation page renders.
     * @return void
     * @date 2026-04-28
     * @author Stephane H.
     */
    public function testActivationPageRenders(): void
    {
        $service = $this->createMock(UserInvitationService::class);
        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $limiterFactory = new RateLimiterFactory([
            'id' => 'test_invite_activate',
            'policy' => 'fixed_window',
            'limit' => 100,
            'interval' => '1 hour',
        ], new InMemoryStorage());
        $controller = new InvitationActivationController($service, $csrfTokenManager, $limiterFactory);
        $twig = new Environment(new ArrayLoader([
            'invitation/activate.html.twig' => 'activation',
        ]));

        $response = $controller->index('token', $twig);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('activation', (string) $response->getContent());
    }

    /**
     * @brief Ensure activation requires password.
     * @return void
     * @date 2026-04-28
     * @author Stephane H.
     */
    public function testActivationRequiresPassword(): void
    {
        $service = $this->createMock(UserInvitationService::class);
        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('isTokenValid')->willReturn(true);
        $limiterFactory = new RateLimiterFactory([
            'id' => 'test_invite_activate',
            'policy' => 'fixed_window',
            'limit' => 100,
            'interval' => '1 hour',
        ], new InMemoryStorage());
        $controller = new InvitationActivationController($service, $csrfTokenManager, $limiterFactory);

        $response = $controller->activate('token', new Request([], [
            '_csrf_token' => 'valid-token',
            'password' => '',
        ]));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/invite/activate/token', (string) $response->headers->get('Location'));
    }

    /**
     * @brief Ensure activation redirects after successful password set.
     * @return void
     * @date 2026-04-28
     * @author Stephane H.
     */
    public function testActivationRedirectsOnSuccess(): void
    {
        $service = $this->createMock(UserInvitationService::class);
        $service->method('activateInvitation')->willReturn(true);
        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('isTokenValid')->willReturn(true);
        $limiterFactory = new RateLimiterFactory([
            'id' => 'test_invite_activate',
            'policy' => 'fixed_window',
            'limit' => 100,
            'interval' => '1 hour',
        ], new InMemoryStorage());
        $controller = new InvitationActivationController($service, $csrfTokenManager, $limiterFactory);

        $response = $controller->activate('token', new Request([], [
            '_csrf_token' => 'valid-token',
            'password' => 'new-password',
        ]));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/login', (string) $response->headers->get('Location'));
    }
}
