<?php

namespace App\Service\Admin;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Service UserHardDeletePurgeService.
 */
class UserHardDeletePurgeService
{
    /**
     * @brief Build hard delete purge executor service.
     * @param EntityManagerInterface $entityManager Doctrine entity manager.
     * @return void
     * @date 2026-04-28
     * @author Stephane H.
     */
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * @brief Purge all functional rows linked to target user (auth and user-scoped CV data).
     * @param array<string, mixed> $snapshotPayload Snapshot payload.
     * @return array<string, int>
     * @date 2026-05-02
     * @author Stephane H.
     */
    public function purgeFromSnapshot(array $snapshotPayload): array
    {
        $userId = (int) ($snapshotPayload['targetUserId'] ?? 0);
        $connection = $this->entityManager->getConnection();

        return $this->entityManager->wrapInTransaction(function () use ($connection, $userId, $snapshotPayload): array {
            $counts = [
                'trustedDevices' => $connection->executeStatement('DELETE FROM trusted_device WHERE user_id = :uid', ['uid' => $userId]),
                'passwordResetRequests' => $connection->executeStatement('DELETE FROM password_reset_request WHERE user_id = :uid', ['uid' => $userId]),
                'profileEmailChangeRequests' => $connection->executeStatement('DELETE FROM profile_email_change_request WHERE user_id = :uid', ['uid' => $userId]),
                'invitationTokensAsInvitee' => $connection->executeStatement('DELETE FROM user_invitation_token WHERE user_id = :uid', ['uid' => $userId]),
                'invitationTokensAsInviter' => $connection->executeStatement('DELETE FROM user_invitation_token WHERE invited_by_user_id = :uid', ['uid' => $userId]),
                'bugReports' => $connection->executeStatement('DELETE FROM bug_report WHERE reporter_user_id = :uid', ['uid' => $userId]),
            ];

            $userEmail = is_array($snapshotPayload['user'] ?? null)
                ? strtolower(trim((string) ($snapshotPayload['user']['email'] ?? '')))
                : '';
            if ($userEmail !== '') {
                $counts['loginTotpChallenges'] = $connection->executeStatement(
                    'DELETE FROM login_totp_challenge WHERE identity = :email',
                    ['email' => $userEmail]
                );
            } else {
                $counts['loginTotpChallenges'] = 0;
            }

            $counts['user'] = $connection->executeStatement('DELETE FROM app_user WHERE id = :uid', ['uid' => $userId]);

            return $counts;
        });
    }
}
