<?php

declare(strict_types=1);

namespace App\Service\Cv;

use Symfony\Component\HttpFoundation\Request;

/**
 * @brief Apply admin References customization POST fields to a CV content JSON payload slice.
 *
 * @date 2026-06-09
 * @author Stephane H.
 */
class CvReferencesAdminUpdateService
{
    /**
     * @brief Parse and normalize reference entries and section toggle from an admin POST request.
     *
     * @param array<string, mixed> $payload Existing payload slice or full profile.
     * @param Request $request HTTP request with nested `reference_entries` and section toggle.
     * @param list<string> $activeLocales Site active locale codes.
     * @return array{
     *     payload: array<string, mixed>,
     *     flashSuccess: list<string>,
     *     flashWarning: list<string>,
     *     flashError: list<string>
     * }
     * @date 2026-06-09
     * @author Stephane H.
     */
    public function applyReferencesFromRequest(array $payload, Request $request, array $activeLocales): array
    {
        $flashSuccess = [];
        $flashWarning = [];
        $flashError = [];

        $payload[ReferencesContract::KEY_SECTION_ENABLED] = ReferencesContract::parseSectionEnabledFromRequest($request);

        $parsed = ReferencesContract::parseEntriesFromRequest($request, $activeLocales);
        if ($parsed === null) {
            $flashError[] = 'dashboard.customization_cv.flash.references_invalid';

            return compact('payload', 'flashSuccess', 'flashWarning', 'flashError');
        }

        $payload[ReferencesContract::KEY_ENTRIES_BY_LOCALE] = $parsed;

        return compact('payload', 'flashSuccess', 'flashWarning', 'flashError');
    }
}
