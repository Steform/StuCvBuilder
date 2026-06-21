<?php

declare(strict_types=1);

namespace App\Service\Employment;

use App\Repository\EmploymentCountryRepository;
use App\Repository\TrackedCompanyRepository;
use App\Service\Locale\LocaleConfigurationService;

/**
 * Resolves CV/site presentation locale from company format or country code.
 */
class EmploymentCountryPresentationLocaleResolver
{
    /**
     * @brief Build presentation locale resolver.
     *
     * @param TrackedCompanyRepository $trackedCompanyRepository Company repository.
     * @param EmploymentCountryRepository $employmentCountryRepository Country repository.
     * @param LocaleConfigurationService $localeConfigurationService Active locale configuration.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function __construct(
        private readonly TrackedCompanyRepository $trackedCompanyRepository,
        private readonly EmploymentCountryRepository $employmentCountryRepository,
        private readonly LocaleConfigurationService $localeConfigurationService,
    ) {
    }

    /**
     * @brief Return active locale codes configured for the site.
     *
     * @param void No input parameter.
     * @return list<string>
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function getActiveLocales(): array
    {
        $config = $this->localeConfigurationService->getConfiguration();
        $active = is_array($config['activeLocales'] ?? null) ? $config['activeLocales'] : [];

        return $active !== [] ? $active : $this->localeConfigurationService->getSupportedLocales();
    }

    /**
     * @brief Check whether locale is currently active on the site.
     *
     * @param string $locale Candidate locale.
     * @return bool
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function isActiveLocale(string $locale): bool
    {
        $normalized = $this->normalizeLocale($locale);

        return $normalized !== null && in_array($normalized, $this->getActiveLocales(), true);
    }

    /**
     * @brief Resolve presentation locale for an active company format code.
     *
     * @param string $formatCode Company format from query or session.
     * @return string|null Active locale or null when not applicable.
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function resolveForFormatCode(string $formatCode): ?string
    {
        $formatCode = trim($formatCode);
        if ($formatCode === '') {
            return null;
        }

        $company = $this->trackedCompanyRepository->findActiveByCode($formatCode);
        if ($company === null) {
            return null;
        }

        return $this->resolveForCountryCode($company->getCountryCode());
    }

    /**
     * @brief Resolve presentation locale for a managed country ISO code.
     *
     * @param string|null $countryCode ISO country on tracked company.
     * @return string|null Active locale or null.
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function resolveForCountryCode(?string $countryCode): ?string
    {
        if ($countryCode === null || trim($countryCode) === '') {
            return null;
        }

        $country = $this->employmentCountryRepository->findOneByCode($countryCode);
        if ($country === null) {
            return null;
        }

        $locale = $this->normalizeLocale($country->getPresentationLocale());
        if ($locale === null || !$this->isActiveLocale($locale)) {
            return null;
        }

        return $locale;
    }

    /**
     * @brief Normalize locale against active site locales.
     *
     * @param string $locale Raw locale.
     * @return string|null Normalized locale when active.
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function normalizeActiveLocale(string $locale): ?string
    {
        $normalized = $this->normalizeLocale($locale);
        if ($normalized === null || !$this->isActiveLocale($normalized)) {
            return null;
        }

        return $normalized;
    }

    /**
     * @brief Normalize raw locale string to two-letter code.
     *
     * @param string $locale Raw locale value.
     * @return string|null
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function normalizeLocale(string $locale): ?string
    {
        $normalized = substr(strtolower(trim(str_replace('_', '-', $locale))), 0, 2);
        if ($normalized === '') {
            return null;
        }

        if (in_array($normalized, ['nb', 'nn'], true)) {
            $normalized = 'no';
        }

        $allowed = $this->localeConfigurationService->getSupportedLocales();

        return in_array($normalized, $allowed, true) ? $normalized : null;
    }
}
