<?php

namespace App\Tests\Functional\Invite;

use App\Entity\User;
use App\Entity\UserInvitationToken;
use App\Repository\UserInvitationTokenRepository;
use App\Service\Auth\UserInvitationService;
use App\Service\Notification\InvitationEmailNotificationService;
use DateInterval;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class UserInvitationServiceTest extends TestCase
{
    /**
     * @brief Ensure invitation creation returns activation URL.
     * @return void
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function testInviteUserReturnsActivationUrl(): void
    {
        $capturedUser = null;
        $userRepository = $this->createMock(EntityRepository::class);
        $userRepository->method('findOneBy')->willReturn(null);
        $userRepository->method('find')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($userRepository);
        $entityManager->expects(self::exactly(2))
            ->method('persist')
            ->with(self::callback(static function (mixed $entity) use (&$capturedUser): bool {
                if ($entity instanceof User) {
                    $capturedUser = $entity;
                    return true;
                }

                return $entity instanceof UserInvitationToken;
            }));
        $entityManager->expects(self::exactly(2))
            ->method('flush')
            ->willReturnCallback(static function () use (&$capturedUser): void {
                if ($capturedUser instanceof User) {
                    $reflectionProperty = new \ReflectionProperty(User::class, 'id');
                    $reflectionProperty->setValue($capturedUser, 42);
                }
            });

        $invitationRepository = $this->createMock(UserInvitationTokenRepository::class);
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher->method('hashPassword')->willReturn('hashed');
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('http://localhost/invite/activate/token');
        $notificationService = new InvitationEmailNotificationService();

        $service = new UserInvitationService(
            $entityManager,
            $invitationRepository,
            $passwordHasher,
            $urlGenerator,
            $notificationService,
            3600
        );

        $url = $service->inviteUser('invite@example.com', 'Invite User', 1);

        self::assertSame('http://localhost/invite/activate/token', $url);
        self::assertCount(1, $notificationService->getMessages());
    }

    /**
     * @brief Ensure activation rejects invalid token.
     * @return void
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function testActivateInvitationRejectsInvalidToken(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $invitationRepository = $this->createMock(UserInvitationTokenRepository::class);
        $invitationRepository->method('findActiveByTokenHash')->willReturn(null);
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $notificationService = new InvitationEmailNotificationService();
        $service = new UserInvitationService(
            $entityManager,
            $invitationRepository,
            $passwordHasher,
            $urlGenerator,
            $notificationService
        );

        self::assertFalse($service->activateInvitation('invalid-token', 'new-password'));
    }

    /**
     * @brief Ensure activation consumes valid invitation token.
     * @return void
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function testActivateInvitationConsumesToken(): void
    {
        $token = 'abc123token';
        $tokenHash = hash('sha256', $token);
        $invitation = new UserInvitationToken(
            99,
            'invite@example.com',
            $tokenHash,
            1,
            new DateTimeImmutable(),
            (new DateTimeImmutable())->add(new DateInterval('PT1H'))
        );

        $user = new User();
        $user->setEmail('invite@example.com');
        $user->setPseudonym('Invite User');
        $user->setPassword('old-password');

        $userRepository = $this->createMock(EntityRepository::class);
        $userRepository->method('find')->with(99)->willReturn($user);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($userRepository);
        $entityManager->expects(self::once())->method('flush');

        $invitationRepository = $this->createMock(UserInvitationTokenRepository::class);
        $invitationRepository->method('findActiveByTokenHash')
            ->with($tokenHash, self::isInstanceOf(DateTimeImmutable::class))
            ->willReturn($invitation);

        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher->method('hashPassword')->willReturn('new-password-hash');
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $notificationService = new InvitationEmailNotificationService();
        $service = new UserInvitationService(
            $entityManager,
            $invitationRepository,
            $passwordHasher,
            $urlGenerator,
            $notificationService
        );

        self::assertTrue($service->activateInvitation($token, 'new-password'));
        self::assertTrue($invitation->isConsumed());
        self::assertSame('new-password-hash', $user->getPassword());
    }

    /**
     * @brief Ensure invitation propagates explicit locale to notification service.
     * @return void
     * @date 2026-04-28
     * @author Stephane H.
     */
    public function testInviteUserPropagatesRequestedLocale(): void
    {
        $capturedUser = null;
        $userRepository = $this->createMock(EntityRepository::class);
        $userRepository->method('findOneBy')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($userRepository);
        $entityManager->expects(self::exactly(2))
            ->method('persist')
            ->with(self::callback(static function (mixed $entity) use (&$capturedUser): bool {
                if ($entity instanceof User) {
                    $capturedUser = $entity;
                }

                return $entity instanceof User || $entity instanceof UserInvitationToken;
            }));
        $entityManager->expects(self::exactly(2))
            ->method('flush')
            ->willReturnCallback(static function () use (&$capturedUser): void {
                if ($capturedUser instanceof User) {
                    $reflectionProperty = new \ReflectionProperty(User::class, 'id');
                    $reflectionProperty->setValue($capturedUser, 501);
                }
            });

        $invitationRepository = $this->createMock(UserInvitationTokenRepository::class);
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher->method('hashPassword')->willReturn('hashed');
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('http://localhost/invite/activate/token');
        $notificationService = $this->createMock(InvitationEmailNotificationService::class);
        $notificationService->expects(self::once())
            ->method('sendInvitation')
            ->with('invite@example.com', 'http://localhost/invite/activate/token', 'de');

        $service = new UserInvitationService(
            $entityManager,
            $invitationRepository,
            $passwordHasher,
            $urlGenerator,
            $notificationService,
            3600
        );
        $service->inviteUser('invite@example.com', 'Invite User', 1, 'de');
    }

    /**
     * @brief Ensure invitation locale falls back to default locale.
     * @return void
     * @date 2026-04-28
     * @author Stephane H.
     */
    public function testInviteUserFallsBackToDefaultLocaleWhenInvalid(): void
    {
        $capturedUser = null;
        $userRepository = $this->createMock(EntityRepository::class);
        $userRepository->method('findOneBy')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($userRepository);
        $entityManager->expects(self::exactly(2))
            ->method('persist')
            ->with(self::callback(static function (mixed $entity) use (&$capturedUser): bool {
                if ($entity instanceof User) {
                    $capturedUser = $entity;
                }

                return $entity instanceof User || $entity instanceof UserInvitationToken;
            }));
        $entityManager->expects(self::exactly(2))
            ->method('flush')
            ->willReturnCallback(static function () use (&$capturedUser): void {
                if ($capturedUser instanceof User) {
                    $reflectionProperty = new \ReflectionProperty(User::class, 'id');
                    $reflectionProperty->setValue($capturedUser, 777);
                }
            });

        $invitationRepository = $this->createMock(UserInvitationTokenRepository::class);
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher->method('hashPassword')->willReturn('hashed');
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('http://localhost/invite/activate/token');
        $notificationService = $this->createMock(InvitationEmailNotificationService::class);
        $notificationService->expects(self::once())
            ->method('sendInvitation')
            ->with('invite@example.com', 'http://localhost/invite/activate/token', 'en');

        $service = new UserInvitationService(
            $entityManager,
            $invitationRepository,
            $passwordHasher,
            $urlGenerator,
            $notificationService,
            3600,
            ['fr', 'en', 'de', 'lt', 'no'],
            'en',
            'fr'
        );
        $service->inviteUser('invite@example.com', 'Invite User', 1, 'es');
    }
}
