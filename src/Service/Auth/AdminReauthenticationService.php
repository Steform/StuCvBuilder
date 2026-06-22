<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\User;
use App\Service\Notification\TotpEmailNotificationService;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * @brief Re-authenticate admins before destructive backup operations.
 */
final class AdminReauthenticationService
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly TotpChallengeService $totpChallengeService,
        private readonly TotpEmailNotificationService $totpEmailNotificationService,
        private readonly TotpFlowDebugLogger $totpFlowDebugLogger,
    ) {
    }

    /**
     * @brief Send a one-time TOTP code for backup re-authentication.
     *
     * @param User $user Authenticated admin user.
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function sendReauthenticationTotp(User $user): void
    {
        $code = (string) random_int(100000, 999999);
        $identity = $this->buildIdentity($user);
        $this->totpFlowDebugLogger->log('admin_reauth_totp_start', [
            'userId' => $user->getId(),
            'email' => $user->getEmail(),
            'identity' => $identity,
            'totpCode' => $code,
        ]);
        $this->totpChallengeService->createLoginChallenge($identity, $code);
        $this->totpEmailNotificationService->sendTotpCode($user->getEmail(), $code);
        $this->totpFlowDebugLogger->log('admin_reauth_totp_dispatched', [
            'userId' => $user->getId(),
            'email' => $user->getEmail(),
        ]);
    }

    /**
     * @brief Validate admin password and TOTP before backup restore/reset.
     *
     * @param User $user Authenticated admin user.
     * @param string $password Submitted current password.
     * @param string $totpCode Submitted TOTP code.
     * @return string|null Translation key on failure, null when valid.
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function validate(User $user, string $password, string $totpCode): ?string
    {
        if (!$this->passwordHasher->isPasswordValid($user, trim($password))) {
            return 'dashboard.customization_backup.flash.reauth_password_invalid';
        }

        if (!$this->totpChallengeService->validateLoginChallenge($this->buildIdentity($user), trim($totpCode))) {
            $this->totpFlowDebugLogger->log('admin_reauth_totp_validate_failed', [
                'userId' => $user->getId(),
                'email' => $user->getEmail(),
            ]);

            return 'dashboard.customization_backup.flash.reauth_totp_invalid';
        }

        $this->totpFlowDebugLogger->log('admin_reauth_totp_validate_success', [
            'userId' => $user->getId(),
            'email' => $user->getEmail(),
        ]);

        return null;
    }

    /**
     * @brief Build TOTP identity key for backup re-authentication.
     *
     * @param User $user Authenticated admin user.
     * @return string
     * @date 2026-06-21
     * @author Stephane H.
     */
    private function buildIdentity(User $user): string
    {
        return sprintf('admin-backup-reauth:%d:%d', (int) $user->getId(), $user->getSessionVersion());
    }
}
