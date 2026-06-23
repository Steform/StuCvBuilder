<?php

declare(strict_types=1);

namespace App\Service\Employment;

use App\Cv\CompanyCvCustomizationSectionKey;
use App\Cv\CompanyCvSkillsOverrideScope;
use App\Cv\SkillsTreeContract;
use App\Entity\CompanyCvSectionOverride;
use App\Entity\TrackedCompany;
use App\Repository\CompanyCvSectionOverrideRepository;
use App\Service\Cv\SkillsCatalogPersistence;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @brief Persist skills catalog in a company CV section override row.
 */
final class CompanySkillsCatalogPersistence implements SkillsCatalogPersistence
{
    /**
     * @brief Wire company skills catalog persistence.
     *
     * @param TrackedCompany $company Tracked company.
     * @param CompanyCvSectionOverrideRepository $overrideRepository Override repository.
     * @param EntityManagerInterface $entityManager ORM.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function __construct(
        private readonly TrackedCompany $company,
        private readonly CompanyCvSectionOverrideRepository $overrideRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @brief Load company Skills override payload slice.
     *
     * @return array<string, mixed>
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function loadPayloadSlice(): array
    {
        $override = $this->requireOverride();

        return CompanyCvSkillsOverrideScope::decodeJson($override->getContentJson());
    }

    /**
     * @brief Merge catalog into company override JSON and flush.
     *
     * @param array{categories: list<array<string, mixed>>} $catalog Normalized catalog.
     * @param list<string> $activeLocales Active locale codes.
     * @param string $defaultLocale Site default locale.
     * @return array{categories: list<array<string, mixed>>}
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function saveCatalog(array $catalog, array $activeLocales, string $defaultLocale): array
    {
        $normalized = SkillsTreeContract::normalizeCatalog($catalog, $activeLocales, $defaultLocale);
        if ($normalized === null) {
            throw new \InvalidArgumentException('dashboard.customization_cv.skills.flash_invalid');
        }

        $override = $this->requireOverride();
        $payload = CompanyCvSkillsOverrideScope::decodeJson($override->getContentJson());
        $payload = SkillsTreeContract::mergeCatalogIntoPayload($payload, $normalized);
        $sanitized = CompanyCvSkillsOverrideScope::sanitizeForPersistence($payload, $activeLocales, $defaultLocale);
        $json = json_encode($sanitized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $override->setContentJson(is_string($json) ? $json : '{}');
        $this->entityManager->flush();

        return $normalized;
    }

    /**
     * @brief Require an existing Skills override row for this company.
     *
     * @return CompanyCvSectionOverride
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function requireOverride(): CompanyCvSectionOverride
    {
        $override = $this->overrideRepository->findOneForCompanySection($this->company, CompanyCvCustomizationSectionKey::SKILLS);
        if ($override === null) {
            throw new \InvalidArgumentException('employment.companies.cv_customization.skills.flash.not_enabled');
        }

        return $override;
    }
}
