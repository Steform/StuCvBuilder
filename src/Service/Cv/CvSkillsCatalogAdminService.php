<?php

declare(strict_types=1);

namespace App\Service\Cv;

use App\Cv\BootstrapIconsManifest;
use App\Cv\SkillsTreeContract;
use App\Service\Cv\SkillsCatalogPersistence;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @brief CRUD operations for the CV skills catalog stored in CvProfile content JSON.
 *
 * @date 2026-05-29
 * @author Stephane H.
 */
final class CvSkillsCatalogAdminService
{
    public function __construct(
        private readonly CvSkillsIconUploadService $cvSkillsIconUploadService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @brief Load normalized catalog from the latest CV profile row.
     *
     * @param list<string> $activeLocales Active locale codes.
     * @param string $defaultLocale Site default locale.
     * @return array{categories: list<array<string, mixed>>}
     * @date 2026-05-29
     * @author Stephane H.
     */
    public function loadCatalog(array $activeLocales, string $defaultLocale, SkillsCatalogPersistence $persistence): array
    {
        $payload = $persistence->loadPayloadSlice();

        return SkillsTreeContract::resolveCatalogFromPayload(
            $payload,
            $activeLocales,
            $defaultLocale,
            $this->translator
        );
    }

    /**
     * @brief Persist a normalized catalog through the given persistence backend.
     *
     * @param array{categories: list<array<string, mixed>>} $catalog Normalized catalog.
     * @param list<string> $activeLocales Active locale codes.
     * @param string $defaultLocale Site default locale.
     * @param SkillsCatalogPersistence $persistence Catalog storage backend.
     * @return array{categories: list<array<string, mixed>>} Stored catalog.
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function saveCatalog(
        array $catalog,
        array $activeLocales,
        string $defaultLocale,
        SkillsCatalogPersistence $persistence,
    ): array {
        return $persistence->saveCatalog($catalog, $activeLocales, $defaultLocale);
    }

    /**
     * @brief Create or update a category node (levels 1–3).
     *
     * @param array<string, mixed> $input Raw admin input.
     * @param list<string> $activeLocales Active locale codes.
     * @param string $defaultLocale Site default locale.
     * @param SkillsCatalogPersistence $persistence Catalog storage backend.
     * @return array{categories: list<array<string, mixed>>} Updated catalog.
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function saveCategory(
        array $input,
        array $activeLocales,
        string $defaultLocale,
        SkillsCatalogPersistence $persistence,
    ): array {
        $catalog = $this->loadCatalog($activeLocales, $defaultLocale, $persistence);
        $level = (int) ($input['level'] ?? 0);
        $nodeId = is_string($input['id'] ?? null) ? trim((string) $input['id']) : '';
        $isCreate = $nodeId === '';

        if ($level === 1) {
            $catalog = $this->saveLevelOneCategory($catalog, $input, $nodeId, $isCreate, $activeLocales, $defaultLocale);
        } elseif ($level === 2) {
            $catalog = $this->saveLevelTwoCategory($catalog, $input, $nodeId, $isCreate, $activeLocales, $defaultLocale);
        } elseif ($level === 3) {
            $catalog = $this->saveLevelThreeGroup($catalog, $input, $nodeId, $isCreate, $activeLocales, $defaultLocale);
        } else {
            throw new \InvalidArgumentException('dashboard.customization_cv.skills.flash_invalid');
        }

        return $this->saveCatalog($catalog, $activeLocales, $defaultLocale, $persistence);
    }

    /**
     * @brief Delete a category node when children allow it.
     *
     * @param int $level Category level (1–3).
     * @param string $nodeId Node id.
     * @param string $categoryId Level-1 parent id (required for levels 2–3).
     * @param string|null $subcategoryId Level-2 parent id (required for level 3).
     * @param list<string> $activeLocales Active locale codes.
     * @param string $defaultLocale Site default locale.
     * @param SkillsCatalogPersistence $persistence Catalog storage backend.
     * @return array{categories: list<array<string, mixed>>} Updated catalog.
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function deleteCategory(
        int $level,
        string $nodeId,
        string $categoryId,
        ?string $subcategoryId,
        array $activeLocales,
        string $defaultLocale,
        SkillsCatalogPersistence $persistence,
    ): array {
        $catalog = $this->loadCatalog($activeLocales, $defaultLocale, $persistence);

        if ($level === 1) {
            $catalog['categories'] = array_values(array_filter(
                $catalog['categories'],
                static function (array $category) use ($nodeId): bool {
                    if (($category['id'] ?? '') !== $nodeId) {
                        return true;
                    }

                    if (!SkillsTreeContract::canDeleteCategory($category)) {
                        throw new \InvalidArgumentException('dashboard.customization_cv.skills.flash_delete_blocked');
                    }

                    return false;
                }
            ));
        } elseif ($level === 2) {
            $catalog = $this->mutateCategoryById($catalog, $categoryId, function (array $category) use ($nodeId): array {
                $category['subcategories'] = array_values(array_filter(
                    $category['subcategories'] ?? [],
                    static function (array $subcategory) use ($nodeId): bool {
                        if (($subcategory['id'] ?? '') !== $nodeId) {
                            return true;
                        }

                        if (!SkillsTreeContract::canDeleteSubcategory($subcategory)) {
                            throw new \InvalidArgumentException('dashboard.customization_cv.skills.flash_delete_blocked');
                        }

                        return false;
                    }
                ));

                return $category;
            });
        } elseif ($level === 3) {
            if ($subcategoryId === null || $subcategoryId === '') {
                throw new \InvalidArgumentException('dashboard.customization_cv.skills.flash_invalid');
            }

            $catalog = $this->mutateCategoryById($catalog, $categoryId, function (array $category) use ($subcategoryId, $nodeId): array {
                $category['subcategories'] = array_map(
                    static function (array $subcategory) use ($subcategoryId, $nodeId): array {
                        if (($subcategory['id'] ?? '') !== $subcategoryId) {
                            return $subcategory;
                        }

                        $subcategory['groups'] = array_values(array_filter(
                            $subcategory['groups'] ?? [],
                            static function (array $group) use ($nodeId): bool {
                                if (($group['id'] ?? '') !== $nodeId) {
                                    return true;
                                }

                                if (!SkillsTreeContract::canDeleteGroup($group)) {
                                    throw new \InvalidArgumentException('dashboard.customization_cv.skills.flash_delete_blocked');
                                }

                                return false;
                            }
                        ));

                        return $subcategory;
                    },
                    $category['subcategories'] ?? []
                );

                return $category;
            });
        } else {
            throw new \InvalidArgumentException('dashboard.customization_cv.skills.flash_invalid');
        }

        return $this->saveCatalog($catalog, $activeLocales, $defaultLocale, $persistence);
    }

    /**
     * @brief Move a category node up or down among its siblings.
     *
     * @param int $level Category level (1–3).
     * @param string $nodeId Node id to move.
     * @param string $categoryId Level-1 parent id (required for levels 2–3).
     * @param string|null $subcategoryId Level-2 parent id (required for level 3).
     * @param string $direction Move direction (`up` or `down`).
     * @param list<string> $activeLocales Active locale codes.
     * @param string $defaultLocale Site default locale.
     * @param SkillsCatalogPersistence $persistence Catalog storage backend.
     * @return array{categories: list<array<string, mixed>>} Updated catalog.
     * @date 2026-06-11
     * @author Stephane H.
     */
    public function moveCategory(
        int $level,
        string $nodeId,
        string $categoryId,
        ?string $subcategoryId,
        string $direction,
        array $activeLocales,
        string $defaultLocale,
        SkillsCatalogPersistence $persistence,
    ): array {
        if (!in_array($direction, ['up', 'down'], true)) {
            throw new \InvalidArgumentException('dashboard.customization_cv.skills.flash_invalid');
        }

        $catalog = $this->loadCatalog($activeLocales, $defaultLocale, $persistence);

        if ($level === 1) {
            $siblings = &$catalog['categories'];
            if (!$this->moveSiblingList($siblings, $nodeId, $direction)) {
                throw new \InvalidArgumentException('dashboard.customization_cv.skills.flash_move_blocked');
            }
            unset($siblings);
        } elseif ($level === 2) {
            $catalog = $this->mutateCategoryById($catalog, $categoryId, function (array $category) use ($nodeId, $direction): array {
                $siblings = $category['subcategories'] ?? [];
                if (!$this->moveSiblingList($siblings, $nodeId, $direction)) {
                    throw new \InvalidArgumentException('dashboard.customization_cv.skills.flash_move_blocked');
                }

                $category['subcategories'] = $siblings;

                return $category;
            });
        } elseif ($level === 3) {
            if ($subcategoryId === null || $subcategoryId === '') {
                throw new \InvalidArgumentException('dashboard.customization_cv.skills.flash_invalid');
            }

            $catalog = $this->mutateCategoryById($catalog, $categoryId, function (array $category) use ($subcategoryId, $nodeId, $direction): array {
                $category['subcategories'] = array_map(
                    function (array $subcategory) use ($subcategoryId, $nodeId, $direction): array {
                        if (($subcategory['id'] ?? '') !== $subcategoryId) {
                            return $subcategory;
                        }

                        $siblings = $subcategory['groups'] ?? [];
                        if (!$this->moveSiblingList($siblings, $nodeId, $direction)) {
                            throw new \InvalidArgumentException('dashboard.customization_cv.skills.flash_move_blocked');
                        }

                        $subcategory['groups'] = $siblings;

                        return $subcategory;
                    },
                    $category['subcategories'] ?? []
                );

                return $category;
            });
        } else {
            throw new \InvalidArgumentException('dashboard.customization_cv.skills.flash_invalid');
        }

        return $this->saveCatalog($catalog, $activeLocales, $defaultLocale, $persistence);
    }

    /**
     * @brief Create or update a skill item under a subcategory or level-3 group.
     *
     * Bootstrap icon classes must match the {@see SkillsTreeContract} icon pattern; custom uploads remain optional.
     *
     * @param array<string, mixed> $input Raw admin input.
     * @param UploadedFile|null $iconUpload Optional new icon file.
     * @param list<string> $activeLocales Active locale codes.
     * @param string $defaultLocale Site default locale.
     * @param SkillsCatalogPersistence $persistence Catalog storage backend.
     * @return array{categories: list<array<string, mixed>>} Updated catalog.
     * @date 2026-06-10
     * @author Stephane H.
     */
    public function saveSkill(
        array $input,
        ?UploadedFile $iconUpload,
        array $activeLocales,
        string $defaultLocale,
        SkillsCatalogPersistence $persistence,
    ): array {
        $catalog = $this->loadCatalog($activeLocales, $defaultLocale, $persistence);
        $categoryId = (string) ($input['categoryId'] ?? '');
        $subcategoryId = (string) ($input['subcategoryId'] ?? '');
        $groupId = isset($input['groupId']) && is_string($input['groupId']) ? trim($input['groupId']) : '';
        $skillId = is_string($input['id'] ?? null) ? trim((string) $input['id']) : '';
        $isCreate = $skillId === '';
        if ($isCreate) {
            $skillId = SkillsTreeContract::generateNodeId('item');
        }

        $iconType = is_string($input['iconType'] ?? null) ? (string) $input['iconType'] : SkillsTreeContract::ICON_TYPE_BOOTSTRAP;
        $existingPath = null;
        if (!$isCreate) {
            $existingPath = $this->findSkillIconPath($catalog, $skillId);
        }

        $iconPath = $existingPath;
        if ($iconUpload instanceof UploadedFile) {
            $this->cvSkillsIconUploadService->deleteIfNeeded($existingPath);
            $iconPath = $this->cvSkillsIconUploadService->store($iconUpload, $skillId);
            $iconType = SkillsTreeContract::ICON_TYPE_IMAGE;
        } elseif ($iconType === SkillsTreeContract::ICON_TYPE_BOOTSTRAP) {
            $this->cvSkillsIconUploadService->deleteIfNeeded($existingPath);
            $iconPath = null;
        } elseif ($iconType === SkillsTreeContract::ICON_TYPE_IMAGE && $iconPath === null) {
            $iconPath = is_string($input['iconPath'] ?? null) ? trim((string) $input['iconPath']) : null;
        }

        $bootstrapIcon = is_string($input['icon'] ?? null) ? trim((string) $input['icon']) : '';

        $skillRow = [
            'id' => $skillId,
            'sortOrder' => (int) ($input['sortOrder'] ?? 0),
            'visibleOnPrimary' => filter_var($input['visibleOnPrimary'] ?? true, FILTER_VALIDATE_BOOL),
            'labelMode' => (string) ($input['labelMode'] ?? SkillsTreeContract::LABEL_MODE_LOCALIZED),
            'canonicalLabel' => (string) ($input['canonicalLabel'] ?? ''),
            'labelsByLocale' => is_array($input['labelsByLocale'] ?? null) ? $input['labelsByLocale'] : [],
            'iconType' => $iconType,
            'icon' => $bootstrapIcon !== '' ? $bootstrapIcon : BootstrapIconsManifest::DEFAULT_ICON,
            'iconPath' => $iconPath,
        ];

        if (!$isCreate) {
            $catalog = $this->removeSkillFromCatalog($catalog, $skillId);
        }

        $catalog = $this->mutateCategoryById($catalog, $categoryId, function (array $category) use (
            $subcategoryId,
            $groupId,
            $skillId,
            $isCreate,
            $skillRow
        ): array {
            if ($subcategoryId === '') {
                $category['items'] = $this->upsertItemList(
                    is_array($category['items'] ?? null) ? $category['items'] : [],
                    $skillId,
                    $isCreate,
                    $skillRow
                );

                return $category;
            }

            $category['subcategories'] = array_map(
                function (array $subcategory) use ($subcategoryId, $groupId, $skillId, $isCreate, $skillRow): array {
                    if (($subcategory['id'] ?? '') !== $subcategoryId) {
                        return $subcategory;
                    }

                    if ($groupId !== '') {
                        $subcategory['groups'] = array_map(
                            function (array $group) use ($groupId, $skillId, $isCreate, $skillRow): array {
                                if (($group['id'] ?? '') !== $groupId) {
                                    return $group;
                                }

                                $group['items'] = $this->upsertItemList(
                                    is_array($group['items'] ?? null) ? $group['items'] : [],
                                    $skillId,
                                    $isCreate,
                                    $skillRow
                                );

                                return $group;
                            },
                            $subcategory['groups'] ?? []
                        );

                        return $subcategory;
                    }

                    $subcategory['items'] = $this->upsertItemList(
                        is_array($subcategory['items'] ?? null) ? $subcategory['items'] : [],
                        $skillId,
                        $isCreate,
                        $skillRow
                    );

                    return $subcategory;
                },
                $category['subcategories'] ?? []
            );

            return $category;
        });

        return $this->saveCatalog($catalog, $activeLocales, $defaultLocale, $persistence);
    }

    /**
     * @brief Delete a skill and its custom icon file when applicable.
     *
     * @param string $skillId Skill id.
     * @param list<string> $activeLocales Active locale codes.
     * @param string $defaultLocale Site default locale.
     * @param SkillsCatalogPersistence $persistence Catalog storage backend.
     * @return array{categories: list<array<string, mixed>>} Updated catalog.
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function deleteSkill(
        string $skillId,
        array $activeLocales,
        string $defaultLocale,
        SkillsCatalogPersistence $persistence,
    ): array {
        $catalog = $this->loadCatalog($activeLocales, $defaultLocale, $persistence);
        $skillCountBefore = $this->countSkillsInCatalog($catalog);
        $skillIdsBefore = $this->collectSkillIds($catalog);
        $iconPath = $this->findSkillIconPath($catalog, $skillId);
        if ($iconPath === null && !in_array($skillId, $skillIdsBefore, true)) {
            throw new \InvalidArgumentException('dashboard.customization_cv.skills.flash_invalid');
        }

        $catalog = $this->removeSkillFromCatalog($catalog, $skillId);
        $skillCountAfter = $this->countSkillsInCatalog($catalog);
        if ($skillCountAfter >= $skillCountBefore) {
            throw new \InvalidArgumentException('dashboard.customization_cv.skills.flash_invalid');
        }

        $savedCatalog = $this->saveCatalog($catalog, $activeLocales, $defaultLocale, $persistence);
        $this->cvSkillsIconUploadService->deleteIfNeeded($iconPath);

        return $savedCatalog;
    }

    /**
     * @param array{categories: list<array<string, mixed>>} $catalog Catalog.
     * @param array<string, mixed> $input Input.
     * @param string $nodeId Node id.
     * @param bool $isCreate Create flag.
     * @param list<string> $activeLocales Locales.
     * @param string $defaultLocale Default locale.
     * @return array{categories: list<array<string, mixed>>}
     */
    private function saveLevelOneCategory(
        array $catalog,
        array $input,
        string $nodeId,
        bool $isCreate,
        array $activeLocales,
        string $defaultLocale,
    ): array {
        if ($isCreate) {
            $nodeId = SkillsTreeContract::generateNodeId('cat');
            $children = ['subcategories' => [], 'items' => []];
            $catalog['categories'][] = $this->buildCategoryRow(
                $nodeId,
                $this->mergeInputSortOrder($input, null, count($catalog['categories'])),
                $activeLocales,
                $children,
                1,
            );
        } else {
            $catalog['categories'] = array_map(
                function (array $category) use ($nodeId, $input, $activeLocales): array {
                    if (($category['id'] ?? '') !== $nodeId) {
                        return $category;
                    }

                    return $this->buildCategoryRow($nodeId, $this->mergeInputSortOrder(
                        $input,
                        (int) ($category['sortOrder'] ?? 0),
                    ), $activeLocales, [
                        'subcategories' => $category['subcategories'] ?? [],
                        'items' => $category['items'] ?? [],
                    ], 1);
                },
                $catalog['categories']
            );
        }

        return $catalog;
    }

    /**
     * @param array{categories: list<array<string, mixed>>} $catalog Catalog.
     * @param array<string, mixed> $input Input.
     * @param string $nodeId Node id.
     * @param bool $isCreate Create flag.
     * @param list<string> $activeLocales Locales.
     * @param string $defaultLocale Default locale.
     * @return array{categories: list<array<string, mixed>>}
     */
    private function saveLevelTwoCategory(
        array $catalog,
        array $input,
        string $nodeId,
        bool $isCreate,
        array $activeLocales,
        string $defaultLocale,
    ): array {
        $categoryId = (string) ($input['categoryId'] ?? '');
        if ($isCreate) {
            $nodeId = SkillsTreeContract::generateNodeId('sub');
        }

        return $this->mutateCategoryById($catalog, $categoryId, function (array $category) use (
            $nodeId,
            $isCreate,
            $input,
            $activeLocales
        ): array {
            $parentLayout = is_array($category['layout'] ?? null)
                ? $category['layout']
                : SkillsTreeContract::rootLayoutBudget();
            $subs = $category['subcategories'] ?? [];
            if ($isCreate) {
                $subs[] = $this->buildSubcategoryRow(
                    $nodeId,
                    $this->mergeInputSortOrder($input, null, count($subs)),
                    $activeLocales,
                    [
                        'groups' => [],
                        'items' => [],
                    ],
                    $parentLayout,
                );
            } else {
                $subs = array_map(
                    function (array $subcategory) use ($nodeId, $input, $activeLocales, $parentLayout): array {
                        if (($subcategory['id'] ?? '') !== $nodeId) {
                            return $subcategory;
                        }

                        return $this->buildSubcategoryRow($nodeId, $this->mergeInputSortOrder(
                            $input,
                            (int) ($subcategory['sortOrder'] ?? 0),
                        ), $activeLocales, [
                            'groups' => $subcategory['groups'] ?? [],
                            'items' => $subcategory['items'] ?? [],
                        ], $parentLayout);
                    },
                    $subs
                );
            }

            $category['subcategories'] = $subs;

            return $category;
        });
    }

    /**
     * @param array{categories: list<array<string, mixed>>} $catalog Catalog.
     * @param array<string, mixed> $input Input.
     * @param string $nodeId Node id.
     * @param bool $isCreate Create flag.
     * @param list<string> $activeLocales Locales.
     * @param string $defaultLocale Default locale.
     * @return array{categories: list<array<string, mixed>>}
     */
    private function saveLevelThreeGroup(
        array $catalog,
        array $input,
        string $nodeId,
        bool $isCreate,
        array $activeLocales,
        string $defaultLocale,
    ): array {
        $categoryId = (string) ($input['categoryId'] ?? '');
        $subcategoryId = (string) ($input['subcategoryId'] ?? '');
        if ($isCreate) {
            $nodeId = SkillsTreeContract::generateNodeId('grp');
        }

        return $this->mutateCategoryById($catalog, $categoryId, function (array $category) use (
            $subcategoryId,
            $nodeId,
            $isCreate,
            $input,
            $activeLocales
        ): array {
            $category['subcategories'] = array_map(
                function (array $subcategory) use ($category, $subcategoryId, $nodeId, $isCreate, $input, $activeLocales): array {
                    if (($subcategory['id'] ?? '') !== $subcategoryId) {
                        return $subcategory;
                    }

                    $parentLayout = is_array($subcategory['layout'] ?? null)
                        ? $subcategory['layout']
                        : SkillsTreeContract::resolveLayoutDefaults(
                            2,
                            SkillsTreeContract::maxChildSpan(
                                is_array($category['layout'] ?? null) ? $category['layout'] : SkillsTreeContract::rootLayoutBudget(),
                                SkillsTreeContract::LAYOUT_BREAKPOINT_DESKTOP,
                            ),
                            SkillsTreeContract::maxChildSpan(
                                is_array($category['layout'] ?? null) ? $category['layout'] : SkillsTreeContract::rootLayoutBudget(),
                                SkillsTreeContract::LAYOUT_BREAKPOINT_TABLET,
                            ),
                            SkillsTreeContract::maxChildSpan(
                                is_array($category['layout'] ?? null) ? $category['layout'] : SkillsTreeContract::rootLayoutBudget(),
                                SkillsTreeContract::LAYOUT_BREAKPOINT_MOBILE,
                            ),
                            count($subcategory['groups'] ?? []) > 0,
                            count($subcategory['items'] ?? []),
                        );
                    $groups = $subcategory['groups'] ?? [];
                    if ($isCreate) {
                        $groups[] = $this->buildGroupRow(
                            $nodeId,
                            $this->mergeInputSortOrder($input, null, count($groups)),
                            $activeLocales,
                            ['items' => []],
                            $parentLayout,
                        );
                    } else {
                        $groups = array_map(
                            function (array $group) use ($nodeId, $input, $activeLocales, $parentLayout): array {
                                if (($group['id'] ?? '') !== $nodeId) {
                                    return $group;
                                }

                                return $this->buildGroupRow($nodeId, $this->mergeInputSortOrder(
                                    $input,
                                    (int) ($group['sortOrder'] ?? 0),
                                ), $activeLocales, [
                                    'items' => $group['items'] ?? [],
                                ], $parentLayout);
                            },
                            $groups
                        );
                    }

                    $subcategory['groups'] = $groups;

                    return $subcategory;
                },
                $category['subcategories'] ?? []
            );

            return $category;
        });
    }

    /**
     * @param string $nodeId Node id.
     * @param array<string, mixed> $input Input.
     * @param list<string> $activeLocales Locales.
     * @param array<string, mixed> $children Child keys.
     * @param int $level Category depth (1–3).
     * @param array{desktop: array{span: int}, tablet: array{span: int}, mobile: array{span: int}}|null $parentLayout Parent layout for levels 2–3.
     * @return array<string, mixed>
     */
    private function buildCategoryRow(
        string $nodeId,
        array $input,
        array $activeLocales,
        array $children,
        int $level,
        ?array $parentLayout = null,
    ): array {
        $layoutBudget = $parentLayout ?? SkillsTreeContract::rootLayoutBudget();
        $maxDesktop = $level === 1
            ? SkillsTreeContract::GRID_ROOT_BUDGET
            : SkillsTreeContract::maxChildSpan($layoutBudget, SkillsTreeContract::LAYOUT_BREAKPOINT_DESKTOP);
        $maxTablet = $level === 1
            ? SkillsTreeContract::GRID_ROOT_BUDGET
            : SkillsTreeContract::maxChildSpan($layoutBudget, SkillsTreeContract::LAYOUT_BREAKPOINT_TABLET);
        $maxMobile = $level === 1
            ? SkillsTreeContract::GRID_ROOT_BUDGET
            : SkillsTreeContract::maxChildSpan($layoutBudget, SkillsTreeContract::LAYOUT_BREAKPOINT_MOBILE);

        return array_merge([
            'id' => $nodeId,
            'sortOrder' => (int) ($input['sortOrder'] ?? 0),
            'visibleOnPrimary' => filter_var($input['visibleOnPrimary'] ?? true, FILTER_VALIDATE_BOOL),
            'labelMode' => (string) ($input['labelMode'] ?? SkillsTreeContract::LABEL_MODE_LOCALIZED),
            'canonicalLabel' => (string) ($input['canonicalLabel'] ?? ''),
            'labelsByLocale' => $this->filterLabelsForLocales($input, $activeLocales),
        ], $this->buildLayoutFieldsFromInput($input, $maxDesktop, $maxTablet, $maxMobile), $children);
    }

    /**
     * @param string $nodeId Node id.
     * @param array<string, mixed> $input Input.
     * @param list<string> $activeLocales Locales.
     * @param array<string, mixed> $children Child keys.
     * @param array{desktop: array{span: int}, tablet: array{span: int}, mobile: array{span: int}} $parentLayout Parent category layout.
     * @return array<string, mixed>
     */
    private function buildSubcategoryRow(
        string $nodeId,
        array $input,
        array $activeLocales,
        array $children,
        array $parentLayout,
    ): array {
        return $this->buildCategoryRow($nodeId, $input, $activeLocales, $children, 2, $parentLayout);
    }

    /**
     * @param string $nodeId Node id.
     * @param array<string, mixed> $input Input.
     * @param list<string> $activeLocales Locales.
     * @param array<string, mixed> $children Child keys.
     * @param array{desktop: array{span: int}, tablet: array{span: int}, mobile: array{span: int}} $parentLayout Parent subcategory layout.
     * @return array<string, mixed>
     */
    private function buildGroupRow(
        string $nodeId,
        array $input,
        array $activeLocales,
        array $children,
        array $parentLayout,
    ): array {
        return $this->buildCategoryRow($nodeId, $input, $activeLocales, $children, 3, $parentLayout);
    }

    /**
     * @brief Build optional layout fields from admin form span inputs.
     *
     * @param array<string, mixed> $input Raw admin input.
     * @param int $maxDesktop Maximum desktop span for the node.
     * @param int $maxTablet Maximum tablet span for the node.
     * @param int $maxMobile Maximum mobile span for the node.
     * @return array{layout?: array{desktop: array{span: int}, tablet: array{span: int}, mobile: array{span: int}}}
     * @date 2026-06-12
     * @author Stephane H.
     */
    private function buildLayoutFieldsFromInput(array $input, int $maxDesktop, int $maxTablet, int $maxMobile): array
    {
        if (
            !array_key_exists('layoutDesktopSpan', $input)
            && !array_key_exists('layoutTabletSpan', $input)
            && !array_key_exists('layoutMobileSpan', $input)
        ) {
            return [];
        }

        $desktopRaw = $input['layoutDesktopSpan'] ?? null;
        $tabletRaw = $input['layoutTabletSpan'] ?? null;
        $mobileRaw = $input['layoutMobileSpan'] ?? null;
        if ($desktopRaw === null || $mobileRaw === null || $desktopRaw === '' || $mobileRaw === '') {
            return [];
        }

        $layout = SkillsTreeContract::normalizeLayout(
            [
                'desktop' => ['span' => (int) $desktopRaw],
                'tablet' => ['span' => (int) ($tabletRaw !== null && $tabletRaw !== '' ? $tabletRaw : $mobileRaw)],
                'mobile' => ['span' => (int) $mobileRaw],
            ],
            $maxDesktop,
            $maxTablet,
            $maxMobile,
        );
        if ($layout === null) {
            throw new \InvalidArgumentException('dashboard.customization_cv.skills.flash_invalid');
        }

        return ['layout' => $layout];
    }

    /**
     * @param array<string, mixed> $input Input.
     * @param list<string> $activeLocales Locales.
     * @return array<string, string>
     */
    private function filterLabelsForLocales(array $input, array $activeLocales): array
    {
        $raw = is_array($input['labelsByLocale'] ?? null) ? $input['labelsByLocale'] : [];
        $labels = [];
        foreach ($activeLocales as $locale) {
            if (!isset($raw[$locale]) || !is_string($raw[$locale])) {
                continue;
            }

            $trimmed = trim($raw[$locale]);
            if ($trimmed !== '') {
                $labels[$locale] = $trimmed;
            }
        }

        return $labels;
    }

    /**
     * @param list<array<string, mixed>> $items Items.
     * @param string $skillId Skill id.
     * @param bool $isCreate Create flag.
     * @param array<string, mixed> $skillRow Skill row.
     * @return list<array<string, mixed>>
     */
    private function upsertItemList(array $items, string $skillId, bool $isCreate, array $skillRow): array
    {
        if ($isCreate) {
            $items[] = $skillRow;

            return $items;
        }

        $found = false;
        $items = array_map(static function (array $item) use ($skillId, $skillRow, &$found): array {
            if (($item['id'] ?? '') !== $skillId) {
                return $item;
            }

            $found = true;

            return $skillRow;
        }, $items);

        if (!$found) {
            $items[] = $skillRow;
        }

        return $items;
    }

    /**
     * @param array{categories: list<array<string, mixed>>} $catalog Catalog.
     * @param string $categoryId Category id.
     * @param callable(array<string, mixed>): array<string, mixed> $mutator Mutator.
     * @return array{categories: list<array<string, mixed>>}
     */
    private function mutateCategoryById(array $catalog, string $categoryId, callable $mutator): array
    {
        $catalog['categories'] = array_map(
            static function (array $category) use ($categoryId, $mutator): array {
                if (($category['id'] ?? '') !== $categoryId) {
                    return $category;
                }

                return $mutator($category);
            },
            $catalog['categories']
        );

        return $catalog;
    }

    /**
     * @param array{categories: list<array<string, mixed>>} $catalog Catalog.
     * @param string $skillId Skill id.
     * @return string|null Icon path.
     */
    private function findSkillIconPath(array $catalog, string $skillId): ?string
    {
        foreach ($catalog['categories'] as $category) {
            foreach ($category['items'] ?? [] as $item) {
                if (is_array($item) && ($item['id'] ?? '') === $skillId) {
                    return is_string($item['iconPath'] ?? null) ? $item['iconPath'] : null;
                }
            }

            foreach ($category['subcategories'] ?? [] as $subcategory) {
                foreach ($subcategory['items'] ?? [] as $item) {
                    if (is_array($item) && ($item['id'] ?? '') === $skillId) {
                        return is_string($item['iconPath'] ?? null) ? $item['iconPath'] : null;
                    }
                }

                foreach ($subcategory['groups'] ?? [] as $group) {
                    foreach ($group['items'] ?? [] as $item) {
                        if (is_array($item) && ($item['id'] ?? '') === $skillId) {
                            return is_string($item['iconPath'] ?? null) ? $item['iconPath'] : null;
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * @brief Count skill items across the full catalog tree.
     *
     * @param array{categories: list<array<string, mixed>>} $catalog Catalog.
     * @return int Total skill count.
     * @date 2026-06-09
     * @author Stephane H.
     */
    private function countSkillsInCatalog(array $catalog): int
    {
        return count($this->collectSkillIds($catalog));
    }

    /**
     * @brief Collect skill ids from every container in the catalog tree.
     *
     * @param array{categories: list<array<string, mixed>>} $catalog Catalog.
     * @return list<string>
     * @date 2026-06-09
     * @author Stephane H.
     */
    private function collectSkillIds(array $catalog): array
    {
        $ids = [];
        foreach ($catalog['categories'] as $category) {
            if (!is_array($category)) {
                continue;
            }

            foreach (is_array($category['items'] ?? null) ? $category['items'] : [] as $item) {
                if (is_array($item) && is_string($item['id'] ?? null) && $item['id'] !== '') {
                    $ids[] = $item['id'];
                }
            }

            foreach ($category['subcategories'] ?? [] as $subcategory) {
                if (!is_array($subcategory)) {
                    continue;
                }

                foreach (is_array($subcategory['items'] ?? null) ? $subcategory['items'] : [] as $item) {
                    if (is_array($item) && is_string($item['id'] ?? null) && $item['id'] !== '') {
                        $ids[] = $item['id'];
                    }
                }

                foreach ($subcategory['groups'] ?? [] as $group) {
                    if (!is_array($group)) {
                        continue;
                    }

                    foreach (is_array($group['items'] ?? null) ? $group['items'] : [] as $item) {
                        if (is_array($item) && is_string($item['id'] ?? null) && $item['id'] !== '') {
                            $ids[] = $item['id'];
                        }
                    }
                }
            }
        }

        return $ids;
    }

    /**
     * @brief Remove a skill from all category containers without mutating by reference.
     *
     * @param array{categories: list<array<string, mixed>>} $catalog Catalog.
     * @param string $skillId Skill id.
     * @return array{categories: list<array<string, mixed>>}
     * @date 2026-06-09
     * @author Stephane H.
     */
    private function removeSkillFromCatalog(array $catalog, string $skillId): array
    {
        $catalog['categories'] = array_map(
            function (array $category) use ($skillId): array {
                $category['items'] = $this->filterSkillItems(
                    is_array($category['items'] ?? null) ? $category['items'] : [],
                    $skillId
                );
                $category['subcategories'] = array_map(
                    function (array $subcategory) use ($skillId): array {
                        $subcategory['items'] = $this->filterSkillItems(
                            is_array($subcategory['items'] ?? null) ? $subcategory['items'] : [],
                            $skillId
                        );
                        $subcategory['groups'] = array_map(
                            function (array $group) use ($skillId): array {
                                $group['items'] = $this->filterSkillItems(
                                    is_array($group['items'] ?? null) ? $group['items'] : [],
                                    $skillId
                                );

                                return $group;
                            },
                            is_array($subcategory['groups'] ?? null) ? $subcategory['groups'] : []
                        );

                        return $subcategory;
                    },
                    is_array($category['subcategories'] ?? null) ? $category['subcategories'] : []
                );

                return $category;
            },
            $catalog['categories']
        );

        return $catalog;
    }

    /**
     * @brief Drop one skill id from a normalized item list.
     *
     * @param list<mixed> $items Skill rows.
     * @param string $skillId Skill id to remove.
     * @return list<array<string, mixed>>
     * @date 2026-06-09
     * @author Stephane H.
     */
    private function filterSkillItems(array $items, string $skillId): array
    {
        return array_values(array_filter(
            $items,
            static fn (mixed $item): bool => is_array($item) && (string) ($item['id'] ?? '') !== $skillId
        ));
    }

    /**
     * @brief Swap a sibling node up or down and reindex sequential sort orders.
     *
     * @param list<array<string, mixed>> $siblings Sibling nodes (mutated in place).
     * @param string $nodeId Node id to move.
     * @param string $direction Move direction (`up` or `down`).
     * @return bool False when the node is missing or the move is out of bounds.
     * @date 2026-06-11
     * @author Stephane H.
     */
    private function moveSiblingList(array &$siblings, string $nodeId, string $direction): bool
    {
        $index = null;
        foreach ($siblings as $i => $sibling) {
            if (is_array($sibling) && ($sibling['id'] ?? '') === $nodeId) {
                $index = $i;
                break;
            }
        }

        if ($index === null) {
            return false;
        }

        $targetIndex = $direction === 'up' ? $index - 1 : $index + 1;
        if ($targetIndex < 0 || $targetIndex >= count($siblings)) {
            return false;
        }

        $temp = $siblings[$index];
        $siblings[$index] = $siblings[$targetIndex];
        $siblings[$targetIndex] = $temp;
        $this->reindexSiblingSortOrders($siblings);

        return true;
    }

    /**
     * @brief Assign contiguous sortOrder values after a sibling reorder.
     *
     * @param list<array<string, mixed>> $siblings Sibling nodes (mutated in place).
     * @return void
     * @date 2026-06-11
     * @author Stephane H.
     */
    private function reindexSiblingSortOrders(array &$siblings): void
    {
        foreach ($siblings as $i => &$sibling) {
            if (!is_array($sibling)) {
                continue;
            }

            $sibling['sortOrder'] = $i;
        }
        unset($sibling);
    }

    /**
     * @brief Keep existing sort order on update; append at end on create when absent from input.
     *
     * @param array<string, mixed> $input Raw admin input.
     * @param int|null $existingSortOrder Current sibling sort order on update.
     * @param int|null $createFallback Sort order for new siblings when input omits it.
     * @return array<string, mixed>
     * @date 2026-06-11
     * @author Stephane H.
     */
    private function mergeInputSortOrder(array $input, ?int $existingSortOrder = null, ?int $createFallback = null): array
    {
        if (array_key_exists('sortOrder', $input)) {
            return $input;
        }

        if ($existingSortOrder !== null) {
            $input['sortOrder'] = $existingSortOrder;

            return $input;
        }

        if ($createFallback !== null) {
            $input['sortOrder'] = $createFallback;
        }

        return $input;
    }
}
