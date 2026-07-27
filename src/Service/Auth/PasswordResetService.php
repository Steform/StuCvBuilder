<?php

namespace App\Service\Auth;

use App\Entity\PasswordResetRequest;
use App\Entity\User;
use App\Repository\PasswordResetRequestRepository;
use App\Repository\UserRepository;
use App\Service\Admin\TrustedDeviceAdminService;
use App\Service\Notification\PasswordResetEmailNotificationService;
use DateInterval;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Service PasswordResetService.
 */
class PasswordResetService
{
    private const TOKEN_TTL_SECONDS = 3600;

    /**
     * @brief Build password reset service.
     * @param EntityManagerInterface $entityManager Doctrine entity manager.
     * @param UserRepository $userRepository User repository.
     * @param PasswordResetRequestRepository $passwordResetRequestRepository Password reset repository.
     * @param UserPasswordHasherInterface $passwordHasher User password hasher.
     * @param UrlGeneratorInterface $urlGenerator Router URL generator.
     * @param PasswordResetEmailNotificationService $passwordResetEmailNotificationService Password reset email sender.
     * @param TrustedDeviceAdminService $trustedDeviceAdminService Trusted device admin service.
     * @param array<int, string> $supportedLocales Supported locale list.
     * @param string $defaultLocale Default locale.
     * @param string $fallbackLocale Fallback locale.
     * @return void
     * @date 2026-07-27
     * @author Stephane H.
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly PasswordResetRequestRepository $passwordResetRequestRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly PasswordResetEmailNotificationService $passwordResetEmailNotificationService,
        private readonly TrustedDeviceAdminService $trustedDeviceAdminService,
        private readonly array $supportedLocales = ['fr', 'en', 'de', 'lt', 'nb'],
        private readonly string $defaultLocale = 'en',
        private readonly string $fallbackLocale = 'fr'
    ) {
    }

    /**
     * @brief Request a self-service password reset when the account is active.
     * @param string $email Target account email.
     * @param string|null $locale Preferred email locale.
     * @return void
     * @date 2026-07-27
     * @author Stephane H.
     */
    public function requestReset(string $email, ?string $locale = null): void
    {
        $normalizedEmail = strtolower(trim($email));
        if ($normalizedEmail === '') {
            return;
        }

        $user = $this->userRepository->findOneBy(['email' => $normalizedEmail]);
        if (!$user instanceof User || !$user->isActive() || $user->getId() === null) {
            return;
        }

        $this->createAndSendReset($user, false, $locale);
    }

    /**
     * @brief Force a password reset for an admin target user and send email.
     * @param User $targetUser Target user.
     * @param string|null $locale Preferred email locale.
     * @return string Plain reset token.
     * @date 2026-07-27
     * @author Stephane H.
     */
    public function forceReset(User $targetUser, ?string $locale = null): string
    {
        return $this->createAndSendReset($targetUser, true, $locale);
    }

    /**
     * @brief Set an admin-provided temporary password and require change on next login.
     * @param User $targetUser Target user.
     * @param string $plainPassword Temporary plain password.
     * @return string|null Translation key on failure or null on success.
     * @date 2026-07-27
     * @author Stephane H.
     */
    public function setTemporaryPassword(User $targetUser, string $plainPassword): ?string
    {
        $password = trim($plainPassword);
        if ($password === '' || $targetUser->getId() === null) {
            return 'admin.users.error.password_required';
        }

        $targetUser->setPassword($this->passwordHasher->hashPassword($targetUser, $password));
        $targetUser->setPasswordResetRequired(true);
        $targetUser->bumpSessionVersion();
        $this->passwordResetRequestRepository->invalidateActiveForUser((int) $targetUser->getId());
        $this->entityManager->flush();
        $this->trustedDeviceAdminService->revokeAll((int) $targetUser->getId());

        return null;
    }

    /**
     * @brief Complete a forced password change for an authenticated user.
     * @param User $user Authenticated user.
     * @param string $newPassword New password value.
     * @return string|null Translation key on failure or null on success.
     * @date 2026-07-27
     * @author Stephane H.
     */
    public function completeForcedPasswordChange(User $user, string $newPassword): ?string
    {
        $password = trim($newPassword);
        if ($password === '' || $user->getId() === null) {
            return 'security.forced_password_change.password_required';
        }
        if (!$user->isPasswordResetRequired()) {
            return 'security.forced_password_change.not_required';
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setPasswordResetRequired(false);
        $user->bumpSessionVersion();
        $this->passwordResetRequestRepository->invalidateActiveForUser((int) $user->getId());
        $this->entityManager->flush();
        $this->trustedDeviceAdminService->revokeAll((int) $user->getId());

        return null;
    }

    /**
     * @brief Whether a plain reset token is currently valid.
     * @param string $plainToken Plain reset token from URL.
     * @return bool
     * @date 2026-07-27
     * @author Stephane H.
     */
    public function isTokenValid(string $plainToken): bool
    {
        return $this->findActiveRequest(trim($plainToken)) instanceof PasswordResetRequest;
    }

    /**
     * @brief Consume a valid token and set the new password.
     * @param string $plainToken Plain reset token from URL.
     * @param string $newPassword New password value.
     * @return bool
     * @date 2026-07-27
     * @author Stephane H.
     */
    public function resetPassword(string $plainToken, string $newPassword): bool
    {
        $password = trim($newPassword);
        if ($password === '') {
            return false;
        }

        $resetRequest = $this->findActiveRequest(trim($plainToken));
        if (!$resetRequest instanceof PasswordResetRequest) {
            return false;
        }

        $user = $this->userRepository->find($resetRequest->getUserId());
        if (!$user instanceof User || !$user->isActive()) {
            return false;
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setPasswordResetRequired(false);
        $user->bumpSessionVersion();
        $resetRequest->consume();
        $this->entityManager->flush();
        $this->trustedDeviceAdminService->revokeAll((int) $user->getId());

        return true;
    }

    /**
     * @brief Create a reset token, persist it, and send the email.
     * @param User $user Target user.
     * @param bool $requireReset Whether to invalidate current password and require change.
     * @param string|null $locale Preferred email locale.
     * @return string Plain reset token.
     * @date 2026-07-27
     * @author Stephane H.
     */
    private function createAndSendReset(User $user, bool $requireReset, ?string $locale): string
    {
        if ($user->getId() === null) {
            throw new \RuntimeException('password_reset.user_missing');
        }

        if ($requireReset) {
            // Randomize password so the previous credential cannot be reused after force-reset.
            $user->setPassword($this->passwordHasher->hashPassword($user, bin2hex(random_bytes(32))));
            $user->setPasswordResetRequired(true);
        }

        $this->passwordResetRequestRepository->invalidateActiveForUser((int) $user->getId());

        $token = bin2hex(random_bytes(32));
        $resetRequest = new PasswordResetRequest(
            (int) $user->getId(),
            hash('sha256', $token),
            (new DateTimeImmutable())->add(new DateInterval(sprintf('PT%dS', self::TOKEN_TTL_SECONDS)))
        );
        $this->entityManager->persist($resetRequest);
        $this->entityManager->flush();

        $resetUrl = $this->urlGenerator->generate(
            'reset_password',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
        $resolvedLocale = $this->resolveLocale($locale);
        $this->passwordResetEmailNotificationService->sendPasswordReset(
            $user->getEmail(),
            $resetUrl,
            $resolvedLocale
        );

        return $token;
    }

    /**
     * @brief Find an active reset request from a plain token.
     * @param string $plainToken Plain reset token.
     * @return PasswordResetRequest|null
     * @date 2026-07-27
     * @author Stephane H.
     */
    private function findActiveRequest(string $plainToken): ?PasswordResetRequest
    {
        if ($plainToken === '') {
            return null;
        }

        return $this->passwordResetRequestRepository->findActiveByTokenHash(
            hash('sha256', $plainToken),
            new DateTimeImmutable()
        );
    }

    /**
     * @brief Resolve email locale with supported fallbacks.
     * @param string|null $requestedLocale Requested locale candidate.
     * @return string
     * @date 2026-07-27
     * @author Stephane H.
     */
    private function resolveLocale(?string $requestedLocale): string
    {
        $normalizedLocale = strtolower(trim((string) $requestedLocale));
        if (in_array($normalizedLocale, $this->supportedLocales, true)) {
            return $normalizedLocale;
        }
        if (in_array($this->defaultLocale, $this->supportedLocales, true)) {
            return $this->defaultLocale;
        }
        if (in_array($this->fallbackLocale, $this->supportedLocales, true)) {
            return $this->fallbackLocale;
        }

        return $this->supportedLocales[0] ?? 'en';
    }
}
