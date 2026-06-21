<?php

declare(strict_types=1);

namespace App\Service\Cv;

use Symfony\Component\HttpFoundation\Request;

/**
 * @brief Apply admin Situation content POST fields to a CV content JSON payload slice.
 */
class CvSituationContentAdminUpdateService
{
    /**
     * @brief Parse and merge Situation editorial content from an admin POST request.
     *
     * @param array<string, mixed> $payload Existing payload (global profile or company override slice).
     * @param Request $request HTTP request with nested `situation_content` fields.
     * @param list<string> $activeLocales Site active locale codes.
     * @return array{
     *     payload: array<string, mixed>,
     *     flashSuccess: list<string>,
     *     flashWarning: list<string>,
     *     flashError: list<string>
     * }
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function applySituationContentFromRequest(array $payload, Request $request, array $activeLocales): array
    {
        $flashSuccess = [];
        $flashWarning = [];
        $flashError = [];

        $parsed = SituationContentContract::parseContentFromRequest($request, $activeLocales);
        if ($parsed === null) {
            $flashError[] = 'dashboard.customization_cv.situation_content.flash_invalid';

            return [
                'payload' => $payload,
                'flashSuccess' => $flashSuccess,
                'flashWarning' => $flashWarning,
                'flashError' => $flashError,
            ];
        }

        $payload[SituationContentContract::KEY_CONTENT_BY_LOCALE] = $parsed;

        return [
            'payload' => $payload,
            'flashSuccess' => $flashSuccess,
            'flashWarning' => $flashWarning,
            'flashError' => $flashError,
        ];
    }
}
