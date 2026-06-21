<?php

namespace App\Tests\Functional\Setup;

use App\Entity\User;
use App\Service\Auth\TotpChallengeService;
use App\Service\Notification\TotpEmailNotificationService;
use App\Service\Setup\SetupStateService;
use App\Tests\Support\SetupControllerTestFactory;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class SetupControllerTest extends TestCase
{
    /**
     * @brief Ensure setup index redirects when admin already exists.
     * @param void No input parameter.
     * @return void
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function testIndexRedirectsWhenAdminExists(): void
    {
        $setupStateService = $this->getMockBuilder(SetupStateService::class)->disableOriginalConstructor()->getMock();
        $setupStateService->method('hasConfirmedAdminUser')->willReturn(true);
        $controller = SetupControllerTestFactory::create(
            $setupStateService,
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(UserPasswordHasherInterface::class),
            $this->getMockBuilder(TotpChallengeService::class)->disableOriginalConstructor()->getMock(),
            $this->createMock(TotpEmailNotificationService::class),
        );

        $response = $controller->index(new Environment(new ArrayLoader(['setup/index.html.twig' => 'setup'])));

        self::assertSame(302, $response->getStatusCode());
    }

    /**
     * @brief Ensure setup admin creation rejects invalid payload.
     * @param void No input parameter.
     * @return void
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function testCreateAdminRejectsInvalidPayload(): void
    {
        $setupStateService = $this->getMockBuilder(SetupStateService::class)->disableOriginalConstructor()->getMock();
        $setupStateService->method('hasConfirmedAdminUser')->willReturn(false);
        $controller = SetupControllerTestFactory::create(
            $setupStateService,
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(UserPasswordHasherInterface::class),
            $this->getMockBuilder(TotpChallengeService::class)->disableOriginalConstructor()->getMock(),
            $this->createMock(TotpEmailNotificationService::class),
        );

        $request = SetupControllerTestFactory::withSetupSession(new Request([], ['email' => '', 'password' => '', 'pseudonym' => '']));
        $response = $controller->createAdmin($request);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/setup', (string) $response->headers->get('Location'));
    }

    /**
     * @brief Ensure setup admin creation rejects missing CSRF token.
     *
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testCreateAdminRejectsMissingCsrfToken(): void
    {
        $setupStateService = $this->getMockBuilder(SetupStateService::class)->disableOriginalConstructor()->getMock();
        $setupStateService->method('hasConfirmedAdminUser')->willReturn(false);
        $controller = SetupControllerTestFactory::create(
            $setupStateService,
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(UserPasswordHasherInterface::class),
            $this->getMockBuilder(TotpChallengeService::class)->disableOriginalConstructor()->getMock(),
            $this->createMock(TotpEmailNotificationService::class),
            csrfValid: false,
        );

        $response = $controller->createAdmin(new Request([], [
            'email' => 'admin@example.com',
            'password' => 'secret',
            'pseudonym' => 'Admin',
            '_csrf_token' => 'invalid',
        ]));

        self::assertSame('/setup', (string) $response->headers->get('Location'));
    }

    /**
     * @brief Ensure setup admin creation persists first administrator.
     * @param void No input parameter.
     * @return void
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function testCreateAdminPersistsFirstAdmin(): void
    {
        $setupStateService = $this->getMockBuilder(SetupStateService::class)->disableOriginalConstructor()->getMock();
        $setupStateService->method('hasConfirmedAdminUser')->willReturn(false);

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);
        $entityManager->expects(self::once())
            ->method('persist')
            ->with(self::callback(static function (mixed $user): bool {
                return $user instanceof User
                    && in_array('ROLE_ADMIN', $user->getRoles(), true)
                    && $user->isSetupConfirmed() === false;
            }));
        $entityManager->expects(self::once())->method('flush');

        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher->method('hashPassword')->willReturn('hashed-password');
        $totpChallengeService = $this->getMockBuilder(TotpChallengeService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createLoginChallenge'])
            ->getMock();
        $totpChallengeService->expects(self::once())
            ->method('createLoginChallenge');
        $totpEmailNotificationService = $this->createMock(TotpEmailNotificationService::class);
        $totpEmailNotificationService->expects(self::once())
            ->method('sendTotpCode');

        $controller = SetupControllerTestFactory::create(
            $setupStateService,
            $entityManager,
            $passwordHasher,
            $totpChallengeService,
            $totpEmailNotificationService,
        );
        $request = SetupControllerTestFactory::withSetupSession(new Request([], [
            'email' => 'admin@example.com',
            'password' => 'secret',
            'pseudonym' => 'Admin',
        ]));
        $response = $controller->createAdmin($request);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/setup/validate', (string) $response->headers->get('Location'));
        self::assertSame('admin@example.com', $request->getSession()->get('setup.pending_email'));
    }

    /**
     * @brief Ensure setup admin creation reuses pending admin with same email.
     * @param void No input parameter.
     * @return void
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function testCreateAdminReusesPendingAdminWithSameEmail(): void
    {
        $setupStateService = $this->getMockBuilder(SetupStateService::class)->disableOriginalConstructor()->getMock();
        $setupStateService->method('hasConfirmedAdminUser')->willReturn(false);
        $pendingAdmin = (new User())
            ->setEmail('admin@example.com')
            ->setRoles(['ROLE_ADMIN', 'ROLE_USER'])
            ->setSetupConfirmed(false);

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn($pendingAdmin);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::once())->method('flush');

        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher->method('hashPassword')->willReturn('rehashed-password');
        $totpChallengeService = $this->getMockBuilder(TotpChallengeService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createLoginChallenge'])
            ->getMock();
        $totpChallengeService->expects(self::once())
            ->method('createLoginChallenge')
            ->with('admin@example.com', self::isType('string'));
        $totpEmailNotificationService = $this->createMock(TotpEmailNotificationService::class);
        $totpEmailNotificationService->expects(self::once())
            ->method('sendTotpCode')
            ->with('admin@example.com', self::isType('string'));

        $controller = SetupControllerTestFactory::create(
            $setupStateService,
            $entityManager,
            $passwordHasher,
            $totpChallengeService,
            $totpEmailNotificationService,
        );
        $request = SetupControllerTestFactory::withSetupSession(new Request([], [
            'email' => 'Admin@Example.com',
            'password' => 'secret',
            'pseudonym' => 'Admin Reuse',
        ]));
        $response = $controller->createAdmin($request);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/setup/validate', (string) $response->headers->get('Location'));
        self::assertSame('admin@example.com', $pendingAdmin->getEmail());
        self::assertSame('Admin Reuse', $pendingAdmin->getPseudonym());
        self::assertFalse($pendingAdmin->isSetupConfirmed());
    }

    /**
     * @brief Ensure setup admin creation rejects reused email for non-pending user.
     * @param void No input parameter.
     * @return void
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function testCreateAdminRejectsExistingEmailOutsidePendingAdminCase(): void
    {
        $setupStateService = $this->getMockBuilder(SetupStateService::class)->disableOriginalConstructor()->getMock();
        $setupStateService->method('hasConfirmedAdminUser')->willReturn(false);
        $existingUser = (new User())
            ->setEmail('existing@example.com')
            ->setRoles(['ROLE_USER'])
            ->setSetupConfirmed(false);

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn($existingUser);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $totpChallengeService = $this->getMockBuilder(TotpChallengeService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createLoginChallenge'])
            ->getMock();
        $totpChallengeService->expects(self::never())->method('createLoginChallenge');
        $totpEmailNotificationService = $this->createMock(TotpEmailNotificationService::class);
        $totpEmailNotificationService->expects(self::never())->method('sendTotpCode');

        $controller = SetupControllerTestFactory::create(
            $setupStateService,
            $entityManager,
            $passwordHasher,
            $totpChallengeService,
            $totpEmailNotificationService,
        );
        $request = SetupControllerTestFactory::withSetupSession(new Request([], [
            'email' => 'existing@example.com',
            'password' => 'secret',
            'pseudonym' => 'Blocked',
        ]));
        $response = $controller->createAdmin($request);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/setup', (string) $response->headers->get('Location'));
    }

    /**
     * @brief Ensure setup validation page redirects when pending admin is missing.
     * @param void No input parameter.
     * @return void
     * @date 2026-04-26
     * @author Stephane H.
     */
    public function testValidatePageRedirectsWhenPendingAdminMissing(): void
    {
        $setupStateService = $this->getMockBuilder(SetupStateService::class)->disableOriginalConstructor()->getMock();
        $setupStateService->method('hasConfirmedAdminUser')->willReturn(false);
        $setupStateService->method('hasPendingAdminUserByEmail')->with('unknown@example.com')->willReturn(false);

        $controller = SetupControllerTestFactory::create(
            $setupStateService,
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(UserPasswordHasherInterface::class),
            $this->getMockBuilder(TotpChallengeService::class)->disableOriginalConstructor()->getMock(),
            $this->createMock(TotpEmailNotificationService::class),
        );

        $request = SetupControllerTestFactory::withSetupSession(new Request(), 'unknown@example.com', 'setup_validate');
        $response = $controller->validatePage(
            new Environment(new ArrayLoader(['setup/validate.html.twig' => 'unused'])),
            $request,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/setup', (string) $response->headers->get('Location'));
    }

    /**
     * @brief Ensure setup validation submit redirects when pending admin is missing.
     * @param void No input parameter.
     * @return void
     * @date 2026-04-26
     * @author Stephane H.
     */
    public function testValidateSubmitRedirectsWhenPendingAdminMissing(): void
    {
        $setupStateService = $this->getMockBuilder(SetupStateService::class)->disableOriginalConstructor()->getMock();
        $setupStateService->method('hasConfirmedAdminUser')->willReturn(false);
        $setupStateService->method('hasPendingAdminUserByEmail')->with('unknown@example.com')->willReturn(false);

        $totpChallengeService = $this->getMockBuilder(TotpChallengeService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['validateLoginChallenge'])
            ->getMock();
        $totpChallengeService->expects(self::never())->method('validateLoginChallenge');
        $controller = SetupControllerTestFactory::create(
            $setupStateService,
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(UserPasswordHasherInterface::class),
            $totpChallengeService,
            $this->createMock(TotpEmailNotificationService::class),
        );

        $request = SetupControllerTestFactory::withSetupSession(
            new Request([], ['email' => 'unknown@example.com', 'totp' => '123456']),
            'unknown@example.com',
            'setup_validate',
        );
        $response = $controller->validateSubmit($request);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/setup', (string) $response->headers->get('Location'));
    }
}
