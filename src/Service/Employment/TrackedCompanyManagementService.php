<?php

declare(strict_types=1);

namespace App\Service\Employment;

use App\Entity\EmploymentDocumentVariant;
use App\Entity\TrackedCompany;
use App\Employment\EmploymentDocumentKind;
use App\Repository\EmploymentDocumentVariantRepository;
use App\Repository\TrackedCompanyRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Admin CRUD for tracked companies.
 */
class TrackedCompanyManagementService
{
    private const MAX_RECRUITER_NAME_LENGTH = 255;

    private const MAX_ADDRESS_LINE_LENGTH = 255;

    private const MAX_ADDRESS_POSTAL_CODE_LENGTH = 32;

    private const MAX_ADDRESS_CITY_LENGTH = 128;

    private const MAX_PHONE_LENGTH = 64;

    private const MAX_EMAIL_LENGTH = 255;

    /**
     * @brief Build tracked company management service.
     *
     * @param EntityManagerInterface $entityManager ORM entity manager.
     * @param TrackedCompanyRepository $trackedCompanyRepository Company repository.
     * @param CompanyCodeGenerator $companyCodeGenerator Code generator.
     * @param EmploymentCountryList $employmentCountryList Allowed countries.
     * @param EmploymentDocumentVariantRepository $documentVariantRepository Document variant repository.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TrackedCompanyRepository $trackedCompanyRepository,
        private readonly CompanyCodeGenerator $companyCodeGenerator,
        private readonly EmploymentCountryList $employmentCountryList,
        private readonly EmploymentDocumentVariantRepository $documentVariantRepository,
    ) {
    }

    /**
     * @brief Create a tracked company.
     *
     * @param string $name Display name.
     * @param string|null $countryCode Optional ISO country.
     * @param TrackedCompanyContactInput $contact Optional recruiter contact fields.
     * @param TrackedCompanyDocumentInput $documents Optional CV / LM variant ids.
     * @return array{company: TrackedCompany|null, error: string|null}
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function create(
        string $name,
        ?string $countryCode,
        TrackedCompanyContactInput $contact,
        TrackedCompanyDocumentInput $documents,
    ): array {
        $name = trim($name);
        if ($name === '') {
            return ['company' => null, 'error' => 'employment.companies.flash.name_required'];
        }

        $normalizedCountry = $this->normalizeCountry($countryCode);
        if ($countryCode !== null && trim($countryCode) !== '' && $normalizedCountry === null) {
            return ['company' => null, 'error' => 'employment.companies.flash.country_invalid'];
        }

        $contactError = $this->validateContact($contact);
        if ($contactError !== null) {
            return ['company' => null, 'error' => $contactError];
        }

        $documentError = $this->resolveDocumentVariants($documents);
        if (is_string($documentError)) {
            return ['company' => null, 'error' => $documentError];
        }

        $code = $this->companyCodeGenerator->generate();
        $company = new TrackedCompany($code, $name, $normalizedCountry);
        $this->applyContactDetails($company, $contact);
        $company->setDocumentVariants($documentError['cv'], $documentError['lm']);
        $this->entityManager->persist($company);
        $this->entityManager->flush();

        return ['company' => $company, 'error' => null];
    }

    /**
     * @brief Update company fields including optional recruiter contact.
     *
     * @param TrackedCompany $company Company entity.
     * @param string $name New name.
     * @param string|null $countryCode New country or empty.
     * @param TrackedCompanyContactInput $contact Optional recruiter contact fields.
     * @param TrackedCompanyDocumentInput $documents Optional CV / LM variant ids.
     * @return string|null Error translation key or null on success.
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function update(
        TrackedCompany $company,
        string $name,
        ?string $countryCode,
        TrackedCompanyContactInput $contact,
        TrackedCompanyDocumentInput $documents,
    ): ?string {
        $name = trim($name);
        if ($name === '') {
            return 'employment.companies.flash.name_required';
        }

        $normalizedCountry = $this->normalizeCountry($countryCode);
        if ($countryCode !== null && trim($countryCode) !== '' && $normalizedCountry === null) {
            return 'employment.companies.flash.country_invalid';
        }

        $contactError = $this->validateContact($contact);
        if ($contactError !== null) {
            return $contactError;
        }

        $documentError = $this->resolveDocumentVariants($documents);
        if (is_string($documentError)) {
            return $documentError;
        }

        $company->setName($name);
        $company->setCountryCode($normalizedCountry);
        $this->applyContactDetails($company, $contact);
        $company->setDocumentVariants($documentError['cv'], $documentError['lm']);
        $this->entityManager->flush();

        return null;
    }

    /**
     * @brief Archive a company.
     *
     * @param TrackedCompany $company Company entity.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function archive(TrackedCompany $company): void
    {
        if ($company->isArchived()) {
            return;
        }

        $company->archive(new DateTimeImmutable());
        $this->entityManager->flush();
    }

    /**
     * @brief Restore an archived company.
     *
     * @param TrackedCompany $company Company entity.
     * @return void
     * @date 2026-06-17
     * @author Stephane H.
     */
    public function unarchive(TrackedCompany $company): void
    {
        if (!$company->isArchived()) {
            return;
        }

        $company->unarchive();
        $this->entityManager->flush();
    }

    /**
     * @brief Delete a tracked company permanently.
     *
     * @param TrackedCompany $company Company entity.
     * @return void
     * @date 2026-06-17
     * @author Stephane H.
     */
    public function delete(TrackedCompany $company): void
    {
        $this->entityManager->remove($company);
        $this->entityManager->flush();
    }

    /**
     * @brief Normalize search query for repository.
     *
     * @param string $query Raw search input.
     * @return string
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function normalizeSearchQuery(string $query): string
    {
        $trimmed = trim($query);

        return function_exists('mb_strtolower') ? mb_strtolower($trimmed, 'UTF-8') : strtolower($trimmed);
    }

    /**
     * @brief Resolve optional CV and LM variants from admin input.
     *
     * @param TrackedCompanyDocumentInput $documents Variant id input.
     * @return array{cv: EmploymentDocumentVariant|null, lm: EmploymentDocumentVariant|null}|string Error translation key.
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function resolveDocumentVariants(TrackedCompanyDocumentInput $documents): array|string
    {
        $cv = null;
        if ($documents->cvDocumentVariantId !== null) {
            $cv = $this->documentVariantRepository->findActiveByIdAndKind(
                $documents->cvDocumentVariantId,
                EmploymentDocumentKind::CV,
            );
            if (!$cv instanceof EmploymentDocumentVariant) {
                return 'employment.companies.flash.cv_document_invalid';
            }
        }

        $lm = null;
        if ($documents->lmDocumentVariantId !== null) {
            $lm = $this->documentVariantRepository->findActiveByIdAndKind(
                $documents->lmDocumentVariantId,
                EmploymentDocumentKind::LM,
            );
            if (!$lm instanceof EmploymentDocumentVariant) {
                return 'employment.companies.flash.lm_document_invalid';
            }
        }

        return ['cv' => $cv, 'lm' => $lm];
    }

    /**
     * @brief Validate optional contact input lengths and email format.
     *
     * @param TrackedCompanyContactInput $contact Contact input.
     * @return string|null Error translation key or null when valid.
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function validateContact(TrackedCompanyContactInput $contact): ?string
    {
        if ($this->exceedsMaxLength($contact->recruiterName, self::MAX_RECRUITER_NAME_LENGTH)) {
            return 'employment.companies.flash.recruiter_name_too_long';
        }

        if ($this->exceedsMaxLength($contact->addressLine1, self::MAX_ADDRESS_LINE_LENGTH)) {
            return 'employment.companies.flash.address_line1_too_long';
        }

        if ($this->exceedsMaxLength($contact->addressLine2, self::MAX_ADDRESS_LINE_LENGTH)) {
            return 'employment.companies.flash.address_line2_too_long';
        }

        if ($this->exceedsMaxLength($contact->addressPostalCode, self::MAX_ADDRESS_POSTAL_CODE_LENGTH)) {
            return 'employment.companies.flash.address_postal_code_too_long';
        }

        if ($this->exceedsMaxLength($contact->addressCity, self::MAX_ADDRESS_CITY_LENGTH)) {
            return 'employment.companies.flash.address_city_too_long';
        }

        if ($this->exceedsMaxLength($contact->phone, self::MAX_PHONE_LENGTH)) {
            return 'employment.companies.flash.phone_too_long';
        }

        $email = trim((string) $contact->email);
        if ($email === '') {
            return null;
        }

        if (strlen($email) > self::MAX_EMAIL_LENGTH) {
            return 'employment.companies.flash.email_too_long';
        }

        if (!$this->isValidEmail($email)) {
            return 'employment.companies.flash.email_invalid';
        }

        return null;
    }

    /**
     * @brief Normalize and assign contact fields on company entity.
     *
     * @param TrackedCompany $company Target company.
     * @param TrackedCompanyContactInput $contact Raw contact input.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function applyContactDetails(TrackedCompany $company, TrackedCompanyContactInput $contact): void
    {
        $company->setContactDetails(
            $this->normalizeOptionalString($contact->recruiterName, self::MAX_RECRUITER_NAME_LENGTH),
            $this->normalizeOptionalString($contact->addressLine1, self::MAX_ADDRESS_LINE_LENGTH),
            $this->normalizeOptionalString($contact->addressLine2, self::MAX_ADDRESS_LINE_LENGTH),
            $this->normalizeOptionalString($contact->addressPostalCode, self::MAX_ADDRESS_POSTAL_CODE_LENGTH),
            $this->normalizeOptionalString($contact->addressCity, self::MAX_ADDRESS_CITY_LENGTH),
            $this->normalizeOptionalString($contact->phone, self::MAX_PHONE_LENGTH),
            $this->normalizeOptionalEmail($contact->email),
        );
    }

    /**
     * @brief Validate and normalize country code.
     *
     * @param string|null $countryCode Raw country.
     * @return string|null ISO code or null when empty.
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function normalizeCountry(?string $countryCode): ?string
    {
        if ($countryCode === null || trim($countryCode) === '') {
            return null;
        }

        $upper = strtoupper(trim($countryCode));
        if (!$this->employmentCountryList->isAllowed($upper)) {
            return null;
        }

        return $upper;
    }

    /**
     * @brief Trim string and return null when empty; enforce max length.
     *
     * @param string|null $value Raw value.
     * @param int $maxLength Maximum length.
     * @return string|null
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function normalizeOptionalString(?string $value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        if (strlen($trimmed) > $maxLength) {
            return substr($trimmed, 0, $maxLength);
        }

        return $trimmed;
    }

    /**
     * @brief Normalize optional email or return null when empty.
     *
     * @param string|null $email Raw email.
     * @return string|null
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function normalizeOptionalEmail(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $trimmed = trim($email);
        if ($trimmed === '') {
            return null;
        }

        return strtolower($trimmed);
    }

    /**
     * @brief Check whether raw value exceeds max length before normalization.
     *
     * @param string|null $value Raw value.
     * @param int $maxLength Maximum allowed length.
     * @return bool
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function exceedsMaxLength(?string $value, int $maxLength): bool
    {
        return $value !== null && strlen(trim($value)) > $maxLength;
    }

    /**
     * @brief Validate email address format.
     *
     * @param string $email Email candidate.
     * @return bool
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
