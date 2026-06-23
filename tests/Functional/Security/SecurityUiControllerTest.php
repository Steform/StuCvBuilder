<?php

namespace App\Tests\Functional\Security;

use App\Controller\SecurityUiController;
use App\Entity\User;
use App\Service\Auth\AuthenticatedLandingResolver;
use App\Service\Auth\TotpChallengeService;
use App\Service\Auth\TotpFlowDebugLogger;
use App\Service\Auth\TrustedDeviceService;
use App\Service\Notification\TotpEmailNotificationService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Psr\Log\NullLogger;

class SecurityUiControllerTest extends TestCase
{
    /**
     * @brief Ensure anonymous user can access login page.
     * @return void
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function testAnonymousCanOpenLoginPage(): void
    {
        $twig = new Environment(new ArrayLoader([
            'security/login.html.twig' => '{{ last_username|default("") }}',
        ]));

        $authenticationUtils = $this->createMock(AuthenticationUtils::class);
        $authenticationUtils->method('getLastUsername')->willReturn('');
        $authenticationUtils->method('getLastAuthenticationError')->willReturn(null);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn(null);

        $controller = $this->createController($security);
        $response = $controller->login($twig, $authenticationUtils);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    /**
     * @brief Configure security mock for authenticated landing resolver checks.
     *
     * @param Security&MockObject $security Security helper mock.
     * @param list<string> $grantedRoles Granted role names.
     * @return void
     * @date 2026-06-22
     * @author Stephane H.
     */
    private function configureGrantedRoles(Security&MockObject $security, array $grantedRoles): void
    {
        $security->method('isGranted')
            ->willReturnCallback(static fn (string $role): bool => in_array($role, $grantedRoles, true));
    }

    /**
     * @brief Ensure authentication error is exposed to login template.
     * @return void
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function testLoginPageReceivesAuthenticationError(): void
    {
        $twig = new Environment(new ArrayLoader([
            'security/login.html.twig' => '{% if error %}ERROR{% endif %}',
        ]));

        $authenticationUtils = $this->createMock(AuthenticationUtils::class);
        $authenticationUtils->method('getLastUsername')->willReturn('admin@example.com');
        $authenticationUtils->method('getLastAuthenticationError')->willReturn(new AuthenticationException('Invalid'));

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn(null);

        $controller = $this->createController($security);
        $response = $controller->login($twig, $authenticationUtils);

        self::assertStringContainsString('ERROR', (string) $response->getContent());
    }

    /**
     * @brief Ensure authenticated admin is redirected to dashboard.
     * @return void
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function testAuthenticatedAdminRedirectsToDashboard(): void
    {
        $twig = new Environment(new ArrayLoader([
            'security/login.html.twig' => 'unused',
        ]));
        $authenticationUtils = $this->createMock(AuthenticationUtils::class);
        $security = $this->createMock(Security::class);

        $admin = (new User())->setEmail('admin@example.com')->setRoles(['ROLE_ADMIN']);
        $security->method('getUser')->willReturn($admin);
        $this->configureGrantedRoles($security, ['ROLE_ADMIN']);

        $controller = $this->createController($security);
        $response = $controller->login($twig, $authenticationUtils);

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        self::assertSame('/dashboard', $response->headers->get('Location'));
    }

    /**
     * @brief Ensure authenticated standard user is redirected to home.
     * @return void
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function testAuthenticatedStandardUserRedirectsToHome(): void
    {
        $twig = new Environment(new ArrayLoader([
            'security/login.html.twig' => 'unused',
        ]));
        $authenticationUtils = $this->createMock(AuthenticationUtils::class);
        $security = $this->createMock(Security::class);

        $user = (new User())->setEmail('user@example.com')->setRoles(['ROLE_USER']);
        $security->method('getUser')->willReturn($user);
        $this->configureGrantedRoles($security, []);

        $controller = $this->createController($security);
        $response = $controller->login($twig, $authenticationUtils);

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        self::assertSame('/', $response->headers->get('Location'));
    }

    /**
     * @brief Build controller with mocked collaborators.
     * @param Security&MockObject $security Security helper mock.
     * @return SecurityUiController
     * @date 2026-04-23
     * @author Stephane H.
     */
    private function createController(Security $security): SecurityUiController
    {
        $totpChallengeService = $this->getMockBuilder(TotpChallengeService::class)->disableOriginalConstructor()->getMock();
        $totpEmailNotificationService = $this->createMock(TotpEmailNotificationService::class);
        $trustedDeviceService = $this->getMockBuilder(TrustedDeviceService::class)->disableOriginalConstructor()->getMock();
        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $loginTotpLimiter = new RateLimiterFactory(
            [
                'id' => 'login_totp_test',
                'policy' => 'fixed_window',
                'limit' => 100,
                'interval' => '15 minutes',
            ],
            new InMemoryStorage(),
        );

        return new SecurityUiController(
            $security,
            $totpChallengeService,
            $totpEmailNotificationService,
            $trustedDeviceService,
            $csrfTokenManager,
            $loginTotpLimiter,
            new TotpFlowDebugLogger(new NullLogger(), false),
            new AuthenticatedLandingResolver($security),
        );
    }
}
