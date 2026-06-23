<?php

namespace App\Tests\Functional\Security;

use App\Entity\LoginTotpChallenge;
use App\Entity\User;
use App\Repository\LoginTotpChallengeRepository;
use App\Service\Auth\TotpChallengeService;
use App\Service\Auth\TotpFlowDebugLogger;
use DateInterval;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class TotpChallengeServiceTest extends TestCase
{
    /**
     * @brief Ensure invalid TOTP code is rejected.
     * @return void
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function testInvalidTotpCode(): void
    {
        $repository = $this->createMock(LoginTotpChallengeRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($this->createMock(EntityRepository::class));
        $service = $this->createService($repository, $entityManager);

        self::assertFalse($service->validate('111111', '222222'));
    }

    /**
     * @brief Ensure valid TOTP code is accepted.
     * @return void
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function testValidTotpCode(): void
    {
        $repository = $this->createMock(LoginTotpChallengeRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($this->createMock(EntityRepository::class));
        $service = $this->createService($repository, $entityManager);

        self::assertTrue($service->validate('333333', '333333'));
    }

    /**
     * @brief Ensure login challenge can be consumed once.
     * @return void
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function testValidateLoginChallengeConsumesChallenge(): void
    {
        $now = new DateTimeImmutable();
        $challenge = new LoginTotpChallenge(
            'admin@example.com',
            password_hash('123456', PASSWORD_DEFAULT),
            $now->add(new DateInterval('PT5M')),
            $now
        );

        $repository = $this->createMock(LoginTotpChallengeRepository::class);
        $repository->expects(self::once())
            ->method('findLatestActiveByIdentity')
            ->with('admin@example.com', self::isInstanceOf(DateTimeImmutable::class))
            ->willReturn($challenge);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $userRepository = $this->createMock(EntityRepository::class);
        $userRepository->expects(self::once())
            ->method('findOneBy')
            ->with(['email' => 'admin@example.com'])
            ->willReturn((new User())->setEmail('admin@example.com')->setRoles(['ROLE_ADMIN'])->setSetupConfirmed(false));
        $entityManager->method('getRepository')->willReturn($userRepository);
        $entityManager->expects(self::once())->method('flush');

        $service = $this->createService($repository, $entityManager);

        self::assertTrue($service->validateLoginChallenge('admin@example.com', '123456'));
        self::assertTrue($challenge->isConsumed());
    }

    /**
     * @brief Ensure non numeric TOTP input is rejected.
     * @return void
     * @date 2026-04-26
     * @author Stephane H.
     */
    public function testValidateLoginChallengeRejectsNonNumericCode(): void
    {
        $repository = $this->createMock(LoginTotpChallengeRepository::class);
        $repository->expects(self::never())->method('findLatestActiveByIdentity');
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($this->createMock(EntityRepository::class));

        $service = $this->createService($repository, $entityManager);

        self::assertFalse($service->validateLoginChallenge('admin@example.com', 'ABCDEF'));
    }

    /**
     * @brief Ensure challenge creation rejects invalid payload.
     * @return void
     * @date 2026-04-26
     * @author Stephane H.
     */
    public function testCreateLoginChallengeRejectsInvalidPayload(): void
    {
        $repository = $this->createMock(LoginTotpChallengeRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($this->createMock(EntityRepository::class));

        $service = $this->createService($repository, $entityManager);

        $this->expectException(InvalidArgumentException::class);
        $service->createLoginChallenge('', '12345');
    }

    /**
     * @brief Ensure resend is blocked during cooldown.
     * @return void
     * @date 2026-06-15
     * @author Stephane H.
     */
    public function testResendLoginChallengeRejectsCooldown(): void
    {
        $now = new DateTimeImmutable();
        $challenge = new LoginTotpChallenge(
            'admin@example.com',
            password_hash('123456', PASSWORD_DEFAULT),
            $now->add(new DateInterval('PT5M')),
            $now
        );

        $repository = $this->createMock(LoginTotpChallengeRepository::class);
        $repository->method('findLatestPendingByIdentity')
            ->with('admin@example.com')
            ->willReturn($challenge);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($this->createMock(EntityRepository::class));

        $service = $this->createService($repository, $entityManager, 300, 60, 3);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('auth.totp.challenge.cooldown');
        $service->resendLoginChallenge('admin@example.com');
    }

    /**
     * @brief Ensure resend updates active challenge and returns a new code.
     * @return void
     * @date 2026-06-15
     * @author Stephane H.
     */
    public function testResendLoginChallengeUpdatesActiveChallenge(): void
    {
        $sentAt = (new DateTimeImmutable())->sub(new DateInterval('PT2M'));
        $challenge = new LoginTotpChallenge(
            'admin@example.com',
            password_hash('123456', PASSWORD_DEFAULT),
            $sentAt->add(new DateInterval('PT5M')),
            $sentAt
        );

        $repository = $this->createMock(LoginTotpChallengeRepository::class);
        $repository->method('findLatestPendingByIdentity')
            ->with('admin@example.com')
            ->willReturn($challenge);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($this->createMock(EntityRepository::class));
        $entityManager->expects(self::once())->method('flush');

        $service = $this->createService($repository, $entityManager, 300, 60, 3);
        $code = $service->resendLoginChallenge('admin@example.com');

        self::assertMatchesRegularExpression('/^\d{6}$/', $code);
        self::assertFalse(password_verify('123456', $challenge->getCodeHash()));
        self::assertTrue(password_verify($code, $challenge->getCodeHash()));
        self::assertSame(1, $challenge->getResendCount());
    }

    /**
     * @brief Ensure resend state exposes remaining cooldown seconds.
     * @return void
     * @date 2026-06-15
     * @author Stephane H.
     */
    public function testGetResendStateReturnsRetryAfterSeconds(): void
    {
        $sentAt = (new DateTimeImmutable())->sub(new DateInterval('PT30S'));
        $challenge = new LoginTotpChallenge(
            'admin@example.com',
            password_hash('123456', PASSWORD_DEFAULT),
            $sentAt->add(new DateInterval('PT5M')),
            $sentAt
        );

        $repository = $this->createMock(LoginTotpChallengeRepository::class);
        $repository->method('findLatestPendingByIdentity')
            ->with('admin@example.com')
            ->willReturn($challenge);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($this->createMock(EntityRepository::class));

        $service = $this->createService($repository, $entityManager, 300, 60, 3);
        $state = $service->getResendState('admin@example.com');

        self::assertFalse($state['canResend']);
        self::assertGreaterThan(0, $state['retryAfterSeconds']);
        self::assertFalse($state['rateLimited']);
    }

    /**
     * @brief Build TOTP challenge service with mocked persistence layer.
     *
     * @param LoginTotpChallengeRepository $repository Challenge repository mock.
     * @param EntityManagerInterface $entityManager Entity manager mock.
     * @param int $defaultTtlSeconds Challenge lifetime in seconds.
     * @param int $resendCooldownSeconds Resend cooldown in seconds.
     * @param int $maxResendCount Maximum resend attempts.
     * @return TotpChallengeService
     * @date 2026-06-22
     * @author Stephane H.
     */
    private function createService(
        LoginTotpChallengeRepository $repository,
        EntityManagerInterface $entityManager,
        int $defaultTtlSeconds = 300,
        int $resendCooldownSeconds = 60,
        int $maxResendCount = 3,
    ): TotpChallengeService {
        return new TotpChallengeService(
            $repository,
            $entityManager,
            new TotpFlowDebugLogger(new NullLogger(), false),
            $defaultTtlSeconds,
            $resendCooldownSeconds,
            $maxResendCount,
        );
    }
}
