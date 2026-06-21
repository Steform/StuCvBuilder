<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cv;

use App\Cv\SkillsTreeContract;
use PHPUnit\Framework\TestCase;

/**
 * @brief Unit tests for {@see SkillsTreeContract} primary/secondary filtering and visibility.
 *
 * @date 2026-05-29
 * @author Stephane H.
 */
final class SkillsTreeContractTest extends TestCase
{
    private const ACTIVE_LOCALES = ['fr', 'en'];

    private const DEFAULT_LOCALE = 'fr';

    /**
     * @param array{categories: list<array<string, mixed>>} $catalog Raw catalog.
     * @return array{categories: list<array<string, mixed>>}
     * @date 2026-05-29
     * @author Stephane H.
     */
    private function normalizeSampleCatalog(array $catalog): array
    {
        $normalized = SkillsTreeContract::normalizeCatalog($catalog, self::ACTIVE_LOCALES, self::DEFAULT_LOCALE);
        self::assertIsArray($normalized);

        return $normalized;
    }

    /**
     * @brief Build a minimal two-item catalog for visibility tests.
     *
     * @param bool $categoryVisible Level-1 visibility on primary.
     * @param bool $subcategoryVisible Level-2 visibility on primary.
     * @param bool $secondItemVisible Level-3 item visibility on primary.
     * @return array{categories: list<array<string, mixed>>}
     * @date 2026-05-29
     * @author Stephane H.
     */
    private function buildSampleCatalog(
        bool $categoryVisible = true,
        bool $subcategoryVisible = true,
        bool $secondItemVisible = false,
    ): array {
        return [
            'categories' => [
                [
                    'id' => 'cat-it',
                    'sortOrder' => 0,
                    'visibleOnPrimary' => $categoryVisible,
                    'labelMode' => SkillsTreeContract::LABEL_MODE_LOCALIZED,
                    'canonicalLabel' => '',
                    'labelsByLocale' => ['fr' => 'IT', 'en' => 'IT'],
                    'items' => [],
                    'subcategories' => [
                        [
                            'id' => 'sub-web',
                            'sortOrder' => 0,
                            'visibleOnPrimary' => $subcategoryVisible,
                            'labelMode' => SkillsTreeContract::LABEL_MODE_LOCALIZED,
                            'canonicalLabel' => '',
                            'labelsByLocale' => ['fr' => 'Web', 'en' => 'Web'],
                            'groups' => [],
                            'items' => [
                                [
                                    'id' => 'item-a',
                                    'sortOrder' => 0,
                                    'visibleOnPrimary' => true,
                                    'iconType' => SkillsTreeContract::ICON_TYPE_BOOTSTRAP,
                                    'icon' => 'bi-code-slash',
                                    'iconPath' => null,
                                    'labelMode' => SkillsTreeContract::LABEL_MODE_LOCALIZED,
                                    'canonicalLabel' => '',
                                    'labelsByLocale' => ['fr' => 'Alpha', 'en' => 'Alpha'],
                                ],
                                [
                                    'id' => 'item-b',
                                    'sortOrder' => 1,
                                    'visibleOnPrimary' => $secondItemVisible,
                                    'iconType' => SkillsTreeContract::ICON_TYPE_BOOTSTRAP,
                                    'icon' => 'bi-git',
                                    'iconPath' => null,
                                    'labelMode' => SkillsTreeContract::LABEL_MODE_LOCALIZED,
                                    'canonicalLabel' => '',
                                    'labelsByLocale' => ['fr' => 'Beta', 'en' => 'Beta'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @brief Fully visible catalog must not require the secondary page link.
     *
     * @return void
     * @date 2026-05-29
     * @author Stephane H.
     */
    public function testHasSecondaryVisibleIsFalseWhenEverythingOnPrimary(): void
    {
        $catalog = $this->normalizeSampleCatalog($this->buildSampleCatalog(true, true, true));

        self::assertFalse(SkillsTreeContract::hasSecondaryVisible($catalog));
    }

    /**
     * @brief Hidden skill item must flag secondary content.
     *
     * @return void
     * @date 2026-05-29
     * @author Stephane H.
     */
    public function testHasSecondaryVisibleIsTrueWhenItemHiddenOnPrimary(): void
    {
        $catalog = $this->normalizeSampleCatalog($this->buildSampleCatalog());

        self::assertTrue(SkillsTreeContract::hasSecondaryVisible($catalog));
    }

    /**
     * @brief Primary filter keeps only visible items when ancestors are visible.
     *
     * @return void
     * @date 2026-05-29
     * @author Stephane H.
     */
    public function testFilterForPrimaryExcludesHiddenItems(): void
    {
        $catalog = $this->normalizeSampleCatalog($this->buildSampleCatalog());
        $primary = SkillsTreeContract::filterForPrimary($catalog, 'fr', self::DEFAULT_LOCALE);

        $items = $primary['categories'][0]['subcategories'][0]['items'];
        self::assertCount(1, $items);
        self::assertSame('item-a', $items[0]['id']);
        self::assertSame('Alpha', $items[0]['label']);
    }

    /**
     * @brief Secondary filter exposes only skills hidden on the primary view.
     *
     * @return void
     * @date 2026-05-29
     * @author Stephane H.
     */
    public function testFilterForSecondaryIncludesOnlyHiddenItems(): void
    {
        $catalog = $this->normalizeSampleCatalog($this->buildSampleCatalog());
        $secondary = SkillsTreeContract::filterForSecondary($catalog, 'fr', self::DEFAULT_LOCALE);

        $items = $secondary['categories'][0]['subcategories'][0]['items'];
        self::assertCount(1, $items);
        self::assertSame('item-b', $items[0]['id']);
        self::assertSame('Beta', $items[0]['label']);
    }

    /**
     * @brief Hidden subcategory must surface the full branch on the secondary page.
     *
     * @return void
     * @date 2026-05-29
     * @author Stephane H.
     */
    public function testFilterForSecondaryIncludesFullBranchWhenSubcategoryHidden(): void
    {
        $catalog = $this->normalizeSampleCatalog($this->buildSampleCatalog(true, false, true));
        $secondary = SkillsTreeContract::filterForSecondary($catalog, 'fr', self::DEFAULT_LOCALE);

        $sub = $secondary['categories'][0]['subcategories'][0];
        self::assertSame('sub-web', $sub['id']);
        self::assertCount(2, $sub['items']);
    }

    /**
     * @brief Full filter keeps every skill and flags items hidden on the primary view.
     *
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function testFilterForFullIncludesAllItemsAndFlagsHiddenOnPrimary(): void
    {
        $catalog = $this->normalizeSampleCatalog($this->buildSampleCatalog());
        $full = SkillsTreeContract::filterForFull($catalog, 'fr', self::DEFAULT_LOCALE);

        $items = $full['categories'][0]['subcategories'][0]['items'];
        self::assertCount(2, $items);
        self::assertSame('item-a', $items[0]['id']);
        self::assertFalse($items[0]['hiddenOnPrimary']);
        self::assertSame('item-b', $items[1]['id']);
        self::assertTrue($items[1]['hiddenOnPrimary']);
    }

    /**
     * @brief Full filter must include branches hidden on the primary view with all skills flagged.
     *
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function testFilterForFullIncludesHiddenBranchWithAllItemsFlagged(): void
    {
        $catalog = $this->normalizeSampleCatalog($this->buildSampleCatalog(true, false, true));
        $full = SkillsTreeContract::filterForFull($catalog, 'fr', self::DEFAULT_LOCALE);

        $sub = $full['categories'][0]['subcategories'][0];
        self::assertSame('sub-web', $sub['id']);
        self::assertTrue($sub['hiddenOnPrimary']);
        self::assertCount(2, $sub['items']);
        self::assertTrue($sub['items'][0]['hiddenOnPrimary']);
        self::assertTrue($sub['items'][1]['hiddenOnPrimary']);
    }

    /**
     * @brief Full filter must keep level-1 category skills alongside hidden ones.
     *
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function testFilterForFullIncludesLevelOneItems(): void
    {
        $catalog = $this->normalizeSampleCatalog([
            'categories' => [
                [
                    'id' => 'cat-steak',
                    'sortOrder' => 0,
                    'visibleOnPrimary' => true,
                    'labelMode' => SkillsTreeContract::LABEL_MODE_CANONICAL,
                    'canonicalLabel' => 'Steak',
                    'labelsByLocale' => ['fr' => 'Steak'],
                    'items' => [
                        [
                            'id' => 'item-cuisson',
                            'sortOrder' => 0,
                            'visibleOnPrimary' => true,
                            'iconType' => SkillsTreeContract::ICON_TYPE_BOOTSTRAP,
                            'icon' => 'bi-fire',
                            'iconPath' => null,
                            'labelMode' => SkillsTreeContract::LABEL_MODE_CANONICAL,
                            'canonicalLabel' => 'Cuisson',
                            'labelsByLocale' => ['fr' => 'Cuisson'],
                        ],
                    ],
                    'subcategories' => [],
                ],
            ],
        ]);

        $full = SkillsTreeContract::filterForFull($catalog, 'fr', self::DEFAULT_LOCALE);

        self::assertCount(1, $full['categories']);
        self::assertCount(1, $full['categories'][0]['items']);
        self::assertSame('Cuisson', $full['categories'][0]['items'][0]['label']);
        self::assertFalse($full['categories'][0]['items'][0]['hiddenOnPrimary']);
    }

    /**
     * @brief labelForLocale falls back to default locale then any available label.
     *
     * @return void
     * @date 2026-05-29
     * @author Stephane H.
     */
    public function testLabelForLocaleUsesFallbackChain(): void
    {
        $node = ['labelsByLocale' => ['en' => 'English only']];

        self::assertSame('English only', SkillsTreeContract::labelForLocale($node, 'de', 'fr'));
        self::assertSame('English only', SkillsTreeContract::labelForLocale($node, 'en', 'fr'));
    }

    /**
     * @brief Skills attached directly to a level-1 category appear on the primary CV tree.
     *
     * @return void
     * @date 2026-05-29
     * @author Stephane H.
     */
    public function testFilterForPrimaryIncludesLevelOneItems(): void
    {
        $catalog = $this->normalizeSampleCatalog([
            'categories' => [
                [
                    'id' => 'cat-steak',
                    'sortOrder' => 0,
                    'visibleOnPrimary' => true,
                    'labelMode' => SkillsTreeContract::LABEL_MODE_CANONICAL,
                    'canonicalLabel' => 'Steak',
                    'labelsByLocale' => ['fr' => 'Steak'],
                    'items' => [
                        [
                            'id' => 'item-cuisson',
                            'sortOrder' => 0,
                            'visibleOnPrimary' => true,
                            'iconType' => SkillsTreeContract::ICON_TYPE_BOOTSTRAP,
                            'icon' => 'bi-fire',
                            'iconPath' => null,
                            'labelMode' => SkillsTreeContract::LABEL_MODE_CANONICAL,
                            'canonicalLabel' => 'Cuisson',
                            'labelsByLocale' => ['fr' => 'Cuisson'],
                        ],
                    ],
                    'subcategories' => [],
                ],
            ],
        ]);

        $primary = SkillsTreeContract::filterForPrimary($catalog, 'fr', self::DEFAULT_LOCALE);

        self::assertCount(1, $primary['categories']);
        self::assertCount(1, $primary['categories'][0]['items']);
        self::assertSame('Cuisson', $primary['categories'][0]['items'][0]['label']);
    }

    /**
     * @brief Level-1 category deletion is blocked when direct skills exist.
     *
     * @return void
     * @date 2026-05-29
     * @author Stephane H.
     */
    public function testCanDeleteCategoryIsFalseWhenLevelOneHasItems(): void
    {
        $catalog = $this->normalizeSampleCatalog([
            'categories' => [
                [
                    'id' => 'cat-steak',
                    'sortOrder' => 0,
                    'visibleOnPrimary' => true,
                    'labelMode' => SkillsTreeContract::LABEL_MODE_CANONICAL,
                    'canonicalLabel' => 'Steak',
                    'labelsByLocale' => ['fr' => 'Steak'],
                    'items' => [
                        [
                            'id' => 'item-cuisson',
                            'sortOrder' => 0,
                            'visibleOnPrimary' => true,
                            'iconType' => SkillsTreeContract::ICON_TYPE_BOOTSTRAP,
                            'icon' => 'bi-fire',
                            'iconPath' => null,
                            'labelMode' => SkillsTreeContract::LABEL_MODE_CANONICAL,
                            'canonicalLabel' => 'Cuisson',
                            'labelsByLocale' => ['fr' => 'Cuisson'],
                        ],
                    ],
                    'subcategories' => [],
                ],
            ],
        ]);

        self::assertFalse(SkillsTreeContract::canDeleteCategory($catalog['categories'][0]));
    }

    /**
     * @brief Canonical label mode returns the canonical string regardless of locale.
     *
     * @return void
     * @date 2026-05-29
     * @author Stephane H.
     */
    public function testLabelForLocaleUsesCanonicalMode(): void
    {
        $node = [
            'labelMode' => SkillsTreeContract::LABEL_MODE_CANONICAL,
            'canonicalLabel' => 'JavaScript',
            'labelsByLocale' => ['fr' => 'JS'],
        ];

        self::assertSame('JavaScript', SkillsTreeContract::labelForLocale($node, 'fr', 'fr'));
    }

    /**
     * @brief Invalid Bootstrap icon classes must be rejected during normalization.
     *
     * @return void
     * @date 2026-05-29
     * @author Stephane H.
     */
    public function testNormalizeCatalogRejectsInvalidIcon(): void
    {
        $catalog = $this->buildSampleCatalog();
        $catalog['categories'][0]['subcategories'][0]['items'][0]['icon'] = 'fa-invalid';

        self::assertNull(SkillsTreeContract::normalizeCatalog($catalog, self::ACTIVE_LOCALES, self::DEFAULT_LOCALE));
    }

    /**
     * @brief Valid layout spans are normalized on all three category levels.
     *
     * @return void
     * @date 2026-06-11
     * @author Stephane H.
     */
    public function testNormalizeCatalogAcceptsExplicitLayoutOnThreeLevels(): void
    {
        $catalog = $this->normalizeSampleCatalog([
            'categories' => [
                [
                    'id' => 'cat-root',
                    'sortOrder' => 0,
                    'visibleOnPrimary' => true,
                    'labelMode' => SkillsTreeContract::LABEL_MODE_CANONICAL,
                    'canonicalLabel' => 'Root',
                    'labelsByLocale' => ['fr' => 'Root'],
                    'layout' => [
                        'desktop' => ['span' => 6],
                        'mobile' => ['span' => 12],
                    ],
                    'items' => [],
                    'subcategories' => [
                        [
                            'id' => 'sub-a',
                            'sortOrder' => 0,
                            'visibleOnPrimary' => true,
                            'labelMode' => SkillsTreeContract::LABEL_MODE_CANONICAL,
                            'canonicalLabel' => 'Sub',
                            'labelsByLocale' => ['fr' => 'Sub'],
                            'layout' => [
                                'desktop' => ['span' => 4],
                                'mobile' => ['span' => 6],
                            ],
                            'groups' => [
                                [
                                    'id' => 'grp-a',
                                    'sortOrder' => 0,
                                    'visibleOnPrimary' => true,
                                    'labelMode' => SkillsTreeContract::LABEL_MODE_CANONICAL,
                                    'canonicalLabel' => 'Group',
                                    'labelsByLocale' => ['fr' => 'Group'],
                                    'layout' => [
                                        'desktop' => ['span' => 2],
                                        'mobile' => ['span' => 3],
                                    ],
                                    'items' => [
                                        [
                                            'id' => 'item-a',
                                            'sortOrder' => 0,
                                            'visibleOnPrimary' => true,
                                            'iconType' => SkillsTreeContract::ICON_TYPE_BOOTSTRAP,
                                            'icon' => 'bi-code-slash',
                                            'iconPath' => null,
                                            'labelMode' => SkillsTreeContract::LABEL_MODE_CANONICAL,
                                            'canonicalLabel' => 'Skill',
                                            'labelsByLocale' => ['fr' => 'Skill'],
                                        ],
                                    ],
                                ],
                            ],
                            'items' => [],
                        ],
                    ],
                ],
            ],
        ]);

        self::assertSame(6, $catalog['categories'][0]['layout']['desktop']['span']);
        self::assertSame(12, $catalog['categories'][0]['layout']['tablet']['span']);
        self::assertSame(12, $catalog['categories'][0]['layout']['mobile']['span']);
        self::assertSame(4, $catalog['categories'][0]['subcategories'][0]['layout']['desktop']['span']);
        self::assertSame(2, $catalog['categories'][0]['subcategories'][0]['groups'][0]['layout']['desktop']['span']);
    }

    /**
     * @brief Child span above parent budget must invalidate catalog normalization.
     *
     * @return void
     * @date 2026-06-11
     * @author Stephane H.
     */
    public function testNormalizeCatalogRejectsChildSpanAboveParentBudget(): void
    {
        $catalog = [
            'categories' => [
                [
                    'id' => 'cat-root',
                    'sortOrder' => 0,
                    'visibleOnPrimary' => true,
                    'labelMode' => SkillsTreeContract::LABEL_MODE_CANONICAL,
                    'canonicalLabel' => 'Root',
                    'labelsByLocale' => ['fr' => 'Root'],
                    'layout' => [
                        'desktop' => ['span' => 6],
                        'mobile' => ['span' => 12],
                    ],
                    'items' => [],
                    'subcategories' => [
                        [
                            'id' => 'sub-a',
                            'sortOrder' => 0,
                            'visibleOnPrimary' => true,
                            'labelMode' => SkillsTreeContract::LABEL_MODE_CANONICAL,
                            'canonicalLabel' => 'Sub',
                            'labelsByLocale' => ['fr' => 'Sub'],
                            'layout' => [
                                'desktop' => ['span' => 8],
                                'mobile' => ['span' => 12],
                            ],
                            'groups' => [],
                            'items' => [],
                        ],
                    ],
                ],
            ],
        ];

        self::assertNull(SkillsTreeContract::normalizeCatalog($catalog, self::ACTIVE_LOCALES, self::DEFAULT_LOCALE));
    }

    /**
     * @brief Catalogs without layout receive migration defaults from tree heuristics.
     *
     * @return void
     * @date 2026-06-11
     * @author Stephane H.
     */
    public function testNormalizeCatalogAppliesLayoutDefaultsWhenMissing(): void
    {
        $catalog = $this->normalizeSampleCatalog($this->buildSampleCatalog());

        self::assertSame(12, $catalog['categories'][0]['layout']['desktop']['span']);
        self::assertSame(12, $catalog['categories'][0]['layout']['tablet']['span']);
        self::assertSame(12, $catalog['categories'][0]['layout']['mobile']['span']);
        self::assertSame(12, $catalog['categories'][0]['subcategories'][0]['layout']['desktop']['span']);
    }

    /**
     * @brief Inline root categories without children default to a compact desktop span.
     *
     * @return void
     * @date 2026-06-11
     * @author Stephane H.
     */
    public function testNormalizeCatalogAppliesCompactRootLayoutDefaults(): void
    {
        $catalog = $this->normalizeSampleCatalog([
            'categories' => [
                [
                    'id' => 'cat-leaf',
                    'sortOrder' => 0,
                    'visibleOnPrimary' => true,
                    'labelMode' => SkillsTreeContract::LABEL_MODE_CANONICAL,
                    'canonicalLabel' => 'Leaf',
                    'labelsByLocale' => ['fr' => 'Leaf'],
                    'items' => [
                        [
                            'id' => 'item-a',
                            'sortOrder' => 0,
                            'visibleOnPrimary' => true,
                            'iconType' => SkillsTreeContract::ICON_TYPE_BOOTSTRAP,
                            'icon' => 'bi-code-slash',
                            'iconPath' => null,
                            'labelMode' => SkillsTreeContract::LABEL_MODE_CANONICAL,
                            'canonicalLabel' => 'Skill',
                            'labelsByLocale' => ['fr' => 'Skill'],
                        ],
                    ],
                    'subcategories' => [],
                ],
            ],
        ]);

        self::assertSame(4, $catalog['categories'][0]['layout']['desktop']['span']);
        self::assertSame(12, $catalog['categories'][0]['layout']['tablet']['span']);
        self::assertSame(12, $catalog['categories'][0]['layout']['mobile']['span']);
    }

    /**
     * @brief normalizeLayout clamps valid spans within parent budgets.
     *
     * @return void
     * @date 2026-06-11
     * @author Stephane H.
     */
    public function testNormalizeLayoutAcceptsValidSpans(): void
    {
        $layout = SkillsTreeContract::normalizeLayout(
            [
                'desktop' => ['span' => 6],
                'mobile' => ['span' => 12],
            ],
            12,
            12,
            12,
        );

        self::assertIsArray($layout);
        self::assertSame(6, $layout['desktop']['span']);
        self::assertSame(12, $layout['tablet']['span']);
        self::assertSame(12, $layout['mobile']['span']);
    }

    /**
     * @brief Tablet span falls back to mobile when legacy layouts omit tablet.
     *
     * @return void
     * @date 2026-06-12
     * @author Stephane H.
     */
    public function testNormalizeLayoutFallsBackTabletSpanToMobile(): void
    {
        $layout = SkillsTreeContract::normalizeLayout(
            [
                'desktop' => ['span' => 4],
                'mobile' => ['span' => 8],
            ],
            12,
            12,
            12,
        );

        self::assertIsArray($layout);
        self::assertSame(8, $layout['tablet']['span']);
    }

    /**
     * @brief maxChildSpan reads parent layout budgets per breakpoint.
     *
     * @return void
     * @date 2026-06-11
     * @author Stephane H.
     */
    public function testMaxChildSpanUsesParentLayout(): void
    {
        $parentLayout = [
            'desktop' => ['span' => 6],
            'mobile' => ['span' => 8],
        ];

        self::assertSame(6, SkillsTreeContract::maxChildSpan($parentLayout, SkillsTreeContract::LAYOUT_BREAKPOINT_DESKTOP));
        self::assertSame(8, SkillsTreeContract::maxChildSpan($parentLayout, SkillsTreeContract::LAYOUT_BREAKPOINT_MOBILE));
        self::assertSame(8, SkillsTreeContract::maxChildSpan([
            'desktop' => ['span' => 6],
            'tablet' => ['span' => 8],
            'mobile' => ['span' => 8],
        ], SkillsTreeContract::LAYOUT_BREAKPOINT_TABLET));
    }
}
