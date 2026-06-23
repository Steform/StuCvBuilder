<?php

namespace App\Tests\Functional\Security;

use App\Security\LoginFormAuthenticator;
use App\Service\Auth\AuthenticatedLandingResolver;
use App\Service\Auth\TotpFlowDebugLogger;
use App\Service\Auth\TrustedDeviceService;
use App\Controller\SecurityUiController;
use App\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use App\Entity\User;
use Psr\Log\NullLogger;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;

class LoginFormAuthenticatorTest extends TestCase
{
    /**
     * @brief Ensure authenticator supports login post route.
     * @return void
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function testSupportsLoginPostRoute(): void
    {
        $authenticator = $this->createAuthenticator();
        $request = Request::create('/login/check', 'POST');
        $request->attributes->set('_route', 'app_login_check');

        $supports = $authenticator->supports($request);

        self::assertTrue($supports);
    }

    /**
     * @brief Ensure passport contains expected badges.
     * @return void
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function testAuthenticateBuildsPassportWithBadges(): void
    {
        $authenticator = $this->createAuthenticator();

        $request = new Request([], [
            'email' => 'admin@example.com',
            'password' => 'secret',
            '_csrf_token' => 'csrf-token',
        ]);
        $request->setMethod('POST');
        $request->attributes->set('_route', 'app_login_check');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $passport = $authenticator->authenticate($request);

        self::assertTrue($passport->hasBadge(CsrfTokenBadge::class));
        self::assertTrue($passport->hasBadge(RememberMeBadge::class));
    }

    /**
     * @brief Ensure invalid TOTP challenge rejects custom credentials.
     * @return void
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function testOnAuthenticationSuccessRedirectsToTotpWhenNotTrusted(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $trustedDeviceService = $this->createMock(TrustedDeviceService::class);
        $trustedDeviceService->method('isTrustedDevice')->willReturn(false);
        $securityUiController = $this->createMock(SecurityUiController::class);
        $securityUiController->expects(self::once())->method('startTotpStep');
        $userRepository = $this->createMock(UserRepository::class);
        $authenticator = new LoginFormAuthenticator(
            $urlGenerator,
            $trustedDeviceService,
            $securityUiController,
            $userRepository,
            new TotpFlowDebugLogger(new NullLogger(), false),
            $this->createLandingResolver('/login/totp'),
        );

        $request = Request::create('/login/check', 'POST', ['_remember_me' => '1']);
        $request->setSession(new Session(new MockArraySessionStorage()));
        $request->request->set('_remember_me', '1');
        $user = (new User())->setEmail('user@example.com')->setRoles(['ROLE_USER']);
        $user->setTotpEnabled(true);
        $user->setSetupConfirmed(true);
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $response = $authenticator->onAuthenticationSuccess($request, $token, 'main');
        self::assertSame('/login/totp', $response->headers->get('Location'));
    }

    /**
     * @brief Ensure authentication success redirects standard user to home.
     * @return void
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function testOnAuthenticationSuccessRedirectsStandardUserToHome(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')
            ->willReturnMap([
                ['app_home', [], 1, '/'],
                ['app_dashboard', [], 1, '/dashboard'],
            ]);
        $trustedDeviceService = $this->createMock(TrustedDeviceService::class);
        $trustedDeviceService->method('isTrustedDevice')->willReturn(true);
        $securityUiController = $this->createMock(SecurityUiController::class);
        $userRepository = $this->createMock(UserRepository::class);
        $authenticator = new LoginFormAuthenticator(
            $urlGenerator,
            $trustedDeviceService,
            $securityUiController,
            $userRepository,
            new TotpFlowDebugLogger(new NullLogger(), false),
            $this->createLandingResolver('/'),
        );
        $request = Request::create('/login', 'POST');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn((new User())->setEmail('user@example.com')->setRoles(['ROLE_USER']));

        $response = $authenticator->onAuthenticationSuccess($request, $token, 'main');

        self::assertNotNull($response);
        self::assertSame('/', $response->headers->get('Location'));
    }

    /**
     * @brief Build authenticator for isolated tests.
     * @return LoginFormAuthenticator
     * @date 2026-04-23
     * @author Stephane H.
     */
    private function createAuthenticator(): LoginFormAuthenticator
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')
            ->willReturnMap([
                ['app_login_check', [], UrlGeneratorInterface::ABSOLUTE_PATH, '/login/check'],
                ['app_home', [], UrlGeneratorInterface::ABSOLUTE_PATH, '/'],
            ]);
        $trustedDeviceService = $this->createMock(TrustedDeviceService::class);
        $trustedDeviceService->method('isTrustedDevice')->willReturn(true);
        $securityUiController = $this->createMock(SecurityUiController::class);
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn((new User())->setEmail('admin@example.com'));

        return new LoginFormAuthenticator(
            $urlGenerator,
            $trustedDeviceService,
            $securityUiController,
            $userRepository,
            new TotpFlowDebugLogger(new NullLogger(), false),
            $this->createLandingResolver('/'),
        );
    }

    /**
     * @brief Build landing resolver mock returning a fixed path.
     *
     * @param string $landingPath Resolved post-auth path.
     * @return AuthenticatedLandingResolver
     * @date 2026-06-22
     * @author Stephane H.
     */
    private function createLandingResolver(string $landingPath): AuthenticatedLandingResolver
    {
        $security = $this->createMock(\Symfony\Bundle\SecurityBundle\Security::class);
        $resolver = $this->getMockBuilder(AuthenticatedLandingResolver::class)
            ->setConstructorArgs([$security])
            ->onlyMethods(['resolveLandingPath'])
            ->getMock();
        $resolver->method('resolveLandingPath')->willReturn($landingPath);

        return $resolver;
    }
}
