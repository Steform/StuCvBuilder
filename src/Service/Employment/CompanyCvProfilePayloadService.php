<?php

declare(strict_types=1);

namespace App\Service\Employment;

use App\Employment\CompanyCvContentMode;
use App\Entity\CvProfile;
use App\Entity\TrackedCompany;
use App\Repository\CvProfileRepository;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

/**
 * @brief Load and persist content JSON for a company custom CvProfile.
 */
class CompanyCvProfilePayloadService
{
    /**
     * @brief Wire payload access dependencies.
     *
     * @param EntityManagerInterface $entityManager ORM.
     * @param CvProfileRepository $cvProfileRepository Profile repository.
     * @param CompanyCvProfileCloneService $cloneService Clone service.
     * @return void
     * @date 2026-07-23
     * @author Stephane H.
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CvProfileRepository $cvProfileRepository,
        private readonly CompanyCvProfileCloneService $cloneService,
    ) {
    }

    /**
     * @brief Require a custom-mode company and return its CvProfile (auto-clone if missing).
     *
     * @param TrackedCompany $company Tracked company.
     * @return CvProfile
     * @date 2026-07-23
     * @author Stephane H.
     */
    public function requireCustomProfile(TrackedCompany $company): CvProfile
    {
        if (!$company->isCvContentCustom()) {
            throw new RuntimeException('Company CV content mode is not custom.');
        }

        return $this->cloneService->ensureClone($company);
    }

    /**
     * @brief Load decoded payload for a custom company profile.
     *
     * @param TrackedCompany $company Tracked company in custom mode.
     * @return array<string, mixed>
     * @date 2026-07-23
     * @author Stephane H.
     */
    public function loadPayload(TrackedCompany $company): array
    {
        return $this->cloneService->decodePayload($this->requireCustomProfile($company));
    }

    /**
     * @brief Persist sanitized payload on the company custom profile.
     *
     * @param TrackedCompany $company Tracked company in custom mode.
     * @param array<string, mixed> $payload Decoded content JSON.
     * @return void
     * @date 2026-07-23
     * @author Stephane H.
     */
    public function savePayload(TrackedCompany $company, array $payload): void
    {
        $profile = $this->requireCustomProfile($company);
        $this->cloneService->persistPayload($profile, $payload);
    }

    /**
     * @brief Load global profile payload (empty array when missing).
     *
     * @return array<string, mixed>
     * @date 2026-07-23
     * @author Stephane H.
     */
    public function loadGlobalPayload(): array
    {
        $global = $this->cvProfileRepository->findGlobal();
        if ($global === null) {
            return [];
        }

        return $this->cloneService->decodePayload($global);
    }

    /**
     * @brief Resolve which profile content to edit for a company (custom clone or global read-only source).
     *
     * @param TrackedCompany $company Tracked company.
     * @return array{mode: string, profile: CvProfile|null, payload: array<string, mixed>}
     * @date 2026-07-23
     * @author Stephane H.
     */
    public function resolveForCompany(TrackedCompany $company): array
    {
        if ($company->isCvContentCustom()) {
            $profile = $this->requireCustomProfile($company);

            return [
                'mode' => CompanyCvContentMode::CUSTOM,
                'profile' => $profile,
                'payload' => $this->cloneService->decodePayload($profile),
            ];
        }

        $global = $this->cvProfileRepository->findGlobal();

        return [
            'mode' => CompanyCvContentMode::SYNCED,
            'profile' => $global,
            'payload' => $global !== null ? $this->cloneService->decodePayload($global) : [],
        ];
    }
}
