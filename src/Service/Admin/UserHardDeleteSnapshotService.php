<?php

namespace App\Service\Admin;

use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service UserHardDeleteSnapshotService.
 */
class UserHardDeleteSnapshotService
{
    /**
     * @brief Build hard delete snapshot collection service.
     * @param EntityManagerInterface $entityManager Doctrine entity manager.
     * @return void
     * @date 2026-04-28
     * @author Stephane H.
     */
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * @brief Build restorable payload for target user hard delete (auth and user-linked CV data).
     * @param User $targetUser Target user aggregate.
     * @return array<string, mixed>
     * @date 2026-04-28
     * @author Stephane H.
     */
    public function buildSnapshotPayload(User $targetUser): array
    {
        $userId = (int) $targetUser->getId();
        $connection = $this->entityManager->getConnection();

        return [
            'snapshotCreatedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
            'targetUserId' => $userId,
            'user' => $connection->fetchAssociative('SELECT * FROM app_user WHERE id = :uid LIMIT 1', ['uid' => $userId]) ?: null,
            'trustedDevices' => $connection->fetchAllAssociative('SELECT * FROM trusted_device WHERE user_id = :uid ORDER BY id ASC', ['uid' => $userId]),
            'passwordResetRequests' => $connection->fetchAllAssociative('SELECT * FROM password_reset_request WHERE user_id = :uid ORDER BY id ASC', ['uid' => $userId]),
            'profileEmailChangeRequests' => $connection->fetchAllAssociative('SELECT * FROM profile_email_change_request WHERE user_id = :uid ORDER BY id ASC', ['uid' => $userId]),
            'invitationTokensAsInvitee' => $connection->fetchAllAssociative('SELECT * FROM user_invitation_token WHERE user_id = :uid ORDER BY id ASC', ['uid' => $userId]),
            'invitationTokensAsInviter' => $connection->fetchAllAssociative('SELECT * FROM user_invitation_token WHERE invited_by_user_id = :uid ORDER BY id ASC', ['uid' => $userId]),
            'bugReports' => $connection->fetchAllAssociative('SELECT * FROM bug_report WHERE reporter_user_id = :uid ORDER BY id ASC', ['uid' => $userId]),
        ];
    }
}
