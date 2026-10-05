<?php

declare(strict_types=1);

namespace App\Service\Cv;

use App\Entity\CvProfile;
use App\Repository\CvProfileRepository;
use App\Repository\TrackedCompanyRepository;

/**
 * @brief Resolve which CvProfile content JSON powers About dynamic CSS (global or company custom).
 */
final class CvAboutStylesheetProfileResolver
{
    /**
     * @brief Wire profile lookup dependencies.
     *
     * @param CvProfileRepository $cvProfileRepository Profile repository.
     * @param TrackedCompanyRepository $trackedCompanyRepository Company repository.
     * @return void
     * @date 2026-10-05
     * @author Stephane H.
     */
    public function __construct(
        private readonly CvProfileRepository $cvProfileRepository,
        private readonly TrackedCompanyRepository $trackedCompanyRepository,
    ) {
    }

    /**
     * @brief Resolve content JSON for About CSS, optionally scoped to a company code.
     *
     * When `$companyCode` matches an active company in custom mode, returns that clone's JSON.
     * Otherwise returns the global profile JSON (or `{}` when missing).
     *
     * @param string|null $companyCode Optional tracked company code from query.
     * @return string Raw content JSON.
     * @date 2026-10-05
     * @author Stephane H.
     */
    public function resolveContentJson(?string $companyCode): string
    {
        $normalizedCode = is_string($companyCode) ? trim($companyCode) : '';
        if ($normalizedCode !== '' && $normalizedCode !== 'default') {
            $company = $this->trackedCompanyRepository->findActiveByCode($normalizedCode);
            if ($company !== null && $company->isCvContentCustom()) {
                $companyProfile = $this->cvProfileRepository->findOneForCompany($company);
                if ($companyProfile instanceof CvProfile) {
                    return $companyProfile->getContentJson();
                }
            }
        }

        $global = $this->cvProfileRepository->findGlobal();

        return $global instanceof CvProfile ? $global->getContentJson() : '{}';
    }
}
