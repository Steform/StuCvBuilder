<?php

declare(strict_types=1);

namespace App\Service\Cv;

use Symfony\Component\HttpFoundation\Request;

/**
 * @brief Collect per-field validation errors for flagship project admin submissions.
 *
 * @date 2026-05-31
 * @author Stephane H.
 */
final class FlagshipProjectsFormValidator
{
    private const MESSAGE_PREFIX = 'dashboard.customization_cv.flagship_projects.validation.';

    /**
     * @brief Validate all submitted flagship project cards from the admin form.
     *
     * @param Request $request HTTP request with `flagship_projects` POST fields.
     * @param list<string> $activeLocales Site active locale codes.
     * @param string $defaultLocale Site default locale code.
     * @return list<array{message: string, parameters: array<string, string>}> Flash-ready error payloads.
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function validateRequest(Request $request, array $activeLocales, string $defaultLocale): array
    {
        $raw = $request->request->all('flagship_projects');
        if (!is_array($raw)) {
            return [$this->error('form_structure_invalid', [])];
        }

        $entriesRaw = $raw['entries'] ?? null;
        if (!is_array($entriesRaw)) {
            return [$this->error('form_structure_invalid', [])];
        }

        if (count($entriesRaw) > FlagshipProjectsContract::MAX_PROJECTS_PER_LOCALE) {
            return [$this->error('too_many_projects', [
                '%max%' => (string) FlagshipProjectsContract::MAX_PROJECTS_PER_LOCALE,
            ])];
        }

        /** @var list<array{id: string, sortOrder: int, entry: array<string, mixed>}> $canonicalRows */
        $canonicalRows = [];
        $errors = [];

        foreach ($entriesRaw as $projectId => $entry) {
            if (!is_string($projectId) || !is_array($entry)) {
                $errors[] = $this->error('form_structure_invalid', []);

                continue;
            }

            $projectId = trim($projectId);
            if (!FlagshipProjectsContract::isValidProjectId($projectId)) {
                $errors[] = $this->error('invalid_project_id', [
                    '%index%' => (string) (count($canonicalRows) + 1),
                ]);

                continue;
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

        foreach ($canonicalRows as $index => $item) {
            $displayIndex = (string) ($index + 1);
            $entry = $item['entry'];
            $localeRows = is_array($entry['locales'] ?? null) ? $entry['locales'] : [];

            $errors = array_merge($errors, $this->validateSharedFields($entry, $displayIndex));

            foreach ($activeLocales as $locale) {
                $localeRow = is_array($localeRows[$locale] ?? null) ? $localeRows[$locale] : [];
                $errors = array_merge(
                    $errors,
                    $this->validateLocaleFields($localeRow, $displayIndex, $locale, $defaultLocale)
                );
            }
        }

        return $errors;
    }

    /**
     * @brief Validate URLs and preview path shared across locales for one project card.
     *
     * @param array<string, mixed> $entry Raw project card payload.
     * @param string $displayIndex 1-based project index for messages.
     * @return list<array{message: string, parameters: array<string, string>}>
     * @date 2026-05-31
     * @author Stephane H.
     */
    private function validateSharedFields(array $entry, string $displayIndex): array
    {
        $errors = [];

        if (!FlagshipProjectsContract::isEmptyOrValidHttpUrl($entry['github_url'] ?? null)) {
            $errors[] = $this->error('code_url_invalid', ['%index%' => $displayIndex]);
        }

        if (!FlagshipProjectsContract::isEmptyOrValidHttpUrl($entry['demo_url'] ?? null)) {
            $errors[] = $this->error('site_url_invalid', ['%index%' => $displayIndex]);
        }

        $previewPath = $entry['preview_image_path'] ?? null;
        if (is_string($previewPath) && trim($previewPath) !== ''
            && FlagshipProjectsContract::normalizeStoredPreviewPath($previewPath) === null) {
            $errors[] = $this->error('preview_path_invalid', ['%index%' => $displayIndex]);
        }

        return $errors;
    }

    /**
     * @brief Validate localized text fields for one project card.
     *
     * @param array<string, mixed> $row Locale-specific submitted row.
     * @param string $displayIndex 1-based project index for messages.
     * @param string $locale Locale code being validated.
     * @param string $defaultLocale Site default locale code.
     * @return list<array{message: string, parameters: array<string, string>}>
     * @date 2026-05-31
     * @author Stephane H.
     */
    private function validateLocaleFields(
        array $row,
        string $displayIndex,
        string $locale,
        string $defaultLocale
    ): array {
        $errors = [];
        $localeCode = strtoupper($locale);
        $params = [
            '%index%' => $displayIndex,
            '%locale%' => $localeCode,
        ];

        $title = $this->rawString($row['title'] ?? null);
        if ($locale === $defaultLocale && $title === '') {
            $errors[] = $this->error('title_required', $params);
        } elseif ($title !== '' && mb_strlen($title) > FlagshipProjectsContract::MAX_TITLE_LENGTH) {
            $errors[] = $this->error('title_too_long', $params + [
                '%max%' => (string) FlagshipProjectsContract::MAX_TITLE_LENGTH,
            ]);
        }

        $description = $this->rawString($row['description'] ?? null);
        if ($description !== '' && mb_strlen($description) > FlagshipProjectsContract::MAX_DESCRIPTION_LENGTH) {
            $errors[] = $this->error('description_too_long', $params + [
                '%max%' => (string) FlagshipProjectsContract::MAX_DESCRIPTION_LENGTH,
            ]);
        }

        $errors = array_merge($errors, $this->validateTags($row['tags'] ?? null, $params));

        $siteLinkLabel = $this->rawString($row['site_link_label'] ?? null);
        if ($siteLinkLabel !== '' && mb_strlen($siteLinkLabel) > FlagshipProjectsContract::MAX_SITE_LINK_LABEL_LENGTH) {
            $errors[] = $this->error('site_link_label_too_long', $params + [
                '%max%' => (string) FlagshipProjectsContract::MAX_SITE_LINK_LABEL_LENGTH,
            ]);
        }

        $previewAlt = $this->rawString($row['preview_alt'] ?? null);
        if ($previewAlt !== '' && mb_strlen($previewAlt) > FlagshipProjectsContract::MAX_PREVIEW_ALT_LENGTH) {
            $errors[] = $this->error('preview_alt_too_long', $params + [
                '%max%' => (string) FlagshipProjectsContract::MAX_PREVIEW_ALT_LENGTH,
            ]);
        }

        return $errors;
    }

    /**
     * @brief Validate tag textarea content for one locale row.
     *
     * @param mixed $value Raw tags from textarea.
     * @param array<string, string> $params Base message parameters.
     * @return list<array{message: string, parameters: array<string, string>}>
     * @date 2026-06-11
     * @author Stephane H.
     */
    private function validateTags(mixed $value, array $params): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (!is_string($value) && !is_array($value)) {
            return [$this->error('tags_invalid', $params)];
        }

        $items = [];
        if (is_string($value)) {
            $parts = preg_split('/\r\n|\r|\n|,/', $value) ?: [];
            foreach ($parts as $part) {
                $items[] = $part;
            }
        } else {
            $items = $value;
        }

        $errors = [];
        $tags = [];
        foreach ($items as $item) {
            if (!is_string($item) && !is_numeric($item)) {
                $errors[] = $this->error('tags_invalid', $params);

                continue;
            }

            $tag = trim((string) $item);
            if ($tag === '') {
                continue;
            }

            if (mb_strlen($tag) > FlagshipProjectsContract::MAX_TAG_LENGTH) {
                $errors[] = $this->error('tag_too_long', $params + [
                    '%max%' => (string) FlagshipProjectsContract::MAX_TAG_LENGTH,
                ]);

                continue;
            }

            $tags[] = $tag;
        }

        return $errors;
    }

    /**
     * @brief Normalize optional scalar input to trimmed string.
     *
     * @param mixed $value Raw submitted value.
     * @return string Trimmed string or empty string when absent.
     * @date 2026-05-31
     * @author Stephane H.
     */
    private function rawString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (!is_string($value) && !is_numeric($value)) {
            return '';
        }

        return trim((string) $value);
    }

    /**
     * @brief Build one flash-ready validation error payload.
     *
     * @param string $key Validation message suffix without prefix.
     * @param array<string, string> $parameters Translation placeholders.
     * @return array{message: string, parameters: array<string, string>}
     * @date 2026-05-31
     * @author Stephane H.
     */
    private function error(string $key, array $parameters): array
    {
        return [
            'message' => self::MESSAGE_PREFIX.$key,
            'parameters' => $parameters,
        ];
    }
}
