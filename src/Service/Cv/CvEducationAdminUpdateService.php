<?php

declare(strict_types=1);

namespace App\Service\Cv;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

/**
 * @brief Apply admin Education customization POST fields to a CV content JSON payload slice.
 */
class CvEducationAdminUpdateService
{
    /**
     * @brief Wire Education admin update service.
     *
     * @param string $projectDir Symfony project root directory.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function __construct(
        private readonly string $projectDir,
    ) {
    }

    /**
     * @brief Parse, merge logos, and normalize education entries from an admin POST request.
     *
     * @param array<string, mixed> $payload Existing payload slice or full profile.
     * @param Request $request HTTP request with nested `education_entries` and optional logo uploads.
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
    public function applyEducationFromRequest(array $payload, Request $request, array $activeLocales): array
    {
        $flashSuccess = [];
        $flashWarning = [];
        $flashError = [];

        $rawEntries = EducationContract::parseRawEntriesFromRequest($request, $activeLocales);
        if ($rawEntries === null) {
            $flashError[] = 'dashboard.customization_cv.flash.education_invalid';

            return compact('payload', 'flashSuccess', 'flashWarning', 'flashError');
        }

        $previousEntries = EducationContract::entriesByLocaleFromStoredPayload($payload);

        try {
            $this->applyEducationLogoUploads($request, $rawEntries, $previousEntries);
        } catch (\InvalidArgumentException $exception) {
            $flashWarning[] = $exception->getMessage();

            return compact('payload', 'flashSuccess', 'flashWarning', 'flashError');
        }

        $parsed = EducationContract::normalizeEntriesByLocale($rawEntries);
        if ($parsed === null) {
            $flashError[] = 'dashboard.customization_cv.flash.education_invalid';

            return compact('payload', 'flashSuccess', 'flashWarning', 'flashError');
        }

        $payload[EducationContract::KEY_ENTRIES_BY_LOCALE] = $parsed;

        return compact('payload', 'flashSuccess', 'flashWarning', 'flashError');
    }

    /**
     * @brief Merge uploaded or removed institution logos into raw education rows before normalization.
     *
     * @param Request $request HTTP request with file bag and remove flags keyed by entry id.
     * @param array<string, list<array<string, mixed>>> $rawEntries Raw rows by locale (mutated in place).
     * @param array<string, list<array<string, mixed>>> $previousEntries Previously stored normalized entries.
     * @return bool True when at least one logo was uploaded, removed, or replaced.
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function applyEducationLogoUploads(Request $request, array &$rawEntries, array $previousEntries): bool
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

        $removeFlags = $request->request->all('education_remove_institution_logo');
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

                $existingPath = EducationContract::normalizeStoredLogoPath($row['institutionLogoPath'] ?? null);
                if ($existingPath === null && isset($previousById[$entryId])) {
                    $existingPath = EducationContract::normalizeStoredLogoPath($previousById[$entryId]['institutionLogoPath'] ?? null);
                }

                $shouldRemove = is_array($removeFlags) && self::normalizeBoolFromRequest($removeFlags[$entryId] ?? false);
                $upload = $request->files->get('education_institution_logo');
                $uploadedFile = null;
                if (is_array($upload) && isset($upload[$entryId]) && $upload[$entryId] instanceof UploadedFile) {
                    $candidate = $upload[$entryId];
                    if ($candidate->isValid()) {
                        $uploadedFile = $candidate;
                    }
                }

                if ($uploadedFile instanceof UploadedFile) {
                    $newPath = $this->storeEducationLogoUpload($uploadedFile, $entryId);
                    $this->deleteCustomEducationLogoIfNeeded($existingPath ?? '');
                    $row['institutionLogoPath'] = $newPath;
                    $changed = true;

                    continue;
                }

                if ($shouldRemove) {
                    $this->deleteCustomEducationLogoIfNeeded($existingPath ?? '');
                    $row['institutionLogoPath'] = null;
                    $changed = true;

                    continue;
                }

                $row['institutionLogoPath'] = $existingPath;
            }
        }
        unset($rows, $row);

        return $changed;
    }

    /**
     * @brief Store uploaded education institution logo under public/images/cv/education/custom.
     *
     * @param UploadedFile $uploadedFile Valid upload.
     * @param string $entryId Education entry UUID for filename suffix.
     * @return string Relative public path.
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function storeEducationLogoUpload(UploadedFile $uploadedFile, string $entryId): string
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

        $targetRelativeDirectory = EducationContract::EDUCATION_LOGO_PATH_PREFIX;
        $targetDirectory = rtrim($this->projectDir, '/').'/public/'.$targetRelativeDirectory;
        if (!is_dir($targetDirectory)) {
            mkdir($targetDirectory, 0775, true);
        }

        $safeId = preg_replace('/[^a-f0-9-]/i', '', $entryId) ?? '';
        $targetFilename = sprintf('education-logo-%s-%s.%s', substr($safeId, 0, 8), bin2hex(random_bytes(4)), $extension);
        $uploadedFile->move($targetDirectory, $targetFilename);

        return $targetRelativeDirectory.$targetFilename;
    }

    /**
     * @brief Delete previous custom education institution logo file when replaced or removed.
     *
     * @param string $relativePath Existing relative path.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function deleteCustomEducationLogoIfNeeded(string $relativePath): void
    {
        if (!str_starts_with($relativePath, EducationContract::EDUCATION_LOGO_PATH_PREFIX)) {
            return;
        }

        $absolutePath = rtrim($this->projectDir, '/').'/public/'.$relativePath;
        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
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
