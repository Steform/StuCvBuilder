<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Controller\SetupController;
use App\Service\Auth\TotpChallengeService;
use App\Service\Auth\TotpFlowDebugLogger;
use App\Service\Notification\TotpEmailNotificationService;
use App\Service\Setup\SetupStateService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * @brief Factory helpers for {@see SetupController} functional tests.
 */
final class SetupControllerTestFactory
{
    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    private static function mock(string $class): object
    {
        $factory = new class ('setup-controller-factory') extends TestCase {
            /**
             * @template T of object
             * @param class-string<T> $class
             * @return T
             */
            public function build(string $class): object
            {
                return $this->createMock($class);
            }
        };

        return $factory->build($class);
    }

    /**
     * @brief Build setup controller with optional CSRF acceptance override.
     *
     * @param SetupStateService $setupStateService Setup state mock.
     * @param EntityManagerInterface $entityManager Entity manager mock.
     * @param UserPasswordHasherInterface $passwordHasher Password hasher mock.
     * @param TotpChallengeService $totpChallengeService TOTP challenge mock.
     * @param TotpEmailNotificationService $totpEmailNotificationService TOTP mail mock.
     * @param bool $csrfValid Whether submitted CSRF tokens should validate.
     * @return SetupController
     * @date 2026-06-21
     * @author Stephane H.
     */
    public static function create(
        SetupStateService $setupStateService,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        TotpChallengeService $totpChallengeService,
        TotpEmailNotificationService $totpEmailNotificationService,
        bool $csrfValid = true,
    ): SetupController {
        /** @var CsrfTokenManagerInterface&MockObject $csrfTokenManager */
        $csrfTokenManager = self::mock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('isTokenValid')->willReturn($csrfValid);

        $setupTotpLimiter = new RateLimiterFactory(
            [
                'id' => 'setup_totp_test',
                'policy' => 'fixed_window',
                'limit' => 100,
                'interval' => '15 minutes',
            ],
            new InMemoryStorage(),
        );

        return new SetupController(
            $setupStateService,
            $entityManager,
            $passwordHasher,
            $totpChallengeService,
            $totpEmailNotificationService,
            $csrfTokenManager,
            $setupTotpLimiter,
            new NullLogger(),
            new TotpFlowDebugLogger(new NullLogger(), false),
        );
    }

    /**
     * @brief Attach setup session and default CSRF fields to a POST request.
     *
     * @param Request $request Mutable request.
     * @param string|null $pendingEmail Pending setup email stored in session.
     * @param string $csrfTokenId CSRF token identifier.
     * @return Request
     * @date 2026-06-21
     * @author Stephane H.
     */
    public static function withSetupSession(
        Request $request,
        ?string $pendingEmail = null,
        string $csrfTokenId = 'setup_create',
    ): Request {
        $session = new Session(new MockArraySessionStorage());
        if ($pendingEmail !== null) {
            $session->set('setup.pending_email', $pendingEmail);
        }
        $request->setSession($session);
        $request->request->set('_csrf_token', $csrfTokenId.'-token');

        return $request;
    }
}
