<?php

namespace App\Tests\Functional\Auth;

use App\Service\Auth\TotpChallengeService;
use App\Service\Notification\TotpEmailNotificationService;
use App\Service\Setup\SetupStateService;
use App\Tests\Support\SetupControllerTestFactory;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class SetupFlowTest extends TestCase
{
    /**
     * @brief Ensure setup initializes and locks state.
     * @param void No input parameter.
     * @return void
     * @date 2026-04-22
     * @author Stephane H.
     */
    public function testInitializeLocksSetup(): void
    {
        $setupStateService = $this->getMockBuilder(SetupStateService::class)->disableOriginalConstructor()->getMock();
        $setupStateService->method('hasConfirmedAdminUser')->willReturn(false);
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn(null);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher->method('hashPassword')->willReturn('hashed');
        $totpChallengeService = $this->getMockBuilder(TotpChallengeService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createLoginChallenge'])
            ->getMock();
        $totpChallengeService->method('createLoginChallenge');
        $totpEmailNotificationService = $this->createMock(TotpEmailNotificationService::class);
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
            'pseudonym' => 'admin',
        ]));

        $response = $controller->createAdmin($request);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/setup/validate', (string) $response->headers->get('Location'));
    }
}
