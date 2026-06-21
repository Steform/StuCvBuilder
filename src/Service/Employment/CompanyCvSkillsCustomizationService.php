<?php

declare(strict_types=1);

namespace App\Service\Employment;

use App\Cv\CompanyCvCustomizationSectionKey;
use App\Cv\CompanyCvSkillsOverrideScope;
use App\Cv\CvProfilePersistenceScope;
use App\Cv\SkillsTreeContract;
use App\Entity\CompanyCvSectionOverride;
use App\Entity\CvProfile;
use App\Entity\TrackedCompany;
use App\Repository\CompanyCvSectionOverrideRepository;
use App\Repository\CvProfileRepository;
use App\Service\Cv\CvSkillsCatalogAdminService;
use App\Service\Cv\CvSkillsSettingsService;
use App\Service\Cv\SkillsCatalogPersistence;
use App\Service\Locale\LocaleConfigurationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @brief Per-company Skills section customization (admin UI + public CV merge).
 */
class CompanyCvSkillsCustomizationService
{
    public const CSRF_SKILLS = 'employment_company_cv_skills';

    public const CSRF_SKILLS_ENABLE = 'employment_company_cv_skills_enable';

    public const CSRF_SKILLS_RESET = 'employment_company_cv_skills_reset';

    /**
     * @brief Wire company Skills customization dependencies.
     *
     * @param EntityManagerInterface $entityManager ORM.
     * @param CompanyCvSectionOverrideRepository $overrideRepository Override repository.
     * @param CvProfileRepository $cvProfileRepository Global CV profile repository.
     * @param CvSkillsCatalogAdminService $cvSkillsCatalogAdminService Skills catalog CRUD service.
     * @param CvSkillsSettingsService $cvSkillsSettingsService Skills projection service.
     * @param LocaleConfigurationService $localeConfigurationService Locale configuration.
     * @param UrlGeneratorInterface $urlGenerator Route generator for company AJAX endpoints.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CompanyCvSectionOverrideRepository $overrideRepository,
        private readonly CvProfileRepository $cvProfileRepository,
        private readonly CvSkillsCatalogAdminService $cvSkillsCatalogAdminService,
        private readonly CvSkillsSettingsService $cvSkillsSettingsService,
        private readonly LocaleConfigurationService $localeConfigurationService,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @brief Whether the company has a persisted Skills override row.
     *
     * @param TrackedCompany $company Tracked company.
     * @return bool
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function isSkillsCustomized(TrackedCompany $company): bool
    {
        return $this->overrideRepository->findOneForCompanySection($company, CompanyCvCustomizationSectionKey::SKILLS) !== null;
    }

    /**
     * @brief Merge company Skills override into resolved CV payload when present.
     *
     * @param array<string, mixed> $payload Default CV payload after global resolve steps.
     * @param TrackedCompany|null $company Active tracked company or null.
     * @param list<string> $activeLocales Active locale codes.
     * @param string $defaultLocale Site default locale.
     * @return array<string, mixed>
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function mergeSkillsOverrideIntoPayload(
        array $payload,
        ?TrackedCompany $company,
        array $activeLocales,
        string $defaultLocale,
    ): array {
        if ($company === null) {
            return $payload;
        }

        $override = $this->overrideRepository->findOneForCompanySection($company, CompanyCvCustomizationSectionKey::SKILLS);
        if ($override === null) {
            return $payload;
        }

        $overridePayload = CompanyCvSkillsOverrideScope::decodeJson($override->getContentJson());

        return CompanyCvSkillsOverrideScope::mergeIntoPayload($payload, $overridePayload, $activeLocales, $defaultLocale);
    }

    /**
     * @brief Copy global Skills catalog into a new company override row.
     *
     * @param TrackedCompany $company Tracked company.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function enableSkillsCustomization(TrackedCompany $company): void
    {
        if ($this->isSkillsCustomized($company)) {
            return;
        }

        [$activeLocales, $defaultLocale] = $this->resolveLocales();
        $globalPayload = $this->loadLatestGlobalProfilePayload();
        $slice = CompanyCvSkillsOverrideScope::extractFromProfilePayload($globalPayload);
        if ($slice === []) {
            $catalog = $this->cvSkillsSettingsService->resolveFromPayload(
                $globalPayload,
                $activeLocales,
                $defaultLocale,
                $defaultLocale,
            )['catalog'];
            $slice = [SkillsTreeContract::KEY => $catalog];
        }

        $sanitized = CompanyCvSkillsOverrideScope::sanitizeForPersistence($slice, $activeLocales, $defaultLocale);
        $json = json_encode($sanitized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $override = new CompanyCvSectionOverride(
            $company,
            CompanyCvCustomizationSectionKey::SKILLS,
            is_string($json) ? $json : '{}',
        );
        $this->entityManager->persist($override);
        $this->entityManager->flush();
    }

    /**
     * @brief Remove company Skills override (revert to global CV).
     *
     * @param TrackedCompany $company Tracked company.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function resetSkillsToInherited(TrackedCompany $company): void
    {
        $override = $this->overrideRepository->findOneForCompanySection($company, CompanyCvCustomizationSectionKey::SKILLS);
        if ($override === null) {
            return;
        }

        $this->entityManager->remove($override);
        $this->entityManager->flush();
    }

    /**
     * @brief Build Twig variables for company Skills admin panel.
     *
     * @param TrackedCompany $company Tracked company.
     * @param Request $request HTTP request for panel state.
     * @return array<string, mixed>
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function buildSkillsAdminViewData(TrackedCompany $company, Request $request): array
    {
        [$activeLocales, $defaultLocale] = $this->resolveLocales();
        $globalPayload = $this->loadLatestGlobalProfilePayload();
        $globalResolved = $this->cvSkillsSettingsService->resolveFromPayload(
            $globalPayload,
            $activeLocales,
            $defaultLocale,
            (string) $request->getLocale(),
        );

        $override = $this->overrideRepository->findOneForCompanySection($company, CompanyCvCustomizationSectionKey::SKILLS);
        $isCustomized = $override !== null;

        $skillsPayload = $isCustomized
            ? CompanyCvSkillsOverrideScope::decodeJson($override->getContentJson())
            : CompanyCvSkillsOverrideScope::extractFromProfilePayload($globalPayload);

        if ($skillsPayload === [] && !$isCustomized) {
            $skillsPayload = [SkillsTreeContract::KEY => $globalResolved['catalog']];
        }

        $overrideResolved = $this->cvSkillsSettingsService->resolveFromPayload(
            $skillsPayload,
            $activeLocales,
            $defaultLocale,
            (string) $request->getLocale(),
        );

        $companyId = (int) ($company->getId() ?? 0);

        return [
            'cvSkillsCustomizationEnabled' => $isCustomized,
            'cvSkillsInheritedSummary' => $this->buildInheritedSummary($globalResolved['catalog'], $defaultLocale),
            'cvSkillsCatalog' => $overrideResolved['catalog'],
            'activeLocales' => $activeLocales,
            'defaultLocale' => $defaultLocale,
            'cvCustomizationActivePanel' => 'skills_catalog',
            'cvSkillsRoutes' => $this->buildCompanySkillsRoutes($companyId),
            'cvSkillsCsrfTokenId' => self::CSRF_SKILLS,
            'cvSkillsCustomizationSection' => CompanyCvCustomizationSectionKey::SKILLS,
        ];
    }

    /**
     * @brief Create catalog persistence for AJAX CRUD on a customized company.
     *
     * @param TrackedCompany $company Tracked company.
     * @return SkillsCatalogPersistence
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function createCatalogPersistence(TrackedCompany $company): SkillsCatalogPersistence
    {
        if (!$this->isSkillsCustomized($company)) {
            throw new \InvalidArgumentException('employment.companies.cv_customization.skills.flash.not_enabled');
        }

        return new CompanySkillsCatalogPersistence($company, $this->overrideRepository, $this->entityManager);
    }

    /**
     * @brief Save a category for a company override catalog.
     *
     * @param TrackedCompany $company Tracked company.
     * @param array<string, mixed> $input Raw admin input.
     * @return array{categories: list<array<string, mixed>>}
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function saveCategoryForCompany(TrackedCompany $company, array $input): array
    {
        [$activeLocales, $defaultLocale] = $this->resolveLocales();

        return $this->cvSkillsCatalogAdminService->saveCategory(
            $input,
            $activeLocales,
            $defaultLocale,
            $this->createCatalogPersistence($company),
        );
    }

    /**
     * @brief Delete a category for a company override catalog.
     *
     * @param TrackedCompany $company Tracked company.
     * @param int $level Category level.
     * @param string $nodeId Node id.
     * @param string $categoryId Parent category id.
     * @param string|null $subcategoryId Parent subcategory id.
     * @return array{categories: list<array<string, mixed>>}
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function deleteCategoryForCompany(
        TrackedCompany $company,
        int $level,
        string $nodeId,
        string $categoryId,
        ?string $subcategoryId,
    ): array {
        [$activeLocales, $defaultLocale] = $this->resolveLocales();

        return $this->cvSkillsCatalogAdminService->deleteCategory(
            $level,
            $nodeId,
            $categoryId,
            $subcategoryId,
            $activeLocales,
            $defaultLocale,
            $this->createCatalogPersistence($company),
        );
    }

    /**
     * @brief Move a category node within a company override catalog.
     *
     * @param TrackedCompany $company Tracked company.
     * @param int $level Category level.
     * @param string $nodeId Node id.
     * @param string $categoryId Parent category id.
     * @param string|null $subcategoryId Parent subcategory id.
     * @param string $direction Move direction (`up` or `down`).
     * @return array{categories: list<array<string, mixed>>}
     * @date 2026-06-11
     * @author Stephane H.
     */
    public function moveCategoryForCompany(
        TrackedCompany $company,
        int $level,
        string $nodeId,
        string $categoryId,
        ?string $subcategoryId,
        string $direction,
    ): array {
        [$activeLocales, $defaultLocale] = $this->resolveLocales();

        return $this->cvSkillsCatalogAdminService->moveCategory(
            $level,
            $nodeId,
            $categoryId,
            $subcategoryId,
            $direction,
            $activeLocales,
            $defaultLocale,
            $this->createCatalogPersistence($company),
        );
    }

    /**
     * @brief Save a skill for a company override catalog.
     *
     * @param TrackedCompany $company Tracked company.
     * @param array<string, mixed> $input Raw admin input.
     * @param UploadedFile|null $iconUpload Optional icon upload.
     * @return array{categories: list<array<string, mixed>>}
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function saveSkillForCompany(TrackedCompany $company, array $input, ?UploadedFile $iconUpload): array
    {
        [$activeLocales, $defaultLocale] = $this->resolveLocales();

        return $this->cvSkillsCatalogAdminService->saveSkill(
            $input,
            $iconUpload,
            $activeLocales,
            $defaultLocale,
            $this->createCatalogPersistence($company),
        );
    }

    /**
     * @brief Delete a skill for a company override catalog.
     *
     * @param TrackedCompany $company Tracked company.
     * @param string $skillId Skill id.
     * @return array{categories: list<array<string, mixed>>}
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function deleteSkillForCompany(TrackedCompany $company, string $skillId): array
    {
        [$activeLocales, $defaultLocale] = $this->resolveLocales();

        return $this->cvSkillsCatalogAdminService->deleteSkill(
            $skillId,
            $activeLocales,
            $defaultLocale,
            $this->createCatalogPersistence($company),
        );
    }

    /**
     * @brief Load latest global CV profile decoded payload.
     *
     * @param void No input parameter.
     * @return array<string, mixed>
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function loadLatestGlobalProfilePayload(): array
    {
        $profile = $this->cvProfileRepository->findOneBy([], ['id' => 'DESC']);
        if (!$profile instanceof CvProfile) {
            return [];
        }

        $decoded = json_decode($profile->getContentJson(), true);

        return is_array($decoded)
            ? CvProfilePersistenceScope::sanitizeForPersistence($decoded)
            : [];
    }

    /**
     * @brief Resolve active locales and default locale.
     *
     * @param void No input parameter.
     * @return array{0: list<string>, 1: string}
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function resolveLocales(): array
    {
        $localeConfig = $this->localeConfigurationService->getConfiguration();
        $activeLocales = is_array($localeConfig['activeLocales'] ?? null) ? $localeConfig['activeLocales'] : ['fr'];
        if ($activeLocales === []) {
            $activeLocales = $this->localeConfigurationService->getSupportedLocales();
        }
        $defaultLocale = is_string($localeConfig['defaultLocale'] ?? null) ? $localeConfig['defaultLocale'] : ($activeLocales[0] ?? 'fr');

        return [$activeLocales, $defaultLocale];
    }

    /**
     * @brief Build inherited catalog summary for the admin shell preview.
     *
     * @param array{categories: list<array<string, mixed>>} $catalog Resolved global catalog.
     * @param string $defaultLocale Default locale for labels.
     * @return array{categoryCount: int, skillCount: int, sampleLabels: list<string>}
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function buildInheritedSummary(array $catalog, string $defaultLocale): array
    {
        $sampleLabels = [];
        $skillCount = 0;

        foreach ($catalog['categories'] ?? [] as $category) {
            if (!is_array($category)) {
                continue;
            }

            $this->collectSkillLabelsFromNode($category, $defaultLocale, $sampleLabels, $skillCount);
            foreach ($category['subcategories'] ?? [] as $subcategory) {
                if (!is_array($subcategory)) {
                    continue;
                }

                $this->collectSkillLabelsFromNode($subcategory, $defaultLocale, $sampleLabels, $skillCount);
                foreach ($subcategory['groups'] ?? [] as $group) {
                    if (is_array($group)) {
                        $this->collectSkillLabelsFromNode($group, $defaultLocale, $sampleLabels, $skillCount);
                    }
                }
            }
        }

        return [
            'categoryCount' => count($catalog['categories'] ?? []),
            'skillCount' => $skillCount,
            'sampleLabels' => array_slice(array_values(array_unique($sampleLabels)), 0, 6),
        ];
    }

    /**
     * @brief Collect skill labels and increment counter from a catalog node.
     *
     * @param array<string, mixed> $node Category, subcategory, or group node.
     * @param string $defaultLocale Default locale for labels.
     * @param list<string> $sampleLabels Collected sample labels (mutated).
     * @param int $skillCount Running skill count (mutated).
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function collectSkillLabelsFromNode(
        array $node,
        string $defaultLocale,
        array &$sampleLabels,
        int &$skillCount,
    ): void {
        foreach ($node['items'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }

            ++$skillCount;
            $label = $this->resolveSkillLabel($item, $defaultLocale);
            if ($label !== '') {
                $sampleLabels[] = $label;
            }
        }
    }

    /**
     * @brief Resolve display label for a skill item row.
     *
     * @param array<string, mixed> $item Skill item row.
     * @param string $defaultLocale Default locale code.
     * @return string
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function resolveSkillLabel(array $item, string $defaultLocale): string
    {
        $labelMode = is_string($item['labelMode'] ?? null) ? (string) $item['labelMode'] : SkillsTreeContract::LABEL_MODE_LOCALIZED;
        if ($labelMode === SkillsTreeContract::LABEL_MODE_CANONICAL) {
            return is_string($item['canonicalLabel'] ?? null) ? trim((string) $item['canonicalLabel']) : '';
        }

        $labels = is_array($item['labelsByLocale'] ?? null) ? $item['labelsByLocale'] : [];
        $label = is_string($labels[$defaultLocale] ?? null) ? trim((string) $labels[$defaultLocale]) : '';
        if ($label !== '') {
            return $label;
        }

        foreach ($labels as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return is_string($item['canonicalLabel'] ?? null) ? trim((string) $item['canonicalLabel']) : '';
    }

    /**
     * @brief Build company-scoped skills catalog AJAX route map.
     *
     * @param int $companyId Tracked company id.
     * @return array{categorySave: string, categoryDelete: string, categoryMove: string, skillSave: string, skillDelete: string}
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function buildCompanySkillsRoutes(int $companyId): array
    {
        return [
            'categorySave' => $this->urlGenerator->generate(
                'admin_employment_companies_cv_skills_catalog_category_save',
                ['id' => $companyId],
            ),
            'categoryDelete' => $this->urlGenerator->generate(
                'admin_employment_companies_cv_skills_catalog_category_delete',
                ['id' => $companyId],
            ),
            'categoryMove' => $this->urlGenerator->generate(
                'admin_employment_companies_cv_skills_catalog_category_move',
                ['id' => $companyId],
            ),
            'skillSave' => $this->urlGenerator->generate(
                'admin_employment_companies_cv_skills_catalog_skill_save',
                ['id' => $companyId],
            ),
            'skillDelete' => $this->urlGenerator->generate(
                'admin_employment_companies_cv_skills_catalog_skill_delete',
                ['id' => $companyId],
            ),
        ];
    }
}
