<?php

namespace App\Tests\Functional\Profile;

use App\Entity\User;
use App\Repository\ProfileEmailChangeRequestRepository;
use App\Repository\UserRepository;
use App\Service\Admin\TrustedDeviceAdminService;
use App\Service\Auth\TotpChallengeService;
use App\Service\Auth\TotpFlowDebugLogger;
use App\Service\Notification\TotpEmailNotificationService;
use App\Service\Profile\ProfileUpdateService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ProfileUpdateServiceTest extends TestCase
{
    /**
     * @brief Ensure pseudonym update rejects empty values.
     * @return void
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function testUpdatePseudonymRejectsEmptyValue(): void
    {
        $service = $this->createService();
        $user = (new User())->setEmail('user@example.com')->setPseudonym('old');

        $errorKey = $service->updatePseudonym($user, '   ');

        self::assertSame('profile.error.pseudonym_required', $errorKey);
    }

    /**
     * @brief Ensure pseudonym update persists valid value.
     * @return void
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function testUpdatePseudonymPersistsValidValue(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');
        $service = $this->createService($entityManager);
        $user = (new User())->setEmail('user@example.com')->setPseudonym('old');

        $errorKey = $service->updatePseudonym($user, 'new-pseudonym');

        self::assertNull($errorKey);
        self::assertSame('new-pseudonym', $user->getPseudonym());
    }

    /**
     * @brief Build profile update service with mocked dependencies.
     * @param EntityManagerInterface|null $entityManager Optional entity manager mock.
     * @return ProfileUpdateService
     * @date 2026-04-23
     * @author Stephane H.
     */
    private function createService(?EntityManagerInterface $entityManager = null): ProfileUpdateService
    {
        $entityManager ??= $this->createMock(EntityManagerInterface::class);
        $userRepository = $this->createMock(UserRepository::class);
        $emailChangeRequestRepository = $this->createMock(ProfileEmailChangeRequestRepository::class);
        $totpChallengeService = $this->getMockBuilder(TotpChallengeService::class)->disableOriginalConstructor()->getMock();
        $totpEmailNotificationService = $this->createMock(TotpEmailNotificationService::class);
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $trustedDeviceAdminService = $this->getMockBuilder(TrustedDeviceAdminService::class)->disableOriginalConstructor()->getMock();

        return new ProfileUpdateService(
            $entityManager,
            $userRepository,
            $emailChangeRequestRepository,
            $totpChallengeService,
            $totpEmailNotificationService,
            $passwordHasher,
            $trustedDeviceAdminService,
            new TotpFlowDebugLogger(new \Psr\Log\NullLogger(), false),
        );
    }
}
