<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @brief Resolve landing paths after authentication for role-specific backoffice entry points.
 */
final class AuthenticatedLandingResolver
{
    public const FORCED_PASSWORD_CHANGE_PATH = '/change-password-required';

    /**
     * @brief Build authenticated landing resolver.
     *
     * @param Security $security Security helper for role checks.
     * @return void
     * @date 2026-06-22
     * @author Stephane H.
     */
    public function __construct(private readonly Security $security)
    {
    }

    /**
     * @brief Resolve post-authentication path including forced password change.
     *
     * @param User|null $user Authenticated user candidate.
     * @return string Application path beginning with /.
     * @date 2026-07-27
     * @author Stephane H.
     */
    public function resolvePostAuthPath(?User $user): string
    {
        if ($user instanceof User && $user->isPasswordResetRequired()) {
            return self::FORCED_PASSWORD_CHANGE_PATH;
        }

        return $this->resolveLandingPath();
    }

    /**
     * @brief Resolve post-login landing path for the current authenticated principal.
     *
     * @return string Application path beginning with /.
     * @date 2026-06-22
     * @author Stephane H.
     */
    public function resolveLandingPath(): string
    {
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return '/dashboard';
        }

        if ($this->security->isGranted('ROLE_CV_EDIT')) {
            return '/dashboard';
        }

        if ($this->security->isGranted('ROLE_TUILE')) {
            return '/dashboard/customization/quick-tiles';
        }

        return '/';
    }
}
