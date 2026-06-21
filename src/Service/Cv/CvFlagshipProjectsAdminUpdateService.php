<?php

declare(strict_types=1);

namespace App\Service\Cv;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

/**
 * @brief Apply admin flagship projects POST fields to a CV content JSON payload slice.
 */
class CvFlagshipProjectsAdminUpdateService
{
    /**
     * @brief Wire flagship projects admin update service.
     *
     * @param FlagshipProjectsFormValidator $flagshipProjectsFormValidator Form validator.
     * @param CvFlagshipProjectPreviewUploadService $cvFlagshipProjectPreviewUploadService Preview upload service.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function __construct(
        private readonly FlagshipProjectsFormValidator $flagshipProjectsFormValidator,
        private readonly CvFlagshipProjectPreviewUploadService $cvFlagshipProjectPreviewUploadService,
    ) {
    }

    /**
     * @brief Parse, merge preview uploads, and normalize flagship projects from an admin POST request.
     *
     * @param array<string, mixed> $payload Existing payload slice or full profile.
     * @param Request $request HTTP request with flagship project form fields and uploads.
     * @param list<string> $activeLocales Site active locale codes.
     * @param string $defaultLocale Site default locale.
     * @return array{
     *     payload: array<string, mixed>,
     *     flashSuccess: list<string>,
     *     flashWarning: list<string>,
     *     flashError: list<string>,
     *     flashStructuredWarning: list<array{message: string, parameters: array<string, string>}>
     * }
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function applyFlagshipProjectsFromRequest(
        array $payload,
        Request $request,
        array $activeLocales,
        string $defaultLocale,
    ): array {
        $flashSuccess = [];
        $flashWarning = [];
        $flashError = [];
        $flashStructuredWarning = [];

        $validationErrors = $this->flagshipProjectsFormValidator->validateRequest($request, $activeLocales, $defaultLocale);
        if ($validationErrors !== []) {
            $flashStructuredWarning = $validationErrors;

            return compact('payload', 'flashSuccess', 'flashWarning', 'flashError', 'flashStructuredWarning');
        }

        $parsedRaw = FlagshipProjectsContract::parseRawEntriesFromRequest($request, $activeLocales, $defaultLocale);
        if ($parsedRaw === null) {
            $flashStructuredWarning[] = [
                'message' => 'dashboard.customization_cv.flagship_projects.validation.form_structure_invalid',
                'parameters' => [],
            ];

            return compact('payload', 'flashSuccess', 'flashWarning', 'flashError', 'flashStructuredWarning');
        }

        $previousEntries = FlagshipProjectsContract::entriesByLocaleFromStoredPayload($payload);
        $rawEntries = $parsedRaw['entriesByLocale'];
        $projectIds = $parsedRaw['projectIds'];

        try {
            $this->applyFlagshipPreviewUploads($request, $rawEntries, $previousEntries, $projectIds);
            $this->cleanupRemovedFlagshipProjectPreviews($previousEntries, $projectIds);
        } catch (\InvalidArgumentException $exception) {
            $flashWarning[] = $exception->getMessage();

            return compact('payload', 'flashSuccess', 'flashWarning', 'flashError', 'flashStructuredWarning');
        } catch (\RuntimeException $exception) {
            $flashWarning[] = $exception->getMessage();

            return compact('payload', 'flashSuccess', 'flashWarning', 'flashError', 'flashStructuredWarning');
        }

        $parsed = FlagshipProjectsContract::normalizeEntriesByLocale($rawEntries, $defaultLocale);
        if ($parsed === null) {
            $flashStructuredWarning[] = [
                'message' => 'dashboard.customization_cv.flagship_projects.validation.normalization_failed',
                'parameters' => [],
            ];

            return compact('payload', 'flashSuccess', 'flashWarning', 'flashError', 'flashStructuredWarning');
        }

        $payload[FlagshipProjectsContract::KEY_SECTION_ENABLED] = FlagshipProjectsContract::parseSectionEnabledFromRequest($request);
        $payload[FlagshipProjectsContract::KEY_ENTRIES_BY_LOCALE] = $parsed;

        return compact('payload', 'flashSuccess', 'flashWarning', 'flashError', 'flashStructuredWarning');
    }

    /**
     * @brief Merge uploaded or removed preview images into raw rows before normalization.
     *
     * @param Request $request HTTP request with preview files and remove flags keyed by project id.
     * @param array<string, list<array<string, mixed>>> $rawEntries Raw rows by locale (mutated in place).
     * @param array<string, list<array<string, mixed>>> $previousEntries Previously stored normalized entries.
     * @param list<string> $projectIds Project UUIDs submitted in the current form.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function applyFlagshipPreviewUploads(
        Request $request,
        array &$rawEntries,
        array $previousEntries,
        array $projectIds,
    ): void {
        $entriesRaw = $request->request->all('flagship_projects');
        $entriesBlock = is_array($entriesRaw['entries'] ?? null) ? $entriesRaw['entries'] : [];
        $removeFlags = $request->request->all('flagship_remove_preview');
        $uploads = $request->files->all('flagship_project_preview');
        $previousById = $this->indexFlagshipProjectRowsById($previousEntries);

        foreach ($projectIds as $projectId) {
            if (!is_string($projectId) || $projectId === '') {
                continue;
            }

            $entryBlock = is_array($entriesBlock[$projectId] ?? null) ? $entriesBlock[$projectId] : [];
            $submittedPath = FlagshipProjectsContract::normalizeStoredPreviewPath($entryBlock['preview_image_path'] ?? null);
            $previousEntry = $previousById[$projectId] ?? null;
            $existingPath = $submittedPath
                ?? FlagshipProjectsContract::normalizeStoredPreviewPath(is_array($previousEntry) ? ($previousEntry['previewImagePath'] ?? null) : null)
                ?? FlagshipProjectsContract::DEFAULT_PROJECT_PREVIEW_IMAGE_PATH;

            $shouldRemove = is_array($removeFlags) && self::normalizeBoolFromRequest($removeFlags[$projectId] ?? false);
            $uploadedFile = null;
            if (is_array($uploads) && isset($uploads[$projectId]) && $uploads[$projectId] instanceof UploadedFile) {
                $candidate = $uploads[$projectId];
                if ($candidate->isValid()) {
                    $uploadedFile = $candidate;
                }
            }

            if ($uploadedFile instanceof UploadedFile) {
                $newPath = $this->cvFlagshipProjectPreviewUploadService->store($uploadedFile, $projectId);
                if (
                    $existingPath !== FlagshipProjectsContract::DEFAULT_PROJECT_PREVIEW_IMAGE_PATH
                    && $existingPath !== $newPath
                ) {
                    $this->cvFlagshipProjectPreviewUploadService->deleteIfNeeded($existingPath);
                }
                $existingPath = $newPath;
            } elseif ($shouldRemove) {
                if ($existingPath !== FlagshipProjectsContract::DEFAULT_PROJECT_PREVIEW_IMAGE_PATH) {
                    $this->cvFlagshipProjectPreviewUploadService->deleteIfNeeded($existingPath);
                }
                $existingPath = FlagshipProjectsContract::DEFAULT_PROJECT_PREVIEW_IMAGE_PATH;
            }

            foreach ($rawEntries as &$rows) {
                foreach ($rows as &$row) {
                    if (!is_array($row) || ($row['id'] ?? '') !== $projectId) {
                        continue;
                    }

                    $row['previewImagePath'] = $existingPath;
                }
                unset($row);
            }
            unset($rows);
        }
    }

    /**
     * @brief Delete custom preview files for projects removed from the submitted form.
     *
     * @param array<string, list<array<string, mixed>>> $previousEntries Previously stored rows by locale.
     * @param list<string> $projectIds Project UUIDs still present in the submitted form.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function cleanupRemovedFlagshipProjectPreviews(array $previousEntries, array $projectIds): void
    {
        $previousIds = FlagshipProjectsContract::collectProjectIdsFromEntriesByLocale($previousEntries);
        $keptIds = array_fill_keys($projectIds, true);

        foreach ($previousIds as $previousId) {
            if (isset($keptIds[$previousId])) {
                continue;
            }

            foreach ($previousEntries as $rows) {
                foreach ($rows as $row) {
                    if (($row['id'] ?? '') !== $previousId) {
                        continue;
                    }

                    $path = FlagshipProjectsContract::normalizeStoredPreviewPath($row['previewImagePath'] ?? null);
                    if ($path !== null && $path !== FlagshipProjectsContract::DEFAULT_PROJECT_PREVIEW_IMAGE_PATH) {
                        $this->cvFlagshipProjectPreviewUploadService->deleteIfNeeded($path);
                    }
                }
            }
        }
    }

    /**
     * @brief Index the first stored row for each project id across locale lists.
     *
     * @param array<string, list<array<string, mixed>>> $entriesByLocale Rows keyed by locale.
     * @return array<string, array<string, mixed>>
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function indexFlagshipProjectRowsById(array $entriesByLocale): array
    {
        $indexed = [];
        foreach ($entriesByLocale as $rows) {
            foreach ($rows as $row) {
                $id = is_string($row['id'] ?? null) ? trim($row['id']) : '';
                if ($id !== '' && !isset($indexed[$id])) {
                    $indexed[$id] = $row;
                }
            }
        }

        return $indexed;
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
