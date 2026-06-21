<?php

declare(strict_types=1);

namespace App\Service\Cv;

/**
 * @brief Resolve CV flagship projects from persisted JSON for the public CV and admin forms.
 *
 * @date 2026-05-31
 * @author Stephane H.
 */
class CvFlagshipProjectsSettingsService
{
    /**
     * @brief Resolve flagship projects for admin forms and public CV rendering.
     *
     * @param string $contentJson CvProfile JSON payload.
     * @param list<string> $activeLocales Site active locales.
     * @param string $defaultLocale Site default locale.
     * @param string $displayLocale Viewer or request locale.
     * @return array{
     *     entriesByLocale: array<string, list<array<string, mixed>>>,
     *     projects: list<array<string, mixed>>,
     *     projectsFull: list<array<string, mixed>>,
     *     hasSecondaryVisible: bool,
     *     hasPersistedMap: bool
     * }
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function resolveFromContentJson(
        string $contentJson,
        array $activeLocales,
        string $defaultLocale,
        string $displayLocale
    ): array {
        $payload = $this->decodeJsonPayload($contentJson);
        $hasPersistedMap = FlagshipProjectsContract::hasPersistedProjectsMap($payload);
        $stored = FlagshipProjectsContract::entriesByLocaleFromStoredPayload($payload);

        $entriesByLocale = [];
        foreach ($activeLocales as $locale) {
            $entriesByLocale[$locale] = $stored[$locale] ?? [];
        }

        $displayLocaleKey = $this->resolveDisplayLocaleKey($entriesByLocale, $displayLocale, $defaultLocale, $activeLocales);
        $localeEntries = $entriesByLocale[$displayLocaleKey] ?? [];
        $projects = $this->filterVisible($localeEntries);
        $projectsFull = $this->resolveAll($localeEntries);

        return [
            'entriesByLocale' => $entriesByLocale,
            'projects' => $projects,
            'projectsFull' => $projectsFull,
            'hasSecondaryVisible' => $this->hasSecondaryVisible($localeEntries),
            'hasPersistedMap' => $hasPersistedMap,
            'canonicalProjects' => $this->buildCanonicalProjectsForAdmin($entriesByLocale, $activeLocales, $defaultLocale),
        ];
    }

    /**
     * @brief Build canonical project cards for the admin form from locale-keyed rows.
     *
     * @param array<string, list<array<string, mixed>>> $entriesByLocale Rows keyed by locale.
     * @param list<string> $activeLocales Site active locales.
     * @param string $defaultLocale Site default locale used as master list.
     * @return list<array<string, mixed>>
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function buildCanonicalProjectsForAdmin(array $entriesByLocale, array $activeLocales, string $defaultLocale): array
    {
        $masterLocale = $this->resolveDisplayLocaleKey($entriesByLocale, $defaultLocale, $defaultLocale, $activeLocales);
        $masterRows = $entriesByLocale[$masterLocale] ?? [];

        $projects = [];
        foreach ($masterRows as $row) {
            $projectId = is_string($row['id'] ?? null) ? trim($row['id']) : '';
            if ($projectId === '') {
                continue;
            }

            $locales = [];
            foreach ($activeLocales as $locale) {
                $localeRow = $this->findProjectRowById($entriesByLocale[$locale] ?? [], $projectId);
                $locales[$locale] = [
                    'title' => is_string($localeRow['title'] ?? null) ? $localeRow['title'] : '',
                    'description' => is_string($localeRow['description'] ?? null) ? $localeRow['description'] : '',
                    'tags' => is_array($localeRow['tags'] ?? null) ? $localeRow['tags'] : [],
                    'previewAlt' => is_string($localeRow['previewAlt'] ?? null) ? $localeRow['previewAlt'] : '',
                    'siteLinkLabel' => is_string($localeRow['siteLinkLabel'] ?? null) ? $localeRow['siteLinkLabel'] : '',
                ];
            }

            $projects[] = [
                'id' => $projectId,
                'sortOrder' => (int) ($row['sortOrder'] ?? 0),
                'githubUrl' => is_string($row['githubUrl'] ?? null) ? $row['githubUrl'] : '',
                'demoUrl' => is_string($row['demoUrl'] ?? null) ? $row['demoUrl'] : '',
                'isVisible' => ($row['isVisible'] ?? true) === true,
                'previewImagePath' => is_string($row['previewImagePath'] ?? null)
                    ? $row['previewImagePath']
                    : FlagshipProjectsContract::DEFAULT_PROJECT_PREVIEW_IMAGE_PATH,
                'locales' => $locales,
            ];
        }

        usort(
            $projects,
            static fn (array $left, array $right): int => ((int) ($left['sortOrder'] ?? 0)) <=> ((int) ($right['sortOrder'] ?? 0))
        );

        return $projects;
    }

    /**
     * @brief Find one project row by UUID in a locale list.
     *
     * @param list<array<string, mixed>> $rows Locale rows.
     * @param string $projectId Project UUID.
     * @return array<string, mixed>
     * @date 2026-05-31
     * @author Stephane H.
     */
    private function findProjectRowById(array $rows, string $projectId): array
    {
        foreach ($rows as $row) {
            if (($row['id'] ?? '') === $projectId) {
                return $row;
            }
        }

        return [];
    }

    /**
     * @brief Keep visible projects sorted for the public CV grid.
     *
     * @param list<array<string, mixed>> $entries Resolved entries for one locale.
     * @return list<array<string, mixed>>
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function filterVisible(array $entries): array
    {
        $filtered = array_values(array_filter(
            $entries,
            static fn (array $entry): bool => ($entry['isVisible'] ?? false) === true
        ));

        usort(
            $filtered,
            static fn (array $left, array $right): int => ((int) ($left['sortOrder'] ?? 0)) <=> ((int) ($right['sortOrder'] ?? 0))
        );

        return array_map(static function (array $entry): array {
            $codeUrl = is_string($entry['githubUrl'] ?? null) ? $entry['githubUrl'] : null;
            $entry['codeLinkIsGithub'] = FlagshipProjectsContract::isGithubCodeUrl($codeUrl);

            return $entry;
        }, $filtered);
    }

    /**
     * @brief Resolve all projects for the full public page, marking rows hidden on the primary CV grid.
     *
     * @param list<array<string, mixed>> $entries Resolved entries for one locale.
     * @return list<array<string, mixed>>
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function resolveAll(array $entries): array
    {
        $sorted = $entries;
        usort(
            $sorted,
            static fn (array $left, array $right): int => ((int) ($left['sortOrder'] ?? 0)) <=> ((int) ($right['sortOrder'] ?? 0))
        );

        return array_map(static function (array $entry): array {
            $codeUrl = is_string($entry['githubUrl'] ?? null) ? $entry['githubUrl'] : null;
            $entry['codeLinkIsGithub'] = FlagshipProjectsContract::isGithubCodeUrl($codeUrl);
            $entry['hiddenOnPrimary'] = ($entry['isVisible'] ?? false) !== true;

            return $entry;
        }, array_values($sorted));
    }

    /**
     * @brief Whether at least one project is hidden on the primary CV grid.
     *
     * @param list<array<string, mixed>> $entries Resolved entries for one locale.
     * @return bool
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function hasSecondaryVisible(array $entries): bool
    {
        foreach ($entries as $entry) {
            if (($entry['isVisible'] ?? false) !== true) {
                return true;
            }
        }

        return false;
    }

    /**
     * @brief Pick the best locale key for display when the requested locale has no rows.
     *
     * @param array<string, list<array<string, mixed>>> $entriesByLocale Entries keyed by locale.
     * @param string $displayLocale Requested locale.
     * @param string $defaultLocale Site default locale.
     * @param list<string> $activeLocales Active locales.
     * @return string
     * @date 2026-05-31
     * @author Stephane H.
     */
    private function resolveDisplayLocaleKey(
        array $entriesByLocale,
        string $displayLocale,
        string $defaultLocale,
        array $activeLocales
    ): string {
        if (($entriesByLocale[$displayLocale] ?? []) !== []) {
            return $displayLocale;
        }

        if (($entriesByLocale[$defaultLocale] ?? []) !== []) {
            return $defaultLocale;
        }

        foreach ($activeLocales as $locale) {
            if (($entriesByLocale[$locale] ?? []) !== []) {
                return $locale;
            }
        }

        return $displayLocale;
    }

    /**
     * @brief Decode JSON payload as associative array.
     *
     * @param string $json JSON payload.
     * @return array<string, mixed>
     * @date 2026-05-31
     * @author Stephane H.
     */
    private function decodeJsonPayload(string $json): array
    {
        $payload = json_decode($json, true);

        return is_array($payload) ? $payload : [];
    }
}
