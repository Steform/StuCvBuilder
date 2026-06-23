<?php

namespace App\Tests\Functional\Invite;

use App\Controller\Admin\UserInvitationController;
use App\Entity\User;
use App\Service\Auth\UserInvitationService;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class UserInvitationControllerTest extends TestCase
{
    /**
     * @brief Ensure admin invitation rejects empty payload.
     * @return void
     * @date 2026-04-28
     * @author Stephane H.
     */
    public function testInviteRejectsEmptyEmail(): void
    {
        $invitationService = $this->createMock(UserInvitationService::class);
        $security = $this->createMock(Security::class);
        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('isTokenValid')->willReturn(true);
        $limiterFactory = new RateLimiterFactory([
            'id' => 'test_admin_invite',
            'policy' => 'fixed_window',
            'limit' => 100,
            'interval' => '1 hour',
        ], new InMemoryStorage());
        $controller = new UserInvitationController($invitationService, $security, $csrfTokenManager, $limiterFactory);
        $request = new Request([], ['email' => '']);
        $request->request->set('_csrf_token', 'valid-token');
        $request->headers->set('Accept', 'application/json');
        $response = $controller->invite($request);

        self::assertSame(400, $response->getStatusCode());
    }

    /**
     * @brief Ensure admin invitation returns activation URL.
     * @return void
     * @date 2026-04-28
     * @author Stephane H.
     */
    public function testInviteReturnsActivationUrl(): void
    {
        $adminUser = new User();
        $reflectionProperty = new \ReflectionProperty(User::class, 'id');
        $reflectionProperty->setValue($adminUser, 7);
        $adminUser->setEmail('admin@example.com');
        $adminUser->setRoles(['ROLE_ADMIN']);

        $invitationService = $this->createMock(UserInvitationService::class);
        $invitationService->method('inviteUser')->willReturn('http://localhost/invite/activate/token');
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($adminUser);
        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('isTokenValid')->willReturn(true);
        $limiterFactory = new RateLimiterFactory([
            'id' => 'test_admin_invite',
            'policy' => 'fixed_window',
            'limit' => 100,
            'interval' => '1 hour',
        ], new InMemoryStorage());
        $controller = new UserInvitationController($invitationService, $security, $csrfTokenManager, $limiterFactory);
        $request = new Request([], [
            'email' => 'invite@example.com',
            'pseudonym' => 'Invite',
            '_csrf_token' => 'valid-token',
        ]);
        $request->headers->set('Accept', 'application/json');
        $response = $controller->invite($request);
        self::assertSame(201, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('http://localhost/invite/activate/token', $payload['activationUrl'] ?? null);
    }
}
