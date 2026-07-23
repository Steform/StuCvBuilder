<?php

declare(strict_types=1);

namespace App\Service\Employment;

use App\Cv\SkillsTreeContract;
use App\Entity\TrackedCompany;
use App\Service\Cv\SkillsCatalogPersistence;

/**
 * @brief Persist skills catalog in the company custom CvProfile content JSON.
 */
final class CompanySkillsCatalogPersistence implements SkillsCatalogPersistence
{
    /**
     * @brief Wire company skills catalog persistence.
     *
     * @param TrackedCompany $company Tracked company (must be in custom mode).
     * @param CompanyCvProfilePayloadService $companyCvProfilePayloadService Company CvProfile payload access.
     * @return void
     * @date 2026-07-23
     * @author Stephane H.
     */
    public function __construct(
        private readonly TrackedCompany $company,
        private readonly CompanyCvProfilePayloadService $companyCvProfilePayloadService,
    ) {
    }

    /**
     * @brief Load company custom CV profile payload.
     *
     * @return array<string, mixed>
     * @date 2026-07-23
     * @author Stephane H.
     */
    public function loadPayloadSlice(): array
    {
        $this->requireCustom();

        return $this->companyCvProfilePayloadService->loadPayload($this->company);
    }

    /**
     * @brief Merge catalog into company custom CvProfile and persist.
     *
     * @param array{categories: list<array<string, mixed>>} $catalog Normalized catalog.
     * @param list<string> $activeLocales Active locale codes.
     * @param string $defaultLocale Site default locale.
     * @return array{categories: list<array<string, mixed>>} Stored catalog.
     * @date 2026-07-23
     * @author Stephane H.
     */
    public function saveCatalog(array $catalog, array $activeLocales, string $defaultLocale): array
    {
        $this->requireCustom();

        $normalized = SkillsTreeContract::normalizeCatalog($catalog, $activeLocales, $defaultLocale);
        if ($normalized === null) {
            throw new \InvalidArgumentException('dashboard.customization_cv.skills.flash_invalid');
        }

        $payload = $this->companyCvProfilePayloadService->loadPayload($this->company);
        $payload = SkillsTreeContract::mergeCatalogIntoPayload($payload, $normalized);
        $this->companyCvProfilePayloadService->savePayload($this->company, $payload);

        return $normalized;
    }

    /**
     * @brief Ensure the company is in custom mode before touching its CV profile.
     *
     * @return void
     * @date 2026-07-23
     * @author Stephane H.
     */
    private function requireCustom(): void
    {
        if (!$this->company->isCvContentCustom()) {
            throw new \InvalidArgumentException('employment.companies.cv_customization.skills.flash.not_enabled');
        }
    }
}
