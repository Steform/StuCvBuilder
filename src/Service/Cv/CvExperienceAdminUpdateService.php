<?php

declare(strict_types=1);

namespace App\Service\Cv;

use App\Service\RichText\RichHtmlSanitizer;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

/**
 * @brief Apply admin Experience customization POST fields to a CV content JSON payload slice.
 */
class CvExperienceAdminUpdateService
{
    /**
     * @brief Wire Experience admin update service.
     *
     * @param string $projectDir Symfony project root directory.
     * @param RichHtmlSanitizer $richHtmlSanitizer Allowlisted HTML sanitizer for per-locale detail fields.
     * @return void
     * @date 2026-06-03
     * @author Stephane H.
     */
    public function __construct(
        private readonly string $projectDir,
        private readonly RichHtmlSanitizer $richHtmlSanitizer,
        private readonly CvExperienceAdminLogger $experienceAdminLogger,
    ) {
    }

    /**
     * @brief Parse, merge logos, and normalize experience entries from an admin POST request.
     *
     * @param array<string, mixed> $payload Existing payload slice or full profile (mutated via return).
     * @param Request $request HTTP request with nested `experience_entries` and optional logo uploads.
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
    public function applyExperienceFromRequest(array $payload, Request $request, array $activeLocales): array
    {
        $flashSuccess = [];
        $flashWarning = [];
        $flashError = [];

        $previousEntries = ExperienceContract::entriesByLocaleFromStoredPayload($payload);
        $this->experienceAdminLogger->log('save_start', [
            'formScope' => (string) $request->request->get('form_scope', ''),
            'customizationEntry' => (string) $request->request->get('customization_entry', ''),
            'customizationLocale' => (string) $request->request->get('customization_locale', ''),
            'activeLocales' => $activeLocales,
        ]);
        $this->experienceAdminLogger->logEntriesSnapshot('stored_before_save', $previousEntries);

        $rawEntries = ExperienceContract::parseRawEntriesFromRequest($request, $activeLocales);
        if ($rawEntries === null) {
            $this->experienceAdminLogger->log('parse_raw_failed', [
                'reason' => 'invalid_request_structure',
            ]);
            $flashError[] = 'dashboard.customization_cv.flash.experience_invalid';

            return [
                'payload' => $payload,
                'flashSuccess' => $flashSuccess,
                'flashWarning' => $flashWarning,
                'flashError' => $flashError,
            ];
        }

        $this->experienceAdminLogger->logEntriesSnapshot('parse_raw_ok', $rawEntries);
        $this->experienceAdminLogger->logEntriesDiff('posted_vs_stored', $previousEntries, $rawEntries);

        $this->sanitizeDetailHtmlInRawEntries($rawEntries);

        try {
            $this->applyExperienceLogoUploads($request, $rawEntries, $previousEntries);
        } catch (\InvalidArgumentException $exception) {
            $this->experienceAdminLogger->log('logo_upload_failed', [
                'message' => $exception->getMessage(),
            ]);
            $flashWarning[] = $exception->getMessage();

            return [
                'payload' => $payload,
                'flashSuccess' => $flashSuccess,
                'flashWarning' => $flashWarning,
                'flashError' => $flashError,
            ];
        }

        $normalized = ExperienceContract::normalizeEntriesByLocaleWithStatus($rawEntries);
        $parsed = $normalized['entries'];
        if ($parsed === null) {
            $this->experienceAdminLogger->log('normalize_failed', [
                'error' => $normalized['error'],
                'merged' => CvExperienceAdminLogger::summarizeEntriesByLocale($rawEntries),
            ]);
            $flashError[] = 'dashboard.customization_cv.flash.experience_invalid';

            return [
                'payload' => $payload,
                'flashSuccess' => $flashSuccess,
                'flashWarning' => $flashWarning,
                'flashError' => $flashError,
            ];
        }

        $this->experienceAdminLogger->logEntriesDiff('normalize_vs_posted', $rawEntries, $parsed);
        $this->experienceAdminLogger->logEntriesDiff('save_result', $previousEntries, $parsed, [
            'removedFromDatabase' => array_values(array_diff(
                CvExperienceAdminLogger::collectEntryIds($previousEntries),
                CvExperienceAdminLogger::collectEntryIds($parsed),
            )),
            'addedToDatabase' => array_values(array_diff(
                CvExperienceAdminLogger::collectEntryIds($parsed),
                CvExperienceAdminLogger::collectEntryIds($previousEntries),
            )),
        ]);

        $payload[ExperienceContract::KEY_ENTRIES_BY_LOCALE] = $parsed;

        return [
            'payload' => $payload,
            'flashSuccess' => $flashSuccess,
            'flashWarning' => $flashWarning,
            'flashError' => $flashError,
        ];
    }

    /**
     * @brief Merge uploaded or removed company logos into raw experience rows before normalization.
     *
     * @param Request $request HTTP request with file bag and remove flags keyed by entry id.
     * @param array<string, list<array<string, mixed>>> $rawEntries Raw rows by locale (mutated in place).
     * @param array<string, list<array<string, mixed>>> $previousEntries Previously stored normalized entries.
     * @return bool True when at least one logo was uploaded, removed, or replaced.
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function applyExperienceLogoUploads(Request $request, array &$rawEntries, array $previousEntries): bool
    {
        $previousById = [];
        foreach ($previousEntries as $rows) {
            foreach ($rows as $entry) {
                $entryId = is_string($entry['id'] ?? null) ? $entry['id'] : '';
                if ($entryId !== '') {
                    $previousById[$entryId] = $entry;
                }
            }
        }

        $removeFlags = $request->request->all('experience_remove_company_logo');
        $changed = false;

        foreach ($rawEntries as &$rows) {
            foreach ($rows as &$row) {
                if (!is_array($row)) {
                    continue;
                }

                $entryId = isset($row['id']) && is_string($row['id']) ? trim($row['id']) : '';
                if ($entryId === '') {
                    continue;
                }

                $existingPath = ExperienceContract::normalizeStoredLogoPath($row['companyLogoPath'] ?? null);
                if ($existingPath === null && isset($previousById[$entryId])) {
                    $existingPath = ExperienceContract::normalizeStoredLogoPath($previousById[$entryId]['companyLogoPath'] ?? null);
                }

                $shouldRemove = is_array($removeFlags) && self::normalizeBoolFromRequest($removeFlags[$entryId] ?? false);
                $upload = $request->files->get('experience_company_logo');
                $uploadedFile = null;
                if (is_array($upload) && isset($upload[$entryId]) && $upload[$entryId] instanceof UploadedFile) {
                    $candidate = $upload[$entryId];
                    if ($candidate->isValid()) {
                        $uploadedFile = $candidate;
                    }
                }

                if ($uploadedFile instanceof UploadedFile) {
                    $newPath = $this->storeExperienceLogoUpload($uploadedFile, $entryId);
                    $this->deleteCustomExperienceLogoIfNeeded($existingPath ?? '');
                    $row['companyLogoPath'] = $newPath;
                    $changed = true;

                    continue;
                }

                if ($shouldRemove) {
                    $this->deleteCustomExperienceLogoIfNeeded($existingPath ?? '');
                    $row['companyLogoPath'] = null;
                    $changed = true;

                    continue;
                }

                $row['companyLogoPath'] = $existingPath;
            }
        }
        unset($rows, $row);

        return $changed;
    }

    /**
     * @brief Store uploaded experience company logo under public/images/cv/experience/custom.
     *
     * @param UploadedFile $uploadedFile Valid upload.
     * @param string $entryId Experience entry UUID for filename suffix.
     * @return string Relative public path.
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function storeExperienceLogoUpload(UploadedFile $uploadedFile, string $entryId): string
    {
        $allowedMimeTypes = ['image/webp', 'image/png', 'image/jpeg'];
        $mimeType = (string) $uploadedFile->getMimeType();
        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            throw new \InvalidArgumentException('dashboard.customization_cv.flash.invalid_image');
        }

        $extension = strtolower((string) ($uploadedFile->guessExtension() ?: $uploadedFile->getClientOriginalExtension() ?: ''));
        if (!in_array($extension, ['webp', 'png', 'jpg', 'jpeg'], true)) {
            throw new \InvalidArgumentException('dashboard.customization_cv.flash.invalid_image');
        }

        $targetRelativeDirectory = ExperienceContract::EXPERIENCE_LOGO_PATH_PREFIX;
        $targetDirectory = rtrim($this->projectDir, '/').'/public/'.$targetRelativeDirectory;
        if (!is_dir($targetDirectory)) {
            mkdir($targetDirectory, 0775, true);
        }

        $safeId = preg_replace('/[^a-f0-9-]/i', '', $entryId) ?? '';
        $targetFilename = sprintf('experience-logo-%s-%s.%s', substr($safeId, 0, 8), bin2hex(random_bytes(4)), $extension);
        $uploadedFile->move($targetDirectory, $targetFilename);

        return $targetRelativeDirectory.$targetFilename;
    }

    /**
     * @brief Delete previous custom experience logo file when replaced or removed.
     *
     * @param string $relativePath Existing relative path.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function deleteCustomExperienceLogoIfNeeded(string $relativePath): void
    {
        if (!str_starts_with($relativePath, ExperienceContract::EXPERIENCE_LOGO_PATH_PREFIX)) {
            return;
        }

        $absolutePath = rtrim($this->projectDir, '/').'/public/'.$relativePath;
        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }

    /**
     * @brief Sanitize per-locale experience detail HTML before normalization.
     *
     * @param array<string, list<array<string, mixed>>> $rawEntries Raw rows by locale (mutated in place).
     * @return void
     * @date 2026-06-03
     * @author Stephane H.
     */
    private function sanitizeDetailHtmlInRawEntries(array &$rawEntries): void
    {
        foreach ($rawEntries as &$rows) {
            if (!is_array($rows)) {
                continue;
            }

            foreach ($rows as &$row) {
                if (!is_array($row)) {
                    continue;
                }

                $rawHtml = $row['detailHtml'] ?? '';
                $row['detailHtml'] = is_string($rawHtml) ? $this->richHtmlSanitizer->sanitize($rawHtml) : '';
            }
        }

        unset($rows, $row);
    }

    /**
     * @brief Normalize checkbox-like request values to bool.
     *
     * @param mixed $value Raw request value.
     * @return bool
     * @date 2026-06-01
     * @author Stephane H.
     */
    private static function normalizeBoolFromRequest(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'on', 'yes'], true);
        }

        return (bool) $value;
    }
}
