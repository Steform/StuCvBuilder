<?php

declare(strict_types=1);

namespace App\Cv;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @brief Hierarchical CV skills catalog (3 category levels + skill items) with primary/secondary visibility.
 *
 * @date 2026-05-29
 * @author Stephane H.
 */
final class SkillsTreeContract
{
    public const KEY = 'skillsCatalog';

    public const SKILL_ICON_PATH_PREFIX = 'images/cv/skills/custom/';

    public const LABEL_MODE_CANONICAL = 'canonical';

    public const LABEL_MODE_LOCALIZED = 'localized';

    public const ICON_TYPE_BOOTSTRAP = 'bootstrap';

    public const ICON_TYPE_IMAGE = 'image';

    public const MAX_CATEGORIES = 32;

    public const MAX_SUBCATEGORIES_PER_CATEGORY = 16;

    public const MAX_GROUPS_PER_SUBCATEGORY = 16;

    public const MAX_ITEMS_PER_CONTAINER = 24;

    public const MAX_LABEL_LENGTH = 120;

    public const MAX_ICON_LENGTH = 64;

    public const GRID_ROOT_BUDGET = 12;

    public const LAYOUT_BREAKPOINT_DESKTOP = 'desktop';

    public const LAYOUT_BREAKPOINT_TABLET = 'tablet';

    public const LAYOUT_BREAKPOINT_MOBILE = 'mobile';

    private const ICON_PATTERN = '/^bi-[a-z0-9-]+$/';

    /**
     * @brief Read stored catalog from profile payload or return default tree labels.
     *
     * @param array<string, mixed> $payload CvProfile content JSON.
     * @param list<string> $activeLocales Active locale codes.
     * @param string $defaultLocale Site default locale.
     * @param TranslatorInterface $translator Symfony translator for default seed labels.
     * @return array{categories: list<array<string, mixed>>}
     * @date 2026-05-29
     * @author Stephane H.
     */
    public static function resolveCatalogFromPayload(
        array $payload,
        array $activeLocales,
        string $defaultLocale,
        TranslatorInterface $translator,
    ): array {
        $stored = $payload[self::KEY] ?? null;
        if (is_array($stored) && ($stored['categories'] ?? null) !== null) {
            $normalized = self::normalizeCatalog($stored, $activeLocales, $defaultLocale);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return self::defaultCatalog($activeLocales, $defaultLocale, $translator);
    }

    /**
     * @brief Normalize catalog array from storage or admin POST.
     *
     * @param mixed $raw Raw catalog structure.
     * @param list<string> $activeLocales Active locale codes.
     * @param string $defaultLocale Site default locale.
     * @return array{categories: list<array<string, mixed>>}|null Null when structure is invalid.
     * @date 2026-05-29
     * @author Stephane H.
     */
    public static function normalizeCatalog(mixed $raw, array $activeLocales, string $defaultLocale): ?array
    {
        if (!is_array($raw)) {
            return null;
        }

        $categoriesRaw = $raw['categories'] ?? null;
        if (!is_array($categoriesRaw)) {
            return null;
        }

        if (count($categoriesRaw) > self::MAX_CATEGORIES) {
            return null;
        }

        $categories = [];
        $sortOrder = 0;
        foreach ($categoriesRaw as $categoryRaw) {
            if (!is_array($categoryRaw)) {
                return null;
            }

            $category = self::normalizeCategory($categoryRaw, $activeLocales, $defaultLocale, $sortOrder);
            if ($category === null) {
                return null;
            }

            $categories[] = $category;
            ++$sortOrder;
        }

        usort($categories, static fn (array $a, array $b): int => ((int) ($a['sortOrder'] ?? 0)) <=> ((int) ($b['sortOrder'] ?? 0)));

        return ['categories' => $categories];
    }

    /**
     * @brief Parse admin POST `skills_catalog` field into a normalized catalog.
     *
     * @param Request $request HTTP request.
     * @param list<string> $activeLocales Active locale codes.
     * @param string $defaultLocale Site default locale.
     * @return array{categories: list<array<string, mixed>>}|null Null when invalid.
     * @date 2026-05-29
     * @author Stephane H.
     */
    public static function parseCatalogFromRequest(Request $request, array $activeLocales, string $defaultLocale): ?array
    {
        $raw = $request->request->all('skills_catalog');

        return self::normalizeCatalog($raw, $activeLocales, $defaultLocale);
    }

    /**
     * @brief Merge normalized catalog into profile payload.
     *
     * @param array<string, mixed> $payload Existing payload.
     * @param array{categories: list<array<string, mixed>>} $catalog Normalized catalog.
     * @return array<string, mixed> Updated payload.
     * @date 2026-05-29
     * @author Stephane H.
     */
    public static function mergeCatalogIntoPayload(array $payload, array $catalog): array
    {
        $payload[self::KEY] = $catalog;

        return $payload;
    }

    /**
     * @brief Whether any skill content is hidden on the primary CV view.
     *
     * @param array{categories: list<array<string, mixed>>} $catalog Normalized catalog.
     * @return bool True when secondary page should be linked.
     * @date 2026-05-29
     * @author Stephane H.
     */
    public static function hasSecondaryVisible(array $catalog): bool
    {
        foreach ($catalog['categories'] as $category) {
            if (self::nodeHasSecondaryContent($category, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @brief Filter catalog for the primary CV section (cascade visibility).
     *
     * @param array{categories: list<array<string, mixed>>} $catalog Normalized catalog.
     * @param string $locale Viewer locale.
     * @param string $defaultLocale Site default locale.
     * @return array{categories: list<array<string, mixed>>}
     * @date 2026-05-29
     * @author Stephane H.
     */
    public static function filterForPrimary(array $catalog, string $locale, string $defaultLocale): array
    {
        $categories = [];
        foreach ($catalog['categories'] as $category) {
            $filtered = self::filterCategoryForPrimary($category, $locale, $defaultLocale, true);
            if ($filtered !== null) {
                $categories[] = $filtered;
            }
        }

        return ['categories' => $categories];
    }

    /**
     * @brief Filter catalog for the full skills page (hidden-on-primary content only).
     *
     * @param array{categories: list<array<string, mixed>>} $catalog Normalized catalog.
     * @param string $locale Viewer locale.
     * @param string $defaultLocale Site default locale.
     * @return array{categories: list<array<string, mixed>>}
     * @date 2026-05-29
     * @author Stephane H.
     */
    public static function filterForSecondary(array $catalog, string $locale, string $defaultLocale): array
    {
        $categories = [];
        foreach ($catalog['categories'] as $category) {
            $filtered = self::filterCategoryForSecondary($category, $locale, $defaultLocale, true);
            if ($filtered !== null) {
                $categories[] = $filtered;
            }
        }

        return ['categories' => $categories];
    }

    /**
     * @brief Filter catalog for the full skills page (complete tree with hidden-on-primary flags).
     *
     * @param array{categories: list<array<string, mixed>>} $catalog Normalized catalog.
     * @param string $locale Viewer locale.
     * @param string $defaultLocale Default locale.
     * @return array{categories: list<array<string, mixed>>}
     * @date 2026-05-31
     * @author Stephane H.
     */
    public static function filterForFull(array $catalog, string $locale, string $defaultLocale): array
    {
        $categories = [];
        foreach ($catalog['categories'] as $category) {
            $filtered = self::filterCategoryForFull($category, $locale, $defaultLocale, true);
            if ($filtered !== null) {
                $categories[] = $filtered;
            }
        }

        return ['categories' => $categories];
    }

    /**
     * @brief Resolve localized label for a node.
     *
     * @param array<string, mixed> $node Node with `labelsByLocale`.
     * @param string $locale Preferred locale.
     * @param string $defaultLocale Fallback locale.
     * @return string Non-empty label when available.
     * @date 2026-05-29
     * @author Stephane H.
     */
    public static function labelForLocale(array $node, string $locale, string $defaultLocale): string
    {
        $labelMode = is_string($node['labelMode'] ?? null) ? $node['labelMode'] : self::LABEL_MODE_LOCALIZED;
        if ($labelMode === self::LABEL_MODE_CANONICAL) {
            $canonical = trim((string) ($node['canonicalLabel'] ?? ''));

            return $canonical !== '' ? $canonical : '';
        }

        $labels = is_array($node['labelsByLocale'] ?? null) ? $node['labelsByLocale'] : [];
        $candidate = $labels[$locale] ?? $labels[$defaultLocale] ?? '';
        if (!is_string($candidate)) {
            $candidate = '';
        }

        $trimmed = trim($candidate);

        if ($trimmed !== '') {
            return $trimmed;
        }

        foreach ($labels as $label) {
            if (is_string($label) && trim($label) !== '') {
                return trim($label);
            }
        }

        $canonicalFallback = trim((string) ($node['canonicalLabel'] ?? ''));

        return $canonicalFallback;
    }

    /**
     * @brief Generate a stable node id for new catalog entities.
     *
     * @param string $prefix Id prefix (`cat`, `sub`, `grp`, `item`).
     * @return string Normalized id.
     * @date 2026-05-29
     * @author Stephane H.
     */
    public static function generateNodeId(string $prefix): string
    {
        $safePrefix = preg_replace('/[^a-z]/', '', strtolower($prefix)) ?: 'node';

        return $safePrefix.'-'.bin2hex(random_bytes(8));
    }

    /**
     * @brief Whether a level-1 category can be deleted (no children).
     *
     * @param array<string, mixed> $category Category node.
     * @return bool True when deletion is allowed.
     * @date 2026-05-29
     * @author Stephane H.
     */
    public static function canDeleteCategory(array $category): bool
    {
        $subs = $category['subcategories'] ?? [];
        $items = $category['items'] ?? [];

        return (!is_array($subs) || $subs === []) && (!is_array($items) || $items === []);
    }

    /**
     * @brief Whether a level-2 subcategory can be deleted (no groups nor skills).
     *
     * @param array<string, mixed> $subcategory Subcategory node.
     * @return bool True when deletion is allowed.
     * @date 2026-05-29
     * @author Stephane H.
     */
    public static function canDeleteSubcategory(array $subcategory): bool
    {
        $groups = $subcategory['groups'] ?? [];
        $items = $subcategory['items'] ?? [];

        return (!is_array($groups) || $groups === []) && (!is_array($items) || $items === []);
    }

    /**
     * @brief Whether a level-3 group can be deleted (no skills).
     *
     * @param array<string, mixed> $group Group node.
     * @return bool True when deletion is allowed.
     * @date 2026-05-29
     * @author Stephane H.
     */
    public static function canDeleteGroup(array $group): bool
    {
        $items = $group['items'] ?? [];

        return !is_array($items) || $items === [];
    }

    /**
     * @brief Collect relative icon paths referenced by the catalog for backup or purge.
     *
     * @param array{categories: list<array<string, mixed>>} $catalog Normalized catalog.
     * @return list<string> Relative public paths.
     * @date 2026-05-29
     * @author Stephane H.
     */
    public static function collectSkillIconPaths(array $catalog): array
    {
        $paths = [];
        foreach ($catalog['categories'] as $category) {
            if (!is_array($category)) {
                continue;
            }

            self::collectItemIconPaths(is_array($category['items'] ?? null) ? $category['items'] : [], $paths);

            foreach ($category['subcategories'] ?? [] as $subcategory) {
                if (!is_array($subcategory)) {
                    continue;
                }

                self::collectItemIconPaths(is_array($subcategory['items'] ?? null) ? $subcategory['items'] : [], $paths);
                foreach ($subcategory['groups'] ?? [] as $group) {
                    if (!is_array($group)) {
                        continue;
                    }

                    self::collectItemIconPaths(is_array($group['items'] ?? null) ? $group['items'] : [], $paths);
                }
            }
        }

        return array_keys($paths);
    }

    /**
     * @brief Build empty default catalog for fresh installs (no demo skill tree).
     *
     * @param list<string> $activeLocales Active locale codes.
     * @param string $defaultLocale Site default locale.
     * @param TranslatorInterface $translator Symfony translator.
     * @return array{categories: list<array<string, mixed>>}
     * @date 2026-05-29
     * @author Stephane H.
     */
    public static function defaultCatalog(
        array $activeLocales,
        string $defaultLocale,
        TranslatorInterface $translator,
    ): array {
        unset($activeLocales, $defaultLocale, $translator);

        return ['categories' => []];
    }

    /**
     * @brief Return the maximum child span for a breakpoint from a parent layout node.
     *
     * @param array{desktop?: array{span?: int}, tablet?: array{span?: int}, mobile?: array{span?: int}} $parentLayout Parent layout.
     * @param string $breakpoint Layout breakpoint (`desktop`, `tablet`, or `mobile`).
     * @return int Maximum child span (1–12).
     * @date 2026-06-12
     * @author Stephane H.
     */
    public static function maxChildSpan(array $parentLayout, string $breakpoint): int
    {
        if ($breakpoint === self::LAYOUT_BREAKPOINT_TABLET && !isset($parentLayout['tablet']['span'])) {
            $breakpoint = self::LAYOUT_BREAKPOINT_MOBILE;
        }

        $span = $parentLayout[$breakpoint]['span'] ?? self::GRID_ROOT_BUDGET;

        return max(1, min(self::GRID_ROOT_BUDGET, (int) $span));
    }

    /**
     * @brief Resolve migration defaults when a node has no explicit layout in storage.
     *
     * @param int $level Category depth (1 = root category, 2 = subcategory, 3 = group).
     * @param int $parentSpanDesktop Parent desktop span budget.
     * @param int $parentSpanTablet Parent tablet span budget.
     * @param int $parentSpanMobile Parent mobile span budget.
     * @param bool $hasSubTree Whether the node has child categories or groups.
     * @param int $itemCount Direct skill item count on the node.
     * @return array{desktop: array{span: int}, tablet: array{span: int}, mobile: array{span: int}}
     * @date 2026-06-12
     * @author Stephane H.
     */
    public static function resolveLayoutDefaults(
        int $level,
        int $parentSpanDesktop,
        int $parentSpanTablet,
        int $parentSpanMobile,
        bool $hasSubTree,
        int $itemCount,
    ): array {
        $parentSpanDesktop = max(1, min(self::GRID_ROOT_BUDGET, $parentSpanDesktop));
        $parentSpanTablet = max(1, min(self::GRID_ROOT_BUDGET, $parentSpanTablet));
        $parentSpanMobile = max(1, min(self::GRID_ROOT_BUDGET, $parentSpanMobile));

        if ($level === 1) {
            $desktopSpan = ($hasSubTree || $itemCount > 12) ? self::GRID_ROOT_BUDGET : min(4, self::GRID_ROOT_BUDGET);

            return [
                'desktop' => ['span' => $desktopSpan],
                'tablet' => ['span' => self::GRID_ROOT_BUDGET],
                'mobile' => ['span' => self::GRID_ROOT_BUDGET],
            ];
        }

        return [
            'desktop' => ['span' => $parentSpanDesktop],
            'tablet' => ['span' => $parentSpanTablet],
            'mobile' => ['span' => $parentSpanMobile],
        ];
    }

    /**
     * @brief Normalize a layout block with desktop, tablet, and mobile spans clamped to parent budgets.
     *
     * @param mixed $raw Raw layout structure from storage or admin input.
     * @param int $maxSpanDesktop Maximum desktop span allowed for this node.
     * @param int $maxSpanTablet Maximum tablet span allowed for this node.
     * @param int $maxSpanMobile Maximum mobile span allowed for this node.
     * @return array{desktop: array{span: int}, tablet: array{span: int}, mobile: array{span: int}}|null Null when structure is invalid.
     * @date 2026-06-12
     * @author Stephane H.
     */
    public static function normalizeLayout(mixed $raw, int $maxSpanDesktop, int $maxSpanTablet, int $maxSpanMobile): ?array
    {
        if (!is_array($raw)) {
            return null;
        }

        $desktopRaw = $raw['desktop'] ?? null;
        $tabletRaw = $raw['tablet'] ?? null;
        $mobileRaw = $raw['mobile'] ?? null;
        if (!is_array($desktopRaw) || !is_array($mobileRaw)) {
            return null;
        }

        $desktopSpan = self::normalizeLayoutSpan($desktopRaw['span'] ?? null, $maxSpanDesktop);
        $mobileSpan = self::normalizeLayoutSpan($mobileRaw['span'] ?? null, $maxSpanMobile);
        if ($desktopSpan === null || $mobileSpan === null) {
            return null;
        }

        $tabletSpanSource = is_array($tabletRaw) ? ($tabletRaw['span'] ?? null) : ($mobileRaw['span'] ?? null);
        $tabletSpan = self::normalizeLayoutSpan($tabletSpanSource, $maxSpanTablet);
        if ($tabletSpan === null) {
            return null;
        }

        return [
            'desktop' => ['span' => $desktopSpan],
            'tablet' => ['span' => $tabletSpan],
            'mobile' => ['span' => $mobileSpan],
        ];
    }

    /**
     * @brief Root layout budget for level-1 categories (12 columns on all breakpoints).
     *
     * @return array{desktop: array{span: int}, tablet: array{span: int}, mobile: array{span: int}}
     * @date 2026-06-12
     * @author Stephane H.
     */
    public static function rootLayoutBudget(): array
    {
        return [
            'desktop' => ['span' => self::GRID_ROOT_BUDGET],
            'tablet' => ['span' => self::GRID_ROOT_BUDGET],
            'mobile' => ['span' => self::GRID_ROOT_BUDGET],
        ];
    }

    /**
     * @param array<string, mixed> $categoryRaw Raw category row.
     * @param list<string> $activeLocales Active locales.
     * @param string $defaultLocale Default locale.
     * @param int $sortOrder Fallback sort index.
     * @return array<string, mixed>|null
     */
    private static function normalizeCategory(
        array $categoryRaw,
        array $activeLocales,
        string $defaultLocale,
        int $sortOrder,
    ): ?array {
        $id = self::normalizeId($categoryRaw['id'] ?? null);
        if ($id === null) {
            return null;
        }

        $subcategoriesRaw = $categoryRaw['subcategories'] ?? [];
        if (!is_array($subcategoriesRaw) || count($subcategoriesRaw) > self::MAX_SUBCATEGORIES_PER_CATEGORY) {
            return null;
        }

        $itemsRaw = $categoryRaw['items'] ?? [];
        if (!is_array($itemsRaw) || count($itemsRaw) > self::MAX_ITEMS_PER_CONTAINER) {
            return null;
        }

        $items = self::normalizeItems($itemsRaw, $activeLocales, $defaultLocale);
        if ($items === null) {
            return null;
        }

        $labelFields = self::normalizeNodeLabelFields($categoryRaw, $activeLocales, $defaultLocale);
        if ($labelFields === null) {
            return null;
        }

        $layoutDefaults = self::resolveLayoutDefaults(
            1,
            self::GRID_ROOT_BUDGET,
            self::GRID_ROOT_BUDGET,
            self::GRID_ROOT_BUDGET,
            count($subcategoriesRaw) > 0,
            count($items),
        );
        $layout = self::normalizeNodeLayout(
            $categoryRaw,
            self::GRID_ROOT_BUDGET,
            self::GRID_ROOT_BUDGET,
            self::GRID_ROOT_BUDGET,
            $layoutDefaults,
        );
        if ($layout === null) {
            return null;
        }

        $subcategories = [];
        $subSort = 0;
        foreach ($subcategoriesRaw as $subRaw) {
            if (!is_array($subRaw)) {
                return null;
            }

            $sub = self::normalizeSubcategory($subRaw, $activeLocales, $defaultLocale, $subSort, $layout);
            if ($sub === null) {
                return null;
            }

            $subcategories[] = $sub;
            ++$subSort;
        }

        return [
            'id' => $id,
            'sortOrder' => self::normalizeSortOrder($categoryRaw['sortOrder'] ?? null, $sortOrder),
            'visibleOnPrimary' => self::normalizeBool($categoryRaw['visibleOnPrimary'] ?? true),
            ...$labelFields,
            'layout' => $layout,
            'items' => $items,
            'subcategories' => $subcategories,
        ];
    }

    /**
     * @param array<string, mixed> $subRaw Raw subcategory row.
     * @param list<string> $activeLocales Active locales.
     * @param string $defaultLocale Default locale.
     * @param int $sortOrder Fallback sort index.
     * @param array{desktop: array{span: int}, tablet: array{span: int}, mobile: array{span: int}} $parentLayout Parent category layout.
     * @return array<string, mixed>|null
     */
    private static function normalizeSubcategory(
        array $subRaw,
        array $activeLocales,
        string $defaultLocale,
        int $sortOrder,
        array $parentLayout,
    ): ?array {
        $id = self::normalizeId($subRaw['id'] ?? null);
        if ($id === null) {
            return null;
        }

        $groupsRaw = $subRaw['groups'] ?? [];
        if (!is_array($groupsRaw) || count($groupsRaw) > self::MAX_GROUPS_PER_SUBCATEGORY) {
            return null;
        }

        $itemsRaw = $subRaw['items'] ?? [];
        if (!is_array($itemsRaw) || count($itemsRaw) > self::MAX_ITEMS_PER_CONTAINER) {
            return null;
        }

        $items = self::normalizeItems($itemsRaw, $activeLocales, $defaultLocale);
        if ($items === null) {
            return null;
        }

        $labelFields = self::normalizeNodeLabelFields($subRaw, $activeLocales, $defaultLocale);
        if ($labelFields === null) {
            return null;
        }

        $maxDesktop = self::maxChildSpan($parentLayout, self::LAYOUT_BREAKPOINT_DESKTOP);
        $maxTablet = self::maxChildSpan($parentLayout, self::LAYOUT_BREAKPOINT_TABLET);
        $maxMobile = self::maxChildSpan($parentLayout, self::LAYOUT_BREAKPOINT_MOBILE);
        $layoutDefaults = self::resolveLayoutDefaults(
            2,
            $maxDesktop,
            $maxTablet,
            $maxMobile,
            count($groupsRaw) > 0,
            count($items),
        );
        $layout = self::normalizeNodeLayout($subRaw, $maxDesktop, $maxTablet, $maxMobile, $layoutDefaults);
        if ($layout === null) {
            return null;
        }

        $groups = [];
        $groupSort = 0;
        foreach ($groupsRaw as $groupRaw) {
            if (!is_array($groupRaw)) {
                return null;
            }

            $group = self::normalizeGroup($groupRaw, $activeLocales, $defaultLocale, $groupSort, $layout);
            if ($group === null) {
                return null;
            }

            $groups[] = $group;
            ++$groupSort;
        }

        return [
            'id' => $id,
            'sortOrder' => self::normalizeSortOrder($subRaw['sortOrder'] ?? null, $sortOrder),
            'visibleOnPrimary' => self::normalizeBool($subRaw['visibleOnPrimary'] ?? true),
            ...$labelFields,
            'layout' => $layout,
            'groups' => $groups,
            'items' => $items,
        ];
    }

    /**
     * @param array<string, mixed> $groupRaw Raw group row.
     * @param list<string> $activeLocales Active locales.
     * @param string $defaultLocale Default locale.
     * @param int $sortOrder Fallback sort index.
     * @param array{desktop: array{span: int}, tablet: array{span: int}, mobile: array{span: int}} $parentLayout Parent subcategory layout.
     * @return array<string, mixed>|null
     */
    private static function normalizeGroup(
        array $groupRaw,
        array $activeLocales,
        string $defaultLocale,
        int $sortOrder,
        array $parentLayout,
    ): ?array {
        $id = self::normalizeId($groupRaw['id'] ?? null);
        if ($id === null) {
            return null;
        }

        $itemsRaw = $groupRaw['items'] ?? [];
        if (!is_array($itemsRaw) || count($itemsRaw) > self::MAX_ITEMS_PER_CONTAINER) {
            return null;
        }

        $items = self::normalizeItems($itemsRaw, $activeLocales, $defaultLocale);
        if ($items === null) {
            return null;
        }

        $labelFields = self::normalizeNodeLabelFields($groupRaw, $activeLocales, $defaultLocale);
        if ($labelFields === null) {
            return null;
        }

        $maxDesktop = self::maxChildSpan($parentLayout, self::LAYOUT_BREAKPOINT_DESKTOP);
        $maxTablet = self::maxChildSpan($parentLayout, self::LAYOUT_BREAKPOINT_TABLET);
        $maxMobile = self::maxChildSpan($parentLayout, self::LAYOUT_BREAKPOINT_MOBILE);
        $layoutDefaults = self::resolveLayoutDefaults(3, $maxDesktop, $maxTablet, $maxMobile, false, count($items));
        $layout = self::normalizeNodeLayout($groupRaw, $maxDesktop, $maxTablet, $maxMobile, $layoutDefaults);
        if ($layout === null) {
            return null;
        }

        return [
            'id' => $id,
            'sortOrder' => self::normalizeSortOrder($groupRaw['sortOrder'] ?? null, $sortOrder),
            'visibleOnPrimary' => self::normalizeBool($groupRaw['visibleOnPrimary'] ?? true),
            ...$labelFields,
            'layout' => $layout,
            'items' => $items,
        ];
    }

    /**
     * @brief Resolve layout from raw node data or migration defaults when layout is absent.
     *
     * @param array<string, mixed> $nodeRaw Raw category, subcategory, or group row.
     * @param int $maxSpanDesktop Maximum desktop span for this node.
     * @param int $maxSpanTablet Maximum tablet span for this node.
     * @param int $maxSpanMobile Maximum mobile span for this node.
     * @param array{desktop: array{span: int}, tablet: array{span: int}, mobile: array{span: int}} $defaults Defaults when layout is missing.
     * @return array{desktop: array{span: int}, tablet: array{span: int}, mobile: array{span: int}}|null
     * @date 2026-06-12
     * @author Stephane H.
     */
    private static function normalizeNodeLayout(
        array $nodeRaw,
        int $maxSpanDesktop,
        int $maxSpanTablet,
        int $maxSpanMobile,
        array $defaults,
    ): ?array {
        if (!array_key_exists('layout', $nodeRaw)) {
            return $defaults;
        }

        return self::normalizeLayout($nodeRaw['layout'], $maxSpanDesktop, $maxSpanTablet, $maxSpanMobile);
    }

    /**
     * @param mixed $raw Raw span value.
     * @param int $maxSpan Maximum allowed span for the breakpoint.
     * @return int|null Clamped span or null when invalid.
     * @date 2026-06-11
     * @author Stephane H.
     */
    private static function normalizeLayoutSpan(mixed $raw, int $maxSpan): ?int
    {
        if (!is_int($raw) && !(is_string($raw) && ctype_digit($raw))) {
            return null;
        }

        $span = (int) $raw;
        $maxSpan = max(1, min(self::GRID_ROOT_BUDGET, $maxSpan));
        if ($span < 1 || $span > $maxSpan) {
            return null;
        }

        return $span;
    }

    /**
     * @param list<mixed> $itemsRaw Raw item rows.
     * @param list<string> $activeLocales Active locales.
     * @param string $defaultLocale Default locale.
     * @return list<array<string, mixed>>|null
     */
    private static function normalizeItems(array $itemsRaw, array $activeLocales, string $defaultLocale): ?array
    {
        $items = [];
        $sortOrder = 0;
        foreach ($itemsRaw as $itemRaw) {
            if (!is_array($itemRaw)) {
                return null;
            }

            $id = self::normalizeId($itemRaw['id'] ?? null);
            if ($id === null) {
                return null;
            }

            $iconFields = self::normalizeItemIconFields($itemRaw);
            if ($iconFields === null) {
                return null;
            }

            $labelFields = self::normalizeNodeLabelFields($itemRaw, $activeLocales, $defaultLocale);
            if ($labelFields === null) {
                return null;
            }

            $items[] = [
                'id' => $id,
                'sortOrder' => self::normalizeSortOrder($itemRaw['sortOrder'] ?? null, $sortOrder),
                'visibleOnPrimary' => self::normalizeBool($itemRaw['visibleOnPrimary'] ?? true),
                ...$iconFields,
                ...$labelFields,
            ];
            ++$sortOrder;
        }

        usort($items, static fn (array $a, array $b): int => ((int) ($a['sortOrder'] ?? 0)) <=> ((int) ($b['sortOrder'] ?? 0)));

        return $items;
    }

    /**
     * @param mixed $raw Labels map from storage.
     * @param list<string> $activeLocales Active locales.
     * @param string $defaultLocale Default locale.
     * @return array<string, string>
     */
    private static function normalizeLabelsByLocale(mixed $raw, array $activeLocales, string $defaultLocale): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $labels = [];
        foreach ($activeLocales as $locale) {
            $value = $raw[$locale] ?? '';
            if (!is_string($value)) {
                continue;
            }

            $trimmed = trim($value);
            if ($trimmed === '') {
                continue;
            }

            $labels[$locale] = mb_substr($trimmed, 0, self::MAX_LABEL_LENGTH);
        }

        if (!isset($labels[$defaultLocale]) && $labels !== []) {
            $labels[$defaultLocale] = reset($labels) ?: '';
        }

        return $labels;
    }

    /**
     * @param mixed $raw Raw id.
     * @return string|null
     */
    private static function normalizeId(mixed $raw): ?string
    {
        if (!is_string($raw)) {
            return null;
        }

        $trimmed = trim($raw);
        if ($trimmed === '' || strlen($trimmed) > 64) {
            return null;
        }

        if (!preg_match('/^[a-z0-9][a-z0-9-]{0,63}$/', $trimmed)) {
            return null;
        }

        return $trimmed;
    }

    /**
     * @param array<string, mixed> $raw Node or item raw row.
     * @param list<string> $activeLocales Active locales.
     * @param string $defaultLocale Default locale.
     * @return array{labelMode: string, canonicalLabel: string, labelsByLocale: array<string, string>}|null
     */
    private static function normalizeNodeLabelFields(array $raw, array $activeLocales, string $defaultLocale): ?array
    {
        $labelMode = self::normalizeLabelMode($raw['labelMode'] ?? null);
        $canonicalLabel = self::normalizeCanonicalLabel($raw['canonicalLabel'] ?? null);
        $labelsByLocale = self::normalizeLabelsByLocale($raw['labelsByLocale'] ?? null, $activeLocales, $defaultLocale);

        if ($labelMode === self::LABEL_MODE_CANONICAL) {
            if ($canonicalLabel === '') {
                return null;
            }

            return [
                'labelMode' => self::LABEL_MODE_CANONICAL,
                'canonicalLabel' => $canonicalLabel,
                'labelsByLocale' => $labelsByLocale,
            ];
        }

        if ($labelsByLocale === []) {
            return null;
        }

        return [
            'labelMode' => self::LABEL_MODE_LOCALIZED,
            'canonicalLabel' => $canonicalLabel,
            'labelsByLocale' => $labelsByLocale,
        ];
    }

    /**
     * @param array<string, mixed> $itemRaw Raw skill row.
     * @return array{iconType: string, icon: string, iconPath: string|null}|null
     */
    private static function normalizeItemIconFields(array $itemRaw): ?array
    {
        $iconType = is_string($itemRaw['iconType'] ?? null) ? (string) $itemRaw['iconType'] : '';
        if ($iconType === '') {
            $iconType = isset($itemRaw['iconPath']) && is_string($itemRaw['iconPath']) && trim($itemRaw['iconPath']) !== ''
                ? self::ICON_TYPE_IMAGE
                : self::ICON_TYPE_BOOTSTRAP;
        }

        if ($iconType === self::ICON_TYPE_IMAGE) {
            $iconPath = self::normalizeIconPath($itemRaw['iconPath'] ?? null);
            if ($iconPath === null) {
                return null;
            }

            return [
                'iconType' => self::ICON_TYPE_IMAGE,
                'icon' => 'bi-circle',
                'iconPath' => $iconPath,
            ];
        }

        $icon = self::normalizeIcon($itemRaw['icon'] ?? null);
        if ($icon === null) {
            return null;
        }

        return [
            'iconType' => self::ICON_TYPE_BOOTSTRAP,
            'icon' => $icon,
            'iconPath' => null,
        ];
    }

    /**
     * @param mixed $raw Raw label mode.
     * @return string
     */
    private static function normalizeLabelMode(mixed $raw): string
    {
        if (!is_string($raw)) {
            return self::LABEL_MODE_LOCALIZED;
        }

        $normalized = strtolower(trim($raw));

        return $normalized === self::LABEL_MODE_CANONICAL
            ? self::LABEL_MODE_CANONICAL
            : self::LABEL_MODE_LOCALIZED;
    }

    /**
     * @param mixed $raw Raw canonical label.
     * @return string
     */
    private static function normalizeCanonicalLabel(mixed $raw): string
    {
        if (!is_string($raw)) {
            return '';
        }

        $trimmed = trim($raw);
        if ($trimmed === '') {
            return '';
        }

        return mb_substr($trimmed, 0, self::MAX_LABEL_LENGTH);
    }

    /**
     * @brief Normalize a relative custom skill icon path for persistence and safe file cleanup.
     *
     * @param mixed $raw Relative icon path.
     * @return string|null Normalized path when valid, null otherwise.
     * @date 2026-06-09
     * @author Stephane H.
     */
    public static function normalizeIconPath(mixed $raw): ?string
    {
        if (!is_string($raw)) {
            return null;
        }

        $trimmed = str_replace('\\', '/', trim($raw));
        if ($trimmed === '' || str_contains($trimmed, '..')) {
            return null;
        }

        $prefix = self::SKILL_ICON_PATH_PREFIX;
        if (!str_starts_with($trimmed, $prefix)) {
            return null;
        }

        if (!preg_match('/^images\/cv\/skills\/custom\/skill-[a-z0-9-]+\.(webp|svg)$/i', $trimmed)) {
            return null;
        }

        return $trimmed;
    }

    /**
     * @param list<mixed> $itemsRaw Item list.
     * @param array<string, true> $paths Collected paths map.
     * @return void
     */
    private static function collectItemIconPaths(array $itemsRaw, array &$paths): void
    {
        foreach ($itemsRaw as $item) {
            if (!is_array($item)) {
                continue;
            }

            if (($item['iconType'] ?? '') !== self::ICON_TYPE_IMAGE) {
                continue;
            }

            $iconPath = self::normalizeIconPath($item['iconPath'] ?? null);
            if ($iconPath !== null) {
                $paths[$iconPath] = true;
            }
        }
    }

    /**
     * @param mixed $raw Raw icon class.
     * @return string|null
     */
    private static function normalizeIcon(mixed $raw): ?string
    {
        if (!is_string($raw)) {
            return null;
        }

        $trimmed = trim($raw);
        if ($trimmed === '' || strlen($trimmed) > self::MAX_ICON_LENGTH) {
            return null;
        }

        if (!preg_match(self::ICON_PATTERN, $trimmed)) {
            return null;
        }

        return $trimmed;
    }

    /**
     * @param mixed $raw Raw sort order.
     * @param int $fallback Fallback index.
     * @return int
     */
    private static function normalizeSortOrder(mixed $raw, int $fallback): int
    {
        if (is_int($raw)) {
            return max(0, min(999, $raw));
        }

        if (is_string($raw) && ctype_digit($raw)) {
            return max(0, min(999, (int) $raw));
        }

        return $fallback;
    }

    /**
     * @param mixed $raw Raw boolean.
     * @return bool
     */
    private static function normalizeBool(mixed $raw): bool
    {
        if (is_bool($raw)) {
            return $raw;
        }

        if (is_string($raw)) {
            $normalized = strtolower(trim($raw));

            return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
        }

        if (is_int($raw)) {
            return $raw === 1;
        }

        return (bool) $raw;
    }

    /**
     * @param array<string, mixed> $category Category node.
     * @param bool $ancestorsVisible Whether parent chain is visible on primary.
     * @return bool
     */
    private static function nodeHasSecondaryContent(array $category, bool $ancestorsVisible): bool
    {
        $selfVisible = $ancestorsVisible && ($category['visibleOnPrimary'] ?? false) === true;
        if (!$selfVisible) {
            return true;
        }

        foreach ($category['items'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }

            if (self::itemVisibleOnPrimary($item, $selfVisible) === false) {
                return true;
            }
        }

        foreach ($category['subcategories'] ?? [] as $subcategory) {
            if (!is_array($subcategory)) {
                continue;
            }

            if (self::subcategoryHasSecondaryContent($subcategory, $selfVisible)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $subcategory Subcategory node.
     * @param bool $ancestorsVisible Parent visibility chain.
     * @return bool
     */
    private static function subcategoryHasSecondaryContent(array $subcategory, bool $ancestorsVisible): bool
    {
        $selfVisible = $ancestorsVisible && ($subcategory['visibleOnPrimary'] ?? false) === true;
        if (!$selfVisible) {
            return true;
        }

        foreach ($subcategory['groups'] ?? [] as $group) {
            if (!is_array($group)) {
                continue;
            }

            if (self::groupHasSecondaryContent($group, $selfVisible)) {
                return true;
            }
        }

        foreach ($subcategory['items'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }

            if (self::itemVisibleOnPrimary($item, $selfVisible) === false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $group Group node.
     * @param bool $ancestorsVisible Parent visibility chain.
     * @return bool
     */
    private static function groupHasSecondaryContent(array $group, bool $ancestorsVisible): bool
    {
        $selfVisible = $ancestorsVisible && ($group['visibleOnPrimary'] ?? false) === true;
        if (!$selfVisible) {
            return true;
        }

        foreach ($group['items'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }

            if (self::itemVisibleOnPrimary($item, $selfVisible) === false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $item Skill item.
     * @param bool $ancestorsVisible Parent visibility chain.
     * @return bool
     */
    private static function itemVisibleOnPrimary(array $item, bool $ancestorsVisible): bool
    {
        return $ancestorsVisible && ($item['visibleOnPrimary'] ?? false) === true;
    }

    /**
     * @param array<string, mixed> $category Category node.
     * @param string $locale Viewer locale.
     * @param string $defaultLocale Default locale.
     * @param bool $ancestorsVisible Parent chain visibility.
     * @return array<string, mixed>|null
     */
    private static function filterCategoryForPrimary(
        array $category,
        string $locale,
        string $defaultLocale,
        bool $ancestorsVisible,
    ): ?array {
        $selfVisible = $ancestorsVisible && ($category['visibleOnPrimary'] ?? false) === true;
        if (!$selfVisible) {
            return null;
        }

        $items = self::filterItemsForPrimary($category['items'] ?? [], $selfVisible, $locale, $defaultLocale);

        $subcategories = [];
        foreach ($category['subcategories'] ?? [] as $subcategory) {
            if (!is_array($subcategory)) {
                continue;
            }

            $filtered = self::filterSubcategoryForPrimary($subcategory, $locale, $defaultLocale, $selfVisible);
            if ($filtered !== null) {
                $subcategories[] = $filtered;
            }
        }

        if ($subcategories === [] && $items === []) {
            return null;
        }

        $category['items'] = $items;
        $category['subcategories'] = $subcategories;
        $category['label'] = self::labelForLocale($category, $locale, $defaultLocale);

        return $category;
    }

    /**
     * @param array<string, mixed> $subcategory Subcategory node.
     * @param string $locale Viewer locale.
     * @param string $defaultLocale Default locale.
     * @param bool $ancestorsVisible Parent chain visibility.
     * @return array<string, mixed>|null
     */
    private static function filterSubcategoryForPrimary(
        array $subcategory,
        string $locale,
        string $defaultLocale,
        bool $ancestorsVisible,
    ): ?array {
        $selfVisible = $ancestorsVisible && ($subcategory['visibleOnPrimary'] ?? false) === true;
        if (!$selfVisible) {
            return null;
        }

        $groups = [];
        foreach ($subcategory['groups'] ?? [] as $group) {
            if (!is_array($group)) {
                continue;
            }

            $filtered = self::filterGroupForPrimary($group, $locale, $defaultLocale, $selfVisible);
            if ($filtered !== null) {
                $groups[] = $filtered;
            }
        }

        $items = self::filterItemsForPrimary($subcategory['items'] ?? [], $selfVisible, $locale, $defaultLocale);

        if ($groups === [] && $items === []) {
            return null;
        }

        $subcategory['groups'] = $groups;
        $subcategory['items'] = $items;
        $subcategory['label'] = self::labelForLocale($subcategory, $locale, $defaultLocale);

        return $subcategory;
    }

    /**
     * @param array<string, mixed> $group Group node.
     * @param string $locale Viewer locale.
     * @param string $defaultLocale Default locale.
     * @param bool $ancestorsVisible Parent chain visibility.
     * @return array<string, mixed>|null
     */
    private static function filterGroupForPrimary(
        array $group,
        string $locale,
        string $defaultLocale,
        bool $ancestorsVisible,
    ): ?array {
        $selfVisible = $ancestorsVisible && ($group['visibleOnPrimary'] ?? false) === true;
        if (!$selfVisible) {
            return null;
        }

        $items = self::filterItemsForPrimary($group['items'] ?? [], $selfVisible, $locale, $defaultLocale);
        if ($items === []) {
            return null;
        }

        $group['items'] = $items;
        $group['label'] = self::labelForLocale($group, $locale, $defaultLocale);

        return $group;
    }

    /**
     * @param list<mixed> $itemsRaw Item list.
     * @param bool $ancestorsVisible Parent chain visibility.
     * @param string $locale Viewer locale.
     * @param string $defaultLocale Default locale.
     * @return list<array<string, mixed>>
     */
    private static function filterItemsForPrimary(
        array $itemsRaw,
        bool $ancestorsVisible,
        string $locale,
        string $defaultLocale,
    ): array {
        $items = [];
        foreach ($itemsRaw as $item) {
            if (!is_array($item)) {
                continue;
            }

            if (!self::itemVisibleOnPrimary($item, $ancestorsVisible)) {
                continue;
            }

            $item['label'] = self::labelForLocale($item, $locale, $defaultLocale);
            if ($item['label'] === '') {
                continue;
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $category Category node.
     * @param string $locale Viewer locale.
     * @param string $defaultLocale Default locale.
     * @param bool $ancestorsVisible Parent chain visibility on primary.
     * @return array<string, mixed>|null
     */
    private static function filterCategoryForSecondary(
        array $category,
        string $locale,
        string $defaultLocale,
        bool $ancestorsVisible,
    ): ?array {
        $selfVisibleOnPrimary = $ancestorsVisible && ($category['visibleOnPrimary'] ?? false) === true;

        $subcategories = [];
        foreach ($category['subcategories'] ?? [] as $subcategory) {
            if (!is_array($subcategory)) {
                continue;
            }

            $filtered = self::filterSubcategoryForSecondary(
                $subcategory,
                $locale,
                $defaultLocale,
                $selfVisibleOnPrimary
            );
            if ($filtered !== null) {
                $subcategories[] = $filtered;
            }
        }

        if (!$selfVisibleOnPrimary) {
            return self::attachLabelsToCategory($category, $locale, $defaultLocale);
        }

        $items = self::filterItemsForSecondary(
            $category['items'] ?? [],
            $selfVisibleOnPrimary,
            $locale,
            $defaultLocale
        );

        if ($subcategories === [] && $items === []) {
            return null;
        }

        $category['items'] = $items;
        $category['subcategories'] = $subcategories;
        $category['label'] = self::labelForLocale($category, $locale, $defaultLocale);

        return $category;
    }

    /**
     * @param array<string, mixed> $subcategory Subcategory node.
     * @param string $locale Viewer locale.
     * @param string $defaultLocale Default locale.
     * @param bool $ancestorsVisibleOnPrimary Parent primary visibility chain.
     * @return array<string, mixed>|null
     */
    private static function filterSubcategoryForSecondary(
        array $subcategory,
        string $locale,
        string $defaultLocale,
        bool $ancestorsVisibleOnPrimary,
    ): ?array {
        $selfVisibleOnPrimary = $ancestorsVisibleOnPrimary && ($subcategory['visibleOnPrimary'] ?? false) === true;

        $groups = [];
        foreach ($subcategory['groups'] ?? [] as $group) {
            if (!is_array($group)) {
                continue;
            }

            $filtered = self::filterGroupForSecondary($group, $locale, $defaultLocale, $selfVisibleOnPrimary);
            if ($filtered !== null) {
                $groups[] = $filtered;
            }
        }

        $items = self::filterItemsForSecondary(
            $subcategory['items'] ?? [],
            $selfVisibleOnPrimary,
            $locale,
            $defaultLocale
        );

        if (!$selfVisibleOnPrimary) {
            return self::attachLabelsToSubcategory($subcategory, $locale, $defaultLocale);
        }

        if ($groups === [] && $items === []) {
            return null;
        }

        $subcategory['groups'] = $groups;
        $subcategory['items'] = $items;
        $subcategory['label'] = self::labelForLocale($subcategory, $locale, $defaultLocale);

        return $subcategory;
    }

    /**
     * @param array<string, mixed> $group Group node.
     * @param string $locale Viewer locale.
     * @param string $defaultLocale Default locale.
     * @param bool $ancestorsVisibleOnPrimary Parent primary visibility chain.
     * @return array<string, mixed>|null
     */
    private static function filterGroupForSecondary(
        array $group,
        string $locale,
        string $defaultLocale,
        bool $ancestorsVisibleOnPrimary,
    ): ?array {
        $selfVisibleOnPrimary = $ancestorsVisibleOnPrimary && ($group['visibleOnPrimary'] ?? false) === true;

        if (!$selfVisibleOnPrimary) {
            return self::attachLabelsToGroup($group, $locale, $defaultLocale);
        }

        $items = self::filterItemsForSecondary($group['items'] ?? [], $selfVisibleOnPrimary, $locale, $defaultLocale);
        if ($items === []) {
            return null;
        }

        $group['items'] = $items;
        $group['label'] = self::labelForLocale($group, $locale, $defaultLocale);

        return $group;
    }

    /**
     * @param list<mixed> $itemsRaw Item list.
     * @param bool $ancestorsVisibleOnPrimary Parent primary visibility chain.
     * @param string $locale Viewer locale.
     * @param string $defaultLocale Default locale.
     * @return list<array<string, mixed>>
     */
    private static function filterItemsForSecondary(
        array $itemsRaw,
        bool $ancestorsVisibleOnPrimary,
        string $locale,
        string $defaultLocale,
    ): array {
        $items = [];
        foreach ($itemsRaw as $item) {
            if (!is_array($item)) {
                continue;
            }

            if (self::itemVisibleOnPrimary($item, $ancestorsVisibleOnPrimary)) {
                continue;
            }

            $item['label'] = self::labelForLocale($item, $locale, $defaultLocale);
            if ($item['label'] === '') {
                continue;
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $category Category node.
     * @param string $locale Viewer locale.
     * @param string $defaultLocale Default locale.
     * @param bool $ancestorsVisibleOnPrimary Parent primary visibility chain.
     * @return array<string, mixed>|null
     * @date 2026-05-31
     * @author Stephane H.
     */
    private static function filterCategoryForFull(
        array $category,
        string $locale,
        string $defaultLocale,
        bool $ancestorsVisibleOnPrimary,
    ): ?array {
        $selfVisibleOnPrimary = $ancestorsVisibleOnPrimary && ($category['visibleOnPrimary'] ?? false) === true;

        $items = self::filterItemsForFull(
            $category['items'] ?? [],
            $selfVisibleOnPrimary,
            $locale,
            $defaultLocale
        );

        $subcategories = [];
        foreach ($category['subcategories'] ?? [] as $subcategory) {
            if (!is_array($subcategory)) {
                continue;
            }

            $filtered = self::filterSubcategoryForFull(
                $subcategory,
                $locale,
                $defaultLocale,
                $selfVisibleOnPrimary
            );
            if ($filtered !== null) {
                $subcategories[] = $filtered;
            }
        }

        if ($subcategories === [] && $items === []) {
            return null;
        }

        $category['items'] = $items;
        $category['subcategories'] = $subcategories;
        $category['label'] = self::labelForLocale($category, $locale, $defaultLocale);
        $category['hiddenOnPrimary'] = !$selfVisibleOnPrimary;

        return $category;
    }

    /**
     * @param array<string, mixed> $subcategory Subcategory node.
     * @param string $locale Viewer locale.
     * @param string $defaultLocale Default locale.
     * @param bool $ancestorsVisibleOnPrimary Parent primary visibility chain.
     * @return array<string, mixed>|null
     * @date 2026-05-31
     * @author Stephane H.
     */
    private static function filterSubcategoryForFull(
        array $subcategory,
        string $locale,
        string $defaultLocale,
        bool $ancestorsVisibleOnPrimary,
    ): ?array {
        $selfVisibleOnPrimary = $ancestorsVisibleOnPrimary && ($subcategory['visibleOnPrimary'] ?? false) === true;

        $groups = [];
        foreach ($subcategory['groups'] ?? [] as $group) {
            if (!is_array($group)) {
                continue;
            }

            $filtered = self::filterGroupForFull($group, $locale, $defaultLocale, $selfVisibleOnPrimary);
            if ($filtered !== null) {
                $groups[] = $filtered;
            }
        }

        $items = self::filterItemsForFull(
            $subcategory['items'] ?? [],
            $selfVisibleOnPrimary,
            $locale,
            $defaultLocale
        );

        if ($groups === [] && $items === []) {
            return null;
        }

        $subcategory['groups'] = $groups;
        $subcategory['items'] = $items;
        $subcategory['label'] = self::labelForLocale($subcategory, $locale, $defaultLocale);
        $subcategory['hiddenOnPrimary'] = !$selfVisibleOnPrimary;

        return $subcategory;
    }

    /**
     * @param array<string, mixed> $group Group node.
     * @param string $locale Viewer locale.
     * @param string $defaultLocale Default locale.
     * @param bool $ancestorsVisibleOnPrimary Parent primary visibility chain.
     * @return array<string, mixed>|null
     * @date 2026-05-31
     * @author Stephane H.
     */
    private static function filterGroupForFull(
        array $group,
        string $locale,
        string $defaultLocale,
        bool $ancestorsVisibleOnPrimary,
    ): ?array {
        $selfVisibleOnPrimary = $ancestorsVisibleOnPrimary && ($group['visibleOnPrimary'] ?? false) === true;

        $items = self::filterItemsForFull($group['items'] ?? [], $selfVisibleOnPrimary, $locale, $defaultLocale);
        if ($items === []) {
            return null;
        }

        $group['items'] = $items;
        $group['label'] = self::labelForLocale($group, $locale, $defaultLocale);
        $group['hiddenOnPrimary'] = !$selfVisibleOnPrimary;

        return $group;
    }

    /**
     * @param list<mixed> $itemsRaw Item list.
     * @param bool $ancestorsVisibleOnPrimary Parent primary visibility chain.
     * @param string $locale Viewer locale.
     * @param string $defaultLocale Default locale.
     * @return list<array<string, mixed>>
     * @date 2026-05-31
     * @author Stephane H.
     */
    private static function filterItemsForFull(
        array $itemsRaw,
        bool $ancestorsVisibleOnPrimary,
        string $locale,
        string $defaultLocale,
    ): array {
        $items = [];
        foreach ($itemsRaw as $item) {
            if (!is_array($item)) {
                continue;
            }

            $item['label'] = self::labelForLocale($item, $locale, $defaultLocale);
            if ($item['label'] === '') {
                continue;
            }

            $item['hiddenOnPrimary'] = !self::itemVisibleOnPrimary($item, $ancestorsVisibleOnPrimary);
            $items[] = $item;
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $category Category node.
     * @param string $locale Viewer locale.
     * @param string $defaultLocale Default locale.
     * @return array<string, mixed>
     */
    private static function attachLabelsToCategory(array $category, string $locale, string $defaultLocale): array
    {
        $category['label'] = self::labelForLocale($category, $locale, $defaultLocale);
        $subcategories = [];
        foreach ($category['subcategories'] ?? [] as $subcategory) {
            if (!is_array($subcategory)) {
                continue;
            }

            $subcategories[] = self::attachLabelsToSubcategory($subcategory, $locale, $defaultLocale);
        }

        $category['items'] = self::attachLabelsToItems(
            is_array($category['items'] ?? null) ? $category['items'] : [],
            $locale,
            $defaultLocale
        );
        $category['subcategories'] = $subcategories;

        return $category;
    }

    /**
     * @param array<string, mixed> $subcategory Subcategory node.
     * @param string $locale Viewer locale.
     * @param string $defaultLocale Default locale.
     * @return array<string, mixed>
     */
    private static function attachLabelsToSubcategory(array $subcategory, string $locale, string $defaultLocale): array
    {
        $subcategory['label'] = self::labelForLocale($subcategory, $locale, $defaultLocale);
        $groups = [];
        foreach ($subcategory['groups'] ?? [] as $group) {
            if (!is_array($group)) {
                continue;
            }

            $groups[] = self::attachLabelsToGroup($group, $locale, $defaultLocale);
        }

        $subcategory['groups'] = $groups;
        $subcategory['items'] = self::attachLabelsToItems(
            is_array($subcategory['items'] ?? null) ? $subcategory['items'] : [],
            $locale,
            $defaultLocale
        );

        return $subcategory;
    }

    /**
     * @param array<string, mixed> $group Group node.
     * @param string $locale Viewer locale.
     * @param string $defaultLocale Default locale.
     * @return array<string, mixed>
     */
    private static function attachLabelsToGroup(array $group, string $locale, string $defaultLocale): array
    {
        $group['label'] = self::labelForLocale($group, $locale, $defaultLocale);
        $group['items'] = self::attachLabelsToItems(
            is_array($group['items'] ?? null) ? $group['items'] : [],
            $locale,
            $defaultLocale
        );

        return $group;
    }

    /**
     * @param list<mixed> $itemsRaw Item list.
     * @param string $locale Viewer locale.
     * @param string $defaultLocale Default locale.
     * @return list<array<string, mixed>>
     */
    private static function attachLabelsToItems(array $itemsRaw, string $locale, string $defaultLocale): array
    {
        $items = [];
        foreach ($itemsRaw as $item) {
            if (!is_array($item)) {
                continue;
            }

            $item['label'] = self::labelForLocale($item, $locale, $defaultLocale);
            if ($item['label'] === '') {
                continue;
            }

            $items[] = $item;
        }

        return $items;
    }
}
