<?php

declare(strict_types=1);

namespace App\Service\Employment;

use App\Entity\EmploymentCountry;
use App\Repository\EmploymentCountryRepository;

/**
 * Provides admin-managed employment country options from the database.
 */
class EmploymentCountryList
{
    /**
     * @brief Build employment country list service.
     *
     * @param EmploymentCountryRepository $employmentCountryRepository Country repository.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function __construct(
        private readonly EmploymentCountryRepository $employmentCountryRepository,
    ) {
    }

    /**
     * @brief Return supported country codes.
     *
     * @return list<string>
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function getCountryCodes(): array
    {
        return array_map(
            static fn (EmploymentCountry $country): string => $country->getCode(),
            $this->employmentCountryRepository->findAllOrderedByLabel(),
        );
    }

    /**
     * @brief Return countries for admin UI selects.
     *
     * @return list<EmploymentCountry>
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function getCountries(): array
    {
        return $this->employmentCountryRepository->findAllOrderedByLabel();
    }

    /**
     * @brief Map country code to admin label.
     *
     * @return array<string, string>
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function getLabelsByCode(): array
    {
        $map = [];
        foreach ($this->getCountries() as $country) {
            $map[$country->getCode()] = $country->getLabel();
        }

        return $map;
    }

    /**
     * @brief Check whether country code exists in managed list.
     *
     * @param string $countryCode Candidate ISO code.
     * @return bool
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function isAllowed(string $countryCode): bool
    {
        return $this->employmentCountryRepository->findOneByCode($countryCode) instanceof EmploymentCountry;
    }

    /**
     * @brief Resolve display label for a managed country code.
     *
     * @param string|null $countryCode ISO code or null.
     * @return string|null Label when found.
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function getLabel(?string $countryCode): ?string
    {
        if ($countryCode === null || trim($countryCode) === '') {
            return null;
        }

        return $this->employmentCountryRepository->findOneByCode($countryCode)?->getLabel();
    }
}
