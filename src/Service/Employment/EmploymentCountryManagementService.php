<?php

declare(strict_types=1);

namespace App\Service\Employment;

use App\Entity\EmploymentCountry;
use App\Repository\EmploymentCountryRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Admin CRUD for employment country options.
 */
class EmploymentCountryManagementService
{
    /**
     * @brief Build employment country management service.
     *
     * @param EntityManagerInterface $entityManager ORM entity manager.
     * @param EmploymentCountryRepository $employmentCountryRepository Country repository.
     * @param EmploymentCountryPresentationLocaleResolver $presentationLocaleResolver Active locale validation.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EmploymentCountryRepository $employmentCountryRepository,
        private readonly EmploymentCountryPresentationLocaleResolver $presentationLocaleResolver,
    ) {
    }

    /**
     * @brief Create a managed country option.
     *
     * @param string $code Candidate ISO 3166-1 alpha-2 code.
     * @param string $label Display label.
     * @param string $presentationLocale Active site locale for CV/LM presentation.
     * @return array{country: EmploymentCountry|null, error: string|null}
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function create(string $code, string $label, string $presentationLocale): array
    {
        $normalizedCode = $this->normalizeCode($code);
        if ($normalizedCode === null) {
            return ['country' => null, 'error' => 'employment.countries.flash.code_invalid'];
        }

        $label = trim($label);
        if ($label === '') {
            return ['country' => null, 'error' => 'employment.countries.flash.label_required'];
        }

        $normalizedLocale = $this->presentationLocaleResolver->normalizeActiveLocale($presentationLocale);
        if ($normalizedLocale === null) {
            return ['country' => null, 'error' => 'employment.countries.flash.locale_invalid'];
        }

        if ($this->employmentCountryRepository->findOneByCode($normalizedCode) instanceof EmploymentCountry) {
            return ['country' => null, 'error' => 'employment.countries.flash.code_duplicate'];
        }

        $country = new EmploymentCountry($normalizedCode, $label, $normalizedLocale);
        $this->entityManager->persist($country);
        $this->entityManager->flush();

        return ['country' => $country, 'error' => null];
    }

    /**
     * @brief Update country label and presentation locale (code is immutable).
     *
     * @param EmploymentCountry $country Country entity.
     * @param string $label New display label.
     * @param string $presentationLocale Active site locale for CV/LM presentation.
     * @return string|null Error translation key or null on success.
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function update(EmploymentCountry $country, string $label, string $presentationLocale): ?string
    {
        $label = trim($label);
        if ($label === '') {
            return 'employment.countries.flash.label_required';
        }

        $normalizedLocale = $this->presentationLocaleResolver->normalizeActiveLocale($presentationLocale);
        if ($normalizedLocale === null) {
            return 'employment.countries.flash.locale_invalid';
        }

        $country->setLabel($label);
        $country->setPresentationLocale($normalizedLocale);
        $this->entityManager->flush();

        return null;
    }

    /**
     * @brief Normalize and validate ISO alpha-2 code.
     *
     * @param string $code Raw code input.
     * @return string|null Uppercase code or null when invalid.
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function normalizeCode(string $code): ?string
    {
        $upper = strtoupper(trim($code));
        if ($upper === '' || !preg_match('/^[A-Z]{2}$/', $upper)) {
            return null;
        }

        return $upper;
    }
}
