<?php

declare(strict_types=1);

namespace App\Service\Cv;

use Symfony\Component\HttpFoundation\Request;

/**
 * @brief Apply admin Web profiles customization POST fields to a CV content JSON payload slice.
 *
 * @date 2026-06-09
 * @author Stephane H.
 */
class CvWebProfilesAdminUpdateService
{
    /**
     * @brief Parse and normalize web profile entries from an admin POST request.
     *
     * @param array<string, mixed> $payload Existing payload slice or full profile.
     * @param Request $request HTTP request with nested `web_profile_entries`.
     * @return array{
     *     payload: array<string, mixed>,
     *     flashSuccess: list<string>,
     *     flashWarning: list<string>,
     *     flashError: list<string>
     * }
     * @date 2026-06-09
     * @author Stephane H.
     */
    public function applyWebProfilesFromRequest(array $payload, Request $request): array
    {
        $flashSuccess = [];
        $flashWarning = [];
        $flashError = [];

        $parsed = WebProfilesContract::parseEntriesFromRequest($request);
        if ($parsed === null) {
            $flashError[] = 'dashboard.customization_cv.flash.web_profiles_invalid';

            return compact('payload', 'flashSuccess', 'flashWarning', 'flashError');
        }

        $payload[WebProfilesContract::KEY_ENTRIES] = $parsed;

        return compact('payload', 'flashSuccess', 'flashWarning', 'flashError');
    }
}
