<?php

declare(strict_types=1);

namespace App\Service\Auth;

use Symfony\Bundle\SecurityBundle\Security;

/**
 * @brief Resolve landing paths after authentication for role-specific backoffice entry points.
 */
final class AuthenticatedLandingResolver
{
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
     * @brief Resolve post-login landing path for the current authenticated principal.
     *
     * @param void No input parameter.
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
            return '/admin/cv';
        }

        if ($this->security->isGranted('ROLE_TUILE')) {
            return '/dashboard/customization/quick-tiles';
        }

        return '/';
    }
}
