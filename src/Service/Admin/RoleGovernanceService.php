<?php

namespace App\Service\Admin;

use App\Entity\User;
use App\Repository\UserRepository;

/**
 * Service RoleGovernanceService.
 */
class RoleGovernanceService
{
    /**
     * @brief Build role governance service.
     * @param UserRepository $userRepository User repository.
     * @param array<int, string> $allowedRoles Allowed role list.
     * @return void
     * @date 2026-06-09
     * @author Stephane H.
     */
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly array $allowedRoles = ['ROLE_USER', 'ROLE_ADMIN', 'ROLE_CV_EDIT', 'ROLE_CV_CONSULT', 'ROLE_TUILE'],
    ) {
    }

    /**
     * @brief Return allowed roles for admin assignment.
     * @param void No input parameter.
     * @return array<int, string>
     * @date 2026-06-09
     * @author Stephane H.
     */
    public function getAllowedRoles(): array
    {
        return $this->allowedRoles;
    }

    /**
     * @brief Validate role change governance constraints.
     * @param User $actorUser Current admin actor.
     * @param User $targetUser User receiving role update.
     * @param array<int, string> $requestedRoles Roles to apply.
     * @return string|null Translation key on error or null.
     * @date 2026-06-09
     * @author Stephane H.
     */
    public function validateRoleChange(User $actorUser, User $targetUser, array $requestedRoles): ?string
    {
        if ($actorUser->getId() !== null && $targetUser->getId() === $actorUser->getId()) {
            return 'admin.users.error.self_role_change_forbidden';
        }

        foreach ($requestedRoles as $role) {
            if (!in_array($role, $this->allowedRoles, true)) {
                return 'admin.users.error.invalid_role';
            }
        }

        $isTargetAdmin = in_array('ROLE_ADMIN', $targetUser->getRoles(), true);
        $willRemainAdmin = in_array('ROLE_ADMIN', $requestedRoles, true);
        if ($isTargetAdmin && !$willRemainAdmin && $targetUser->isActive() && $this->userRepository->countActiveAdmins() <= 1) {
            return 'admin.users.error.last_admin_protected';
        }

        return null;
    }

    /**
     * @brief Validate user deactivation governance constraints.
     * @param User $targetUser Target user to deactivate.
     * @return string|null Translation key on error or null.
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function validateDeactivation(User $targetUser): ?string
    {
        if (in_array('ROLE_ADMIN', $targetUser->getRoles(), true) && $targetUser->isActive() && $this->userRepository->countActiveAdmins() <= 1) {
            return 'admin.users.error.last_admin_protected';
        }

        return null;
    }
}
