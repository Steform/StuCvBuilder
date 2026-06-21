<?php

declare(strict_types=1);

namespace App\Service\Cv;

use App\Cv\SkillsTreeContract;

/**
 * @brief Detects and strips cv-symfony8 demo seeds still stored in CvProfile content JSON.
 */
final class CvLegacySeedContentService
{
    public const DEPRECATED_ENTRY_ID_PREFIX = 'fallback-';

    /**
     * @var list<string> Legacy certification fallback entry keys from cv-symfony8 YAML seeds.
     */
    public const DEPRECATED_CERTIFICATION_ENTRY_KEYS = [
        'entry_funmooc_python',
        'entry_udemy_docker',
        'entry_oc_tcpip',
        'entry_anssi_secnum',
        'entry_bt_telephone',
    ];

    /**
     * @var list<string> Legacy flagship project titles treated as demo seeds.
     */
    public const DEPRECATED_PROJECT_TITLE_MARKERS = [
        'StuSlider',
        'Steform',
    ];

    /**
     * @var list<string> Legacy URL or asset path fragments for flagship demo projects.
     */
    public const DEPRECATED_PROJECT_URL_MARKERS = [
        'github.com/Steform',
        'github.com/steform',
        'stuslider-demo',
        'steform.fr',
    ];

    /**
     * @var list<string> Legacy employer or institution names from cv-symfony8 demo entries.
     */
    public const DEPRECATED_ORGANIZATION_MARKERS = [
        'CKELPROCESS',
    ];

    /**
     * @var list<string> Legacy Situation intro or chip markers copied from cv-symfony8 seeds.
     */
    public const DEPRECATED_SITUATION_TEXT_MARKERS = [
        'focus COBOL',
        'COBOL:primary',
        'COBOL:secondary',
        'CDI en remote (idéalement 100 % remote)',
        'remote (ideally 100% remote)',
        'France, Lituanie ou Norvège',
        'France, Lithuania or Norway',
    ];

    /**
     * @var list<string> Skill labels that together fingerprint the removed Symfony/React demo catalog.
     */
    public const DEPRECATED_SKILLS_DEMO_LABEL_MARKERS = [
        'Symfony',
        'React',
        'Docker',
        'MySQL',
    ];

    /**
     * @brief Remove legacy seeded CV content from a decoded profile payload (idempotent).
     *
     * @param array<string, mixed> $payload Decoded CvProfile content JSON.
     * @return array<string, mixed> Sanitized payload without cv-symfony8 demo seeds.
     * @date 2026-06-21
     * @author Stephane H.
     */
    public static function stripLegacySeededContent(array $payload): array
    {
        self::stripLegacyFlagshipProjects($payload);
        self::stripLegacyCertificationEntries($payload);
        self::stripLegacyExperienceEntries($payload);
        self::stripLegacyEducationEntries($payload);
        self::stripLegacySituationContent($payload);
        self::stripLegacySkillsCatalog($payload);

        return $payload;
    }

    /**
     * @brief Whether an entry id matches deterministic cv-symfony8 fallback ids.
     *
     * @param mixed $entryId Raw entry id from persisted JSON.
     * @return bool True when the id is a known legacy fallback identifier.
     * @date 2026-06-21
     * @author Stephane H.
     */
    public static function isDeprecatedFallbackEntryId(mixed $entryId): bool
    {
        if (!is_string($entryId) || $entryId === '') {
            return false;
        }

        if (str_starts_with($entryId, self::DEPRECATED_ENTRY_ID_PREFIX)) {
            return true;
        }

        foreach (self::DEPRECATED_CERTIFICATION_ENTRY_KEYS as $legacyKey) {
            if ($entryId === $legacyKey || $entryId === self::DEPRECATED_ENTRY_ID_PREFIX.$legacyKey) {
                return true;
            }
        }

        return false;
    }

    /**
     * @brief Whether a Situation locale payload still contains cv-symfony8 COBOL seed copy.
     *
     * @param array<string, mixed> $localeContent Situation content row for one locale.
     * @return bool True when the row matches deprecated Situation seed markers.
     * @date 2026-06-21
     * @author Stephane H.
     */
    public static function isLegacySituationLocaleContent(array $localeContent): bool
    {
        $haystack = strtolower(json_encode($localeContent, JSON_THROW_ON_ERROR));

        foreach (self::DEPRECATED_SITUATION_TEXT_MARKERS as $marker) {
            if (str_contains($haystack, strtolower($marker))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @brief Whether a skills catalog still matches the removed Symfony/React demo tree.
     *
     * @param mixed $catalog Raw skillsCatalog payload.
     * @return bool True when the catalog fingerprints the legacy demo tree.
     * @date 2026-06-21
     * @author Stephane H.
     */
    public static function isLegacyDemoSkillsCatalog(mixed $catalog): bool
    {
        if (!is_array($catalog)) {
            return false;
        }

        $categories = $catalog['categories'] ?? null;
        if (!is_array($categories) || $categories === []) {
            return false;
        }

        $labels = self::collectSkillsCatalogLabels($categories);
        if ($labels === []) {
            return false;
        }

        $matchedMarkers = 0;
        foreach (self::DEPRECATED_SKILLS_DEMO_LABEL_MARKERS as $marker) {
            foreach ($labels as $label) {
                if (strcasecmp($label, $marker) === 0) {
                    ++$matchedMarkers;
                    break;
                }
            }
        }

        return $matchedMarkers >= 3;
    }

    /**
     * @param array<string, mixed> $payload Profile payload mutated in place.
     */
    private static function stripLegacyFlagshipProjects(array &$payload): void
    {
        $raw = $payload[FlagshipProjectsContract::KEY_ENTRIES_BY_LOCALE] ?? null;
        if (!is_array($raw)) {
            return;
        }

        $changed = false;
        foreach ($raw as $locale => $entries) {
            if (!is_string($locale) || !is_array($entries)) {
                continue;
            }

            $filtered = [];
            foreach ($entries as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                if (self::isLegacyFlagshipProjectEntry($entry)) {
                    $changed = true;
                    continue;
                }

                $filtered[] = $entry;
            }

            if ($filtered === []) {
                unset($raw[$locale]);
                $changed = true;
                continue;
            }

            $raw[$locale] = array_values($filtered);
        }

        if (!$changed) {
            return;
        }

        if ($raw === []) {
            unset($payload[FlagshipProjectsContract::KEY_ENTRIES_BY_LOCALE]);
            return;
        }

        $payload[FlagshipProjectsContract::KEY_ENTRIES_BY_LOCALE] = $raw;
    }

    /**
     * @param array<string, mixed> $entry Flagship project row.
     */
    private static function isLegacyFlagshipProjectEntry(array $entry): bool
    {
        if (self::isDeprecatedFallbackEntryId($entry['id'] ?? null)) {
            return true;
        }

        $title = is_string($entry['title'] ?? null) ? $entry['title'] : '';
        foreach (self::DEPRECATED_PROJECT_TITLE_MARKERS as $marker) {
            if (strcasecmp($title, $marker) === 0) {
                return true;
            }
        }

        $urlHaystack = strtolower(implode(' ', array_filter([
            is_string($entry['githubUrl'] ?? null) ? $entry['githubUrl'] : '',
            is_string($entry['demoUrl'] ?? null) ? $entry['demoUrl'] : '',
            is_string($entry['previewImagePath'] ?? null) ? $entry['previewImagePath'] : '',
        ], static fn (string $value): bool => $value !== '')));

        foreach (self::DEPRECATED_PROJECT_URL_MARKERS as $marker) {
            if (str_contains($urlHaystack, strtolower($marker))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $payload Profile payload mutated in place.
     */
    private static function stripLegacyCertificationEntries(array &$payload): void
    {
        $raw = $payload[CertificationContract::KEY_ENTRIES] ?? null;
        if (!is_array($raw)) {
            return;
        }

        $filtered = [];
        $changed = false;
        foreach ($raw as $entry) {
            if (!is_array($entry)) {
                $changed = true;
                continue;
            }

            if (self::isLegacyCertificationEntry($entry)) {
                $changed = true;
                continue;
            }

            $filtered[] = $entry;
        }

        if (!$changed) {
            return;
        }

        if ($filtered === []) {
            unset($payload[CertificationContract::KEY_ENTRIES]);
            return;
        }

        $payload[CertificationContract::KEY_ENTRIES] = array_values($filtered);
    }

    /**
     * @param array<string, mixed> $entry Certification row.
     */
    private static function isLegacyCertificationEntry(array $entry): bool
    {
        if (self::isDeprecatedFallbackEntryId($entry['id'] ?? null)) {
            return true;
        }

        $encoded = strtolower(json_encode($entry, JSON_THROW_ON_ERROR));
        foreach (self::DEPRECATED_ORGANIZATION_MARKERS as $marker) {
            if (str_contains($encoded, strtolower($marker))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $payload Profile payload mutated in place.
     */
    private static function stripLegacyExperienceEntries(array &$payload): void
    {
        self::stripLegacyLocalizedEntries(
            $payload,
            ExperienceContract::KEY_ENTRIES_BY_LOCALE,
            static fn (array $entry): bool => self::isLegacyExperienceEntry($entry),
        );
    }

    /**
     * @param array<string, mixed> $payload Profile payload mutated in place.
     */
    private static function stripLegacyEducationEntries(array &$payload): void
    {
        self::stripLegacyLocalizedEntries(
            $payload,
            EducationContract::KEY_ENTRIES_BY_LOCALE,
            static fn (array $entry): bool => self::isLegacyEducationEntry($entry),
        );
    }

    /**
     * @param array<string, mixed> $payload Profile payload mutated in place.
     * @param string $key Localized entries map key.
     * @param callable(array<string, mixed>): bool $isLegacyEntry Legacy detector callback.
     */
    private static function stripLegacyLocalizedEntries(array &$payload, string $key, callable $isLegacyEntry): void
    {
        $raw = $payload[$key] ?? null;
        if (!is_array($raw)) {
            return;
        }

        $changed = false;
        foreach ($raw as $locale => $entries) {
            if (!is_string($locale) || !is_array($entries)) {
                continue;
            }

            $filtered = [];
            foreach ($entries as $entry) {
                if (!is_array($entry)) {
                    $changed = true;
                    continue;
                }

                if ($isLegacyEntry($entry)) {
                    $changed = true;
                    continue;
                }

                $filtered[] = $entry;
            }

            if ($filtered === []) {
                unset($raw[$locale]);
                $changed = true;
                continue;
            }

            $raw[$locale] = array_values($filtered);
        }

        if (!$changed) {
            return;
        }

        if ($raw === []) {
            unset($payload[$key]);
            return;
        }

        $payload[$key] = $raw;
    }

    /**
     * @param array<string, mixed> $entry Experience row.
     */
    private static function isLegacyExperienceEntry(array $entry): bool
    {
        if (self::isDeprecatedFallbackEntryId($entry['id'] ?? null)) {
            return true;
        }

        $companyName = is_string($entry['companyName'] ?? null) ? $entry['companyName'] : '';
        foreach (self::DEPRECATED_ORGANIZATION_MARKERS as $marker) {
            if (strcasecmp($companyName, $marker) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $entry Education row.
     */
    private static function isLegacyEducationEntry(array $entry): bool
    {
        if (self::isDeprecatedFallbackEntryId($entry['id'] ?? null)) {
            return true;
        }

        $institutionName = is_string($entry['institutionName'] ?? null) ? $entry['institutionName'] : '';
        foreach (self::DEPRECATED_ORGANIZATION_MARKERS as $marker) {
            if (strcasecmp($institutionName, $marker) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $payload Profile payload mutated in place.
     */
    private static function stripLegacySituationContent(array &$payload): void
    {
        $raw = $payload[SituationContentContract::KEY_CONTENT_BY_LOCALE] ?? null;
        if (!is_array($raw)) {
            return;
        }

        $changed = false;
        foreach ($raw as $locale => $localeContent) {
            if (!is_string($locale) || !is_array($localeContent)) {
                continue;
            }

            if (!self::isLegacySituationLocaleContent($localeContent)) {
                continue;
            }

            unset($raw[$locale]);
            $changed = true;
        }

        if (!$changed) {
            return;
        }

        if ($raw === []) {
            unset($payload[SituationContentContract::KEY_CONTENT_BY_LOCALE]);
            return;
        }

        $payload[SituationContentContract::KEY_CONTENT_BY_LOCALE] = $raw;
    }

    /**
     * @param array<string, mixed> $payload Profile payload mutated in place.
     */
    private static function stripLegacySkillsCatalog(array &$payload): void
    {
        if (!array_key_exists(SkillsTreeContract::KEY, $payload)) {
            return;
        }

        if (!self::isLegacyDemoSkillsCatalog($payload[SkillsTreeContract::KEY])) {
            return;
        }

        unset($payload[SkillsTreeContract::KEY]);
    }

    /**
     * @brief Collect human-readable labels from a skills catalog tree.
     *
     * @param list<mixed> $categories Catalog category nodes.
     * @return list<string> Lowercase-trimmed labels.
     * @date 2026-06-21
     * @author Stephane H.
     */
    private static function collectSkillsCatalogLabels(array $categories): array
    {
        $labels = [];

        foreach ($categories as $category) {
            if (!is_array($category)) {
                continue;
            }

            self::appendSkillNodeLabels($category, $labels);

            foreach ($category['subcategories'] ?? [] as $subcategory) {
                if (!is_array($subcategory)) {
                    continue;
                }

                self::appendSkillNodeLabels($subcategory, $labels);

                foreach ($subcategory['groups'] ?? [] as $group) {
                    if (!is_array($group)) {
                        continue;
                    }

                    self::appendSkillNodeLabels($group, $labels);
                }
            }
        }

        return array_values(array_unique($labels));
    }

    /**
     * @param array<string, mixed> $node Catalog node with optional labels and items.
     * @param list<string> $labels Label accumulator mutated in place.
     */
    private static function appendSkillNodeLabels(array $node, array &$labels): void
    {
        if (is_string($node['canonicalLabel'] ?? null) && trim($node['canonicalLabel']) !== '') {
            $labels[] = trim($node['canonicalLabel']);
        }

        if (is_array($node['labelsByLocale'] ?? null)) {
            foreach ($node['labelsByLocale'] as $label) {
                if (is_string($label) && trim($label) !== '') {
                    $labels[] = trim($label);
                }
            }
        }

        foreach ($node['items'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }

            self::appendSkillNodeLabels($item, $labels);
        }
    }
}
