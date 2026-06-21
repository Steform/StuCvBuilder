<?php

declare(strict_types=1);

namespace App\Service\Cv;

use Symfony\Component\HttpFoundation\Request;

/**
 * @brief Persistence and request parsing for flagship projects on the public CV.
 *
 * @date 2026-05-31
 * @author Stephane H.
 */
final class FlagshipProjectsContract
{
    public const KEY_SECTION_ENABLED = 'flagshipProjectsSectionEnabled';

    public const KEY_ENTRIES_BY_LOCALE = 'flagshipProjectsByLocale';

    public const PREVIEW_IMAGE_PATH_PREFIX = 'images/cv/projects/custom/';

    /** @var string Generic placeholder for new or reset flagship project previews. */
    public const DEFAULT_PROJECT_PREVIEW_IMAGE_PATH = 'images/cv/projects/project-default.webp';

    /** @deprecated Use {@see self::DEFAULT_PROJECT_PREVIEW_IMAGE_PATH}. */
    public const FALLBACK_PREVIEW_IMAGE_PATH = self::DEFAULT_PROJECT_PREVIEW_IMAGE_PATH;

    public const MAX_PROJECTS_PER_LOCALE = 6;

    public const MAX_TITLE_LENGTH = 120;

    public const MAX_DESCRIPTION_LENGTH = 600;

    public const MAX_TAG_LENGTH = 40;

    public const MAX_PREVIEW_ALT_LENGTH = 160;

    public const MAX_URL_LENGTH = 500;

    public const MAX_SITE_LINK_LABEL_LENGTH = 40;

    private const UUID_V4_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    /** @var string Accept any UUID-shaped internal project id (including deterministic fallbacks). */
    private const UUID_SHAPE_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    /**
     * @brief Whether the flagship projects block is shown on the public CV (default enabled when unset).
     *
     * @param array<string, mixed> $payload CvProfile content JSON array.
     * @return bool True when the section should render.
     * @date 2026-05-31
     * @author Stephane H.
     */
    public static function isSectionEnabledFromPayload(array $payload): bool
    {
        if (!array_key_exists(self::KEY_SECTION_ENABLED, $payload)) {
            return true;
        }

        return self::normalizeEnabled($payload[self::KEY_SECTION_ENABLED]);
    }

    /**
     * @brief Whether persisted project rows exist in the payload.
     *
     * @param array<string, mixed> $payload Decoded profile JSON.
     * @return bool
     * @date 2026-05-31
     * @author Stephane H.
     */
    public static function hasPersistedProjectsMap(array $payload): bool
    {
        return array_key_exists(self::KEY_ENTRIES_BY_LOCALE, $payload)
            && is_array($payload[self::KEY_ENTRIES_BY_LOCALE]);
    }

    /**
     * @brief Read project rows map from decoded CvProfile payload.
     *
     * @param array<string, mixed> $payload Decoded JSON.
     * @return array<string, list<array<string, mixed>>>
     * @date 2026-05-31
     * @author Stephane H.
     */
    public static function entriesByLocaleFromStoredPayload(array $payload): array
    {
        $raw = $payload[self::KEY_ENTRIES_BY_LOCALE] ?? null;
        if (!is_array($raw)) {
            return [];
        }

        $result = [];
        foreach ($raw as $locale => $rows) {
            if (!is_string($locale) || !is_array($rows)) {
                continue;
            }

            $normalizedRows = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $normalizedRows[] = $row;
            }

            $result[$locale] = $normalizedRows;
        }

        return $result;
    }

    /**
     * @brief Collect unique project ids from rows grouped by locale.
     *
     * @param array<string, list<array<string, mixed>>> $entriesByLocale Rows keyed by locale.
     * @return list<string>
     * @date 2026-05-31
     * @author Stephane H.
     */
    public static function collectProjectIdsFromEntriesByLocale(array $entriesByLocale): array
    {
        $ids = [];
        foreach ($entriesByLocale as $rows) {
            foreach ($rows as $row) {
                $id = is_string($row['id'] ?? null) ? trim($row['id']) : '';
                if ($id !== '' && self::isValidUuid($id)) {
                    $ids[$id] = true;
                }
            }
        }

        return array_keys($ids);
    }

    /**
     * @brief Parse section visibility from admin customization POST fields.
     *
     * @param Request $request HTTP request with `flagship_projects_section_enabled`.
     * @return bool True when the section should be visible on the public CV.
     * @date 2026-05-31
     * @author Stephane H.
     */
    public static function parseSectionEnabledFromRequest(Request $request): bool
    {
        $all = $request->request->all();
        if (!array_key_exists('flagship_projects_section_enabled', $all)) {
            return false;
        }

        return self::normalizeEnabled($all['flagship_projects_section_enabled']);
    }

    /**
     * @brief Parse canonical project cards from admin POST before preview merge.
     *
     * @param Request $request HTTP request.
     * @param list<string> $activeLocales Site active locale codes.
     * @param string $defaultLocale Site default locale used to drop empty project cards.
     * @return array{entriesByLocale: array<string, list<array<string, mixed>>>, projectIds: list<string>}|null Null when structure is invalid.
     * @date 2026-05-31
     * @author Stephane H.
     */
    public static function parseRawEntriesFromRequest(Request $request, array $activeLocales, string $defaultLocale): ?array
    {
        $raw = $request->request->all('flagship_projects');
        if (!is_array($raw)) {
            return null;
        }

        $entriesRaw = $raw['entries'] ?? null;
        if (!is_array($entriesRaw)) {
            return null;
        }

        $canonicalRows = [];
        foreach ($entriesRaw as $projectId => $entry) {
            if (!is_string($projectId) || !is_array($entry)) {
                return null;
            }

            $projectId = trim($projectId);
            if (!self::isValidUuid($projectId)) {
                return null;
            }

            $sortOrder = isset($entry['sort_order']) && is_numeric($entry['sort_order'])
                ? (int) $entry['sort_order']
                : count($canonicalRows);

            $canonicalRows[] = [
                'id' => $projectId,
                'sortOrder' => max(0, $sortOrder),
                'entry' => $entry,
            ];
        }

        usort(
            $canonicalRows,
            static fn (array $left, array $right): int => ((int) $left['sortOrder']) <=> ((int) $right['sortOrder'])
        );

        if (count($canonicalRows) > self::MAX_PROJECTS_PER_LOCALE) {
            return null;
        }

        /** @var array<string, list<array<string, mixed>>> $result */
        $result = [];
        foreach ($activeLocales as $locale) {
            $result[$locale] = [];
        }

        $projectIds = [];
        foreach ($canonicalRows as $index => $item) {
            $projectId = $item['id'];
            $entry = $item['entry'];
            $locales = is_array($entry['locales'] ?? null) ? $entry['locales'] : [];
            $githubUrl = $entry['github_url'] ?? null;
            $demoUrl = $entry['demo_url'] ?? null;
            $isVisible = $entry['is_visible'] ?? true;
            $previewImagePath = $entry['preview_image_path'] ?? null;

            $projectIds[] = $projectId;

            foreach ($activeLocales as $locale) {
                $localeRow = is_array($locales[$locale] ?? null) ? $locales[$locale] : [];

                $result[$locale][] = [
                    'id' => $projectId,
                    'sortOrder' => $index,
                    'title' => $localeRow['title'] ?? '',
                    'description' => $localeRow['description'] ?? '',
                    'tags' => $localeRow['tags'] ?? '',
                    'previewAlt' => $localeRow['preview_alt'] ?? '',
                    'siteLinkLabel' => $localeRow['site_link_label'] ?? '',
                    'githubUrl' => $githubUrl,
                    'demoUrl' => $demoUrl,
                    'isVisible' => $isVisible,
                    'previewImagePath' => $previewImagePath,
                ];
            }
        }

        $result = self::filterEmptyProjects($result, $defaultLocale);

        return [
            'entriesByLocale' => $result,
            'projectIds' => array_values(array_filter(
                $projectIds,
                static fn (string $id): bool => self::projectIdHasDefaultTitle($result, $defaultLocale, $id)
            )),
        ];
    }

    /**
     * @brief Normalize all locale project lists after preview path merge.
     *
     * @param array<string, list<array<string, mixed>>> $rowsByLocale Raw rows keyed by locale.
     * @param string $defaultLocale Site default locale for empty translation fallback.
     * @return array<string, list<array<string, mixed>>>|null Null when any entry is invalid.
     * @date 2026-05-31
     * @author Stephane H.
     */
    public static function normalizeEntriesByLocale(array $rowsByLocale, string $defaultLocale = 'fr'): ?array
    {
        $rowsByLocale = self::applyDefaultLocaleFallbacks($rowsByLocale, $defaultLocale);

        $result = [];
        foreach ($rowsByLocale as $locale => $rows) {
            if (!is_string($locale) || !is_array($rows)) {
                continue;
            }

            if (count($rows) > self::MAX_PROJECTS_PER_LOCALE) {
                return null;
            }

            $normalized = [];
            $sortOrder = 0;
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    return null;
                }

                $entry = self::normalizeEntry($row, $sortOrder);
                if ($entry === null) {
                    return null;
                }

                $normalized[] = $entry;
                ++$sortOrder;
            }

            $result[$locale] = $normalized;
        }

        return $result;
    }

    /**
     * @brief Normalize a single project row from request or stored JSON.
     *
     * @param array<string, mixed> $row Raw project row.
     * @param int $sortOrder Display order index.
     * @return array<string, mixed>|null Null when invalid.
     * @date 2026-05-31
     * @author Stephane H.
     */
    public static function normalizeEntry(array $row, int $sortOrder): ?array
    {
        $id = isset($row['id']) && is_string($row['id']) && $row['id'] !== ''
            ? trim($row['id'])
            : self::generateUuidV4();
        if (!self::isValidUuid($id)) {
            return null;
        }

        $title = self::normalizeText($row['title'] ?? null, self::MAX_TITLE_LENGTH);
        if ($title === null || $title === '') {
            return null;
        }

        $description = self::normalizeText($row['description'] ?? null, self::MAX_DESCRIPTION_LENGTH) ?? '';
        $tags = self::normalizeTags($row['tags'] ?? null);
        if ($tags === null) {
            return null;
        }

        $previewAlt = self::normalizeText($row['previewAlt'] ?? null, self::MAX_PREVIEW_ALT_LENGTH);
        if ($previewAlt === null || $previewAlt === '') {
            $previewAlt = $title;
        }

        $previewImagePath = self::normalizeStoredPreviewPath($row['previewImagePath'] ?? null);
        if ($previewImagePath === null) {
            $previewImagePath = self::DEFAULT_PROJECT_PREVIEW_IMAGE_PATH;
        }

        $siteLinkLabel = self::normalizeText($row['siteLinkLabel'] ?? '', self::MAX_SITE_LINK_LABEL_LENGTH);
        if ($siteLinkLabel === null) {
            return null;
        }

        return [
            'id' => $id,
            'sortOrder' => max(0, $sortOrder),
            'title' => $title,
            'description' => $description,
            'tags' => $tags,
            'previewAlt' => $previewAlt,
            'previewImagePath' => $previewImagePath,
            'githubUrl' => self::normalizeHttpUrl($row['githubUrl'] ?? null),
            'demoUrl' => self::normalizeHttpUrl($row['demoUrl'] ?? null),
            'siteLinkLabel' => $siteLinkLabel,
            'isVisible' => self::normalizeEnabled($row['isVisible'] ?? true),
        ];
    }

    /**
     * @brief Detect whether a code repository URL points to GitHub.
     *
     * @param string|null $url Absolute HTTP(S) URL.
     * @return bool True when the host belongs to GitHub.
     * @date 2026-05-31
     * @author Stephane H.
     */
    public static function isGithubCodeUrl(?string $url): bool
    {
        if ($url === null || trim($url) === '') {
            return false;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '') {
            return false;
        }

        return $host === 'github.com' || str_ends_with($host, '.github.com');
    }

    /**
     * @brief Validate stored relative preview path (custom WebP uploads or protected fallback asset).
     *
     * @param mixed $value Raw path from JSON or hidden field.
     * @return string|null Normalized path or null when absent/invalid.
     * @date 2026-05-31
     * @author Stephane H.
     */
    public static function normalizeStoredPreviewPath(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '' || str_contains($trimmed, '..')) {
            return null;
        }

        if ($trimmed === self::DEFAULT_PROJECT_PREVIEW_IMAGE_PATH) {
            return $trimmed;
        }

        if (!str_starts_with($trimmed, self::PREVIEW_IMAGE_PATH_PREFIX)) {
            return null;
        }

        if (!preg_match('/^images\/cv\/projects\/custom\/project-[a-z0-9-]+\.webp$/i', $trimmed)) {
            return null;
        }

        return $trimmed;
    }

    /**
     * @brief Coerce stored or submitted values to a boolean section flag.
     *
     * @param mixed $raw Raw value from JSON or request.
     * @return bool Normalized enabled flag.
     * @date 2026-05-31
     * @author Stephane H.
     */
    public static function normalizeEnabled(mixed $raw): bool
    {
        if (is_array($raw)) {
            foreach ($raw as $value) {
                if (self::normalizeEnabled($value)) {
                    return true;
                }
            }

            return false;
        }

        if (is_bool($raw)) {
            return $raw;
        }

        if (is_int($raw) || is_float($raw)) {
            return (int) $raw === 1;
        }

        if (is_string($raw)) {
            $normalized = strtolower(trim($raw));
            if ($normalized === '1' || $normalized === 'true' || $normalized === 'on' || $normalized === 'yes') {
                return true;
            }
            if ($normalized === '0' || $normalized === 'false' || $normalized === 'off' || $normalized === 'no') {
                return false;
            }
        }

        return false;
    }

    /**
     * @brief Generate a random UUID v4 string.
     *
     * @return string
     * @date 2026-05-31
     * @author Stephane H.
     */
    public static function generateUuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return self::formatUuidBytes($bytes);
    }

    /**
     * @brief Build a stable UUID-shaped identifier from a seed string (for YAML fallback rows).
     *
     * @param string $seed Deterministic seed.
     * @return string
     * @date 2026-05-31
     * @author Stephane H.
     */
    public static function generateDeterministicUuid(string $seed): string
    {
        return \App\Service\Uuid\DeterministicUuidFactory::generate('cv-flagship', $seed);
    }

    /**
     * @brief Drop project cards whose default-locale title is empty.
     *
     * @param array<string, list<array<string, mixed>>> $rowsByLocale Rows keyed by locale.
     * @param string $defaultLocale Site default locale.
     * @return array<string, list<array<string, mixed>>>
     */
    private static function filterEmptyProjects(array $rowsByLocale, string $defaultLocale): array
    {
        $keepIds = [];
        foreach ($rowsByLocale[$defaultLocale] ?? [] as $row) {
            $id = is_string($row['id'] ?? null) ? trim($row['id']) : '';
            $title = trim((string) ($row['title'] ?? ''));
            if ($id !== '' && $title !== '') {
                $keepIds[$id] = true;
            }
        }

        foreach ($rowsByLocale as $locale => $rows) {
            $rowsByLocale[$locale] = array_values(array_filter(
                $rows,
                static fn (array $row): bool => isset($keepIds[(string) ($row['id'] ?? '')])
            ));
        }

        return $rowsByLocale;
    }

    /**
     * @param array<string, list<array<string, mixed>>> $rowsByLocale Rows keyed by locale.
     * @param string $defaultLocale Site default locale.
     * @param string $projectId Project UUID.
     * @return bool
     */
    private static function projectIdHasDefaultTitle(array $rowsByLocale, string $defaultLocale, string $projectId): bool
    {
        foreach ($rowsByLocale[$defaultLocale] ?? [] as $row) {
            if (($row['id'] ?? '') === $projectId) {
                return trim((string) ($row['title'] ?? '')) !== '';
            }
        }

        return false;
    }

    /**
     * @brief Copy missing localized text fields from the default locale row with the same project id.
     *
     * @param array<string, list<array<string, mixed>>> $rowsByLocale Rows keyed by locale.
     * @param string $defaultLocale Site default locale.
     * @return array<string, list<array<string, mixed>>>
     */
    private static function applyDefaultLocaleFallbacks(array $rowsByLocale, string $defaultLocale): array
    {
        /** @var array<string, array<string, mixed>> $defaultsById */
        $defaultsById = [];
        foreach ($rowsByLocale[$defaultLocale] ?? [] as $row) {
            $id = is_string($row['id'] ?? null) ? trim($row['id']) : '';
            if ($id !== '') {
                $defaultsById[$id] = $row;
            }
        }

        foreach ($rowsByLocale as $locale => $rows) {
            if ($locale === $defaultLocale) {
                continue;
            }

            foreach ($rows as $index => $row) {
                $id = is_string($row['id'] ?? null) ? trim($row['id']) : '';
                $defaultRow = $defaultsById[$id] ?? null;
                if (!is_array($defaultRow)) {
                    continue;
                }

                foreach (['title', 'description', 'previewAlt', 'siteLinkLabel'] as $field) {
                    if (trim((string) ($row[$field] ?? '')) === '' && trim((string) ($defaultRow[$field] ?? '')) !== '') {
                        $row[$field] = $defaultRow[$field];
                    }
                }

                if (self::isTagsEmpty($row['tags'] ?? null) && !self::isTagsEmpty($defaultRow['tags'] ?? null)) {
                    $row['tags'] = $defaultRow['tags'];
                }

                $rows[$index] = $row;
            }

            $rowsByLocale[$locale] = $rows;
        }

        return $rowsByLocale;
    }

    /**
     * @param mixed $value Raw tags payload.
     * @return bool
     */
    private static function isTagsEmpty(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (is_array($value)) {
            return $value === [];
        }

        return trim((string) $value) === '';
    }

    /**
     * @param list<string>|string|null $value Raw tags from textarea or JSON.
     * @return list<string>|null
     */
    private static function normalizeTags(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $items = [];
        if (is_string($value)) {
            $parts = preg_split('/\r\n|\r|\n|,/', $value) ?: [];
            foreach ($parts as $part) {
                $items[] = $part;
            }
        } elseif (is_array($value)) {
            $items = $value;
        } else {
            return null;
        }

        $tags = [];
        foreach ($items as $item) {
            if (!is_string($item)) {
                return null;
            }

            $tag = self::normalizeText($item, self::MAX_TAG_LENGTH);
            if ($tag === null || $tag === '') {
                continue;
            }

            $tags[] = $tag;
        }

        return $tags;
    }

    /**
     * @param mixed $value Raw scalar input.
     * @param int $maxLength Maximum length.
     * @return string|null
     */
    private static function normalizeText(mixed $value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }

        $trimmed = trim((string) $value);
        if ($trimmed === '') {
            return '';
        }

        if (mb_strlen($trimmed) > $maxLength) {
            return null;
        }

        return $trimmed;
    }

    /**
     * @brief Whether a submitted project id matches the expected UUID format.
     *
     * @param string $id Candidate project id from the admin form.
     * @return bool True when the id is valid.
     * @date 2026-05-31
     * @author Stephane H.
     */
    public static function isValidProjectId(string $id): bool
    {
        return self::isValidUuid(trim($id));
    }

    /**
     * @brief Return true when the submitted URL is empty or a valid http(s) URL.
     *
     * @param mixed $value Raw URL input from the admin form.
     * @return bool False when a non-empty value cannot be normalized.
     * @date 2026-05-31
     * @author Stephane H.
     */
    public static function isEmptyOrValidHttpUrl(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (!is_string($value)) {
            return false;
        }

        if (trim($value) === '') {
            return true;
        }

        return self::normalizeHttpUrl($value) !== null;
    }

    /**
     * @param mixed $value Raw URL input.
     * @return string|null
     */
    private static function normalizeHttpUrl(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        if (mb_strlen($trimmed) > self::MAX_URL_LENGTH) {
            return null;
        }

        if (!filter_var($trimmed, FILTER_VALIDATE_URL)) {
            return null;
        }

        $scheme = strtolower((string) parse_url($trimmed, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        return $trimmed;
    }

    /**
     * @param string $id Candidate UUID.
     * @return bool
     */
    private static function isValidUuid(string $id): bool
    {
        return (bool) preg_match(self::UUID_SHAPE_PATTERN, $id);
    }

    /**
     * @param string $bytes Raw 16-byte UUID payload.
     * @return string
     */
    private static function formatUuidBytes(string $bytes): string
    {
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }
}
