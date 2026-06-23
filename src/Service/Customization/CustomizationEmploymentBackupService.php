<?php

declare(strict_types=1);

namespace App\Service\Customization;

use App\Cv\CompanyCvCustomizationSectionKey;
use App\Entity\CompanyCvSectionOverride;
use App\Entity\CompanyCvVisit;
use App\Entity\CvConnectionLog;
use App\Entity\EmploymentCountry;
use App\Entity\EmploymentDocumentLocaleAsset;
use App\Entity\EmploymentDocumentVariant;
use App\Entity\EmploymentPrintPlacement;
use App\Entity\TrackedCompany;
use App\Exception\Customization\CustomizationBackupException;
use App\Repository\CompanyCvSectionOverrideRepository;
use App\Repository\CompanyCvVisitRepository;
use App\Repository\CvConnectionLogRepository;
use App\Repository\EmploymentCountryRepository;
use App\Repository\EmploymentDocumentVariantRepository;
use App\Repository\EmploymentPrintPlacementRepository;
use App\Repository\TrackedCompanyRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @brief Export and restore employment module data (countries, documents, companies, CV overrides, visits, logs).
 */
final class CustomizationEmploymentBackupService
{
    private const EMPLOYMENT_STORAGE_ROOT = 'var/employment_documents';

    /** @var array<string, string> */
    private array $pendingStoragePathRemap = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EmploymentCountryRepository $employmentCountryRepository,
        private readonly EmploymentPrintPlacementRepository $employmentPrintPlacementRepository,
        private readonly EmploymentDocumentVariantRepository $employmentDocumentVariantRepository,
        private readonly TrackedCompanyRepository $trackedCompanyRepository,
        private readonly CompanyCvSectionOverrideRepository $companyCvSectionOverrideRepository,
        private readonly CompanyCvVisitRepository $companyCvVisitRepository,
        private readonly CvConnectionLogRepository $cvConnectionLogRepository,
        private readonly string $projectDir,
    ) {
    }

    /**
     * @brief Collect decoded override JSON payloads for public asset path scanning during export.
     *
     * @return list<array<string, mixed>>
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function collectSectionOverrideContentPayloadsForExport(): array
    {
        $payloads = [];
        foreach ($this->companyCvSectionOverrideRepository->findBy([], ['id' => 'ASC']) as $override) {
            $decoded = json_decode($override->getContentJson(), true);
            if (is_array($decoded)) {
                $payloads[] = $decoded;
            }
        }

        return $payloads;
    }

    /**
     * @brief Build JSON entry map for employment tables (format version 2).
     *
     * @return array<string, string> Map of archive path to JSON bytes.
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function buildJsonEntries(): array
    {
        return [
            CustomizationBackupPaths::DATA_EMPLOYMENT_COUNTRIES => $this->encodeJson($this->serializeCountries()),
            CustomizationBackupPaths::DATA_EMPLOYMENT_PRINT_PLACEMENTS => $this->encodeJson($this->serializePrintPlacements()),
            CustomizationBackupPaths::DATA_EMPLOYMENT_DOCUMENT_VARIANTS => $this->encodeJson($this->serializeDocumentVariants()),
            CustomizationBackupPaths::DATA_TRACKED_COMPANIES => $this->encodeJson($this->serializeTrackedCompanies()),
            CustomizationBackupPaths::DATA_COMPANY_CV_SECTION_OVERRIDES => $this->encodeJson($this->serializeCompanyCvSectionOverrides()),
            CustomizationBackupPaths::DATA_COMPANY_CV_VISITS => $this->encodeJson($this->serializeCompanyVisits()),
            CustomizationBackupPaths::DATA_CV_CONNECTION_LOGS => $this->encodeJson($this->serializeConnectionLogs()),
        ];
    }

    /**
     * @brief Collect relative project paths for employment document files referenced in the database.
     *
     * @return list<string> Paths relative to project root (e.g. var/employment_documents/...).
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function collectStorageFilePaths(): array
    {
        $paths = [];
        foreach ($this->employmentDocumentVariantRepository->findAll() as $variant) {
            foreach ($variant->getLocaleAssets() as $asset) {
                foreach ([$asset->getTemplateStoragePath(), $asset->getPdfStoragePath()] as $path) {
                    if (is_string($path) && $path !== '' && $this->isSafeEmploymentStoragePath($path)) {
                        $paths[$path] = true;
                    }
                }
            }
        }

        return array_keys($paths);
    }

    /**
     * @brief Whether the archive contains employment JSON payloads (format version 2).
     *
     * @param array<string, string> $entryContents Extracted ZIP entries.
     * @return bool True when all employment data files are present.
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function hasEmploymentPayload(array $entryContents): bool
    {
        foreach (CustomizationBackupPaths::employmentDataPaths() as $path) {
            if (!isset($entryContents[$path])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @brief Replace employment database rows from backup JSON (call inside a transaction).
     *
     * @param array<string, string> $entryContents Extracted ZIP entries.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function restoreDatabaseFromArchiveEntries(array $entryContents): void
    {
        if (!$this->hasEmploymentPayload($entryContents)) {
            $this->pendingStoragePathRemap = [];

            return;
        }

        $countries = $this->decodeJsonList($entryContents, CustomizationBackupPaths::DATA_EMPLOYMENT_COUNTRIES);
        $placements = $this->decodeJsonList($entryContents, CustomizationBackupPaths::DATA_EMPLOYMENT_PRINT_PLACEMENTS);
        $variants = $this->decodeJsonList($entryContents, CustomizationBackupPaths::DATA_EMPLOYMENT_DOCUMENT_VARIANTS);
        $companies = $this->decodeJsonList($entryContents, CustomizationBackupPaths::DATA_TRACKED_COMPANIES);
        $visits = $this->decodeJsonList($entryContents, CustomizationBackupPaths::DATA_COMPANY_CV_VISITS);
        $logs = $this->decodeJsonList($entryContents, CustomizationBackupPaths::DATA_CV_CONNECTION_LOGS);

        $this->wipeEmploymentDatabase();
        $this->entityManager->flush();

        $variantByExportKey = $this->restoreDocumentVariants($variants);
        $this->restorePrintPlacements($placements);
        $this->restoreCountries($countries);
        $companyByCode = $this->restoreTrackedCompanies($companies, $variantByExportKey);
        $overrides = $this->decodeOptionalJsonList(
            $entryContents,
            CustomizationBackupPaths::DATA_COMPANY_CV_SECTION_OVERRIDES,
        );
        $this->restoreCompanyCvSectionOverrides($overrides, $companyByCode);
        $visitByKey = $this->restoreCompanyVisits($visits, $companyByCode);
        $this->restoreConnectionLogs($logs, $companyByCode, $visitByKey);

        $this->pendingStoragePathRemap = $this->buildPathRemapFromVariants($variants, $variantByExportKey);
    }

    /**
     * @brief Copy employment document files from the archive after the database transaction commits.
     *
     * @param array<string, string> $entryContents Extracted ZIP entries.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function restoreStorageFilesFromArchiveEntries(array $entryContents): void
    {
        if (!$this->hasEmploymentPayload($entryContents)) {
            return;
        }

        $this->replaceEmploymentStorageTree($entryContents, $this->pendingStoragePathRemap);
        $this->pendingStoragePathRemap = [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function serializeCountries(): array
    {
        $rows = [];
        foreach ($this->employmentCountryRepository->findBy([], ['code' => 'ASC']) as $country) {
            $rows[] = [
                'code' => $country->getCode(),
                'label' => $country->getLabel(),
                'presentationLocale' => $country->getPresentationLocale(),
                'createdAt' => $country->getCreatedAt()->format(DateTimeImmutable::ATOM),
                'updatedAt' => $country->getUpdatedAt()->format(DateTimeImmutable::ATOM),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function serializePrintPlacements(): array
    {
        $rows = [];
        foreach ($this->employmentPrintPlacementRepository->findAll() as $placement) {
            $rows[] = [
                'kind' => $placement->getKind(),
                'linkX' => $placement->getLinkX(),
                'linkY' => $placement->getLinkY(),
                'squareSizeCm' => $placement->getSquareSizeCm(),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function serializeDocumentVariants(): array
    {
        $rows = [];
        foreach ($this->employmentDocumentVariantRepository->findBy([], ['kind' => 'ASC', 'nameNormalized' => 'ASC']) as $variant) {
            $localeAssets = [];
            foreach ($variant->getLocaleAssets() as $asset) {
                $localeAssets[] = [
                    'locale' => $asset->getLocale(),
                    'templateStoragePath' => $asset->getTemplateStoragePath(),
                    'templateOriginalFilename' => $asset->getTemplateOriginalFilename(),
                    'pdfStoragePath' => $asset->getPdfStoragePath(),
                    'pdfOriginalFilename' => $asset->getPdfOriginalFilename(),
                ];
            }

            $rows[] = [
                'exportKey' => self::variantExportKey($variant->getKind(), $variant->getNameNormalized()),
                'legacyId' => $variant->getId(),
                'kind' => $variant->getKind(),
                'name' => $variant->getName(),
                'createdAt' => $variant->getCreatedAt()->format(DateTimeImmutable::ATOM),
                'updatedAt' => $variant->getUpdatedAt()->format(DateTimeImmutable::ATOM),
                'archivedAt' => $variant->getArchivedAt()?->format(DateTimeImmutable::ATOM),
                'linkX' => $variant->getLinkX(),
                'linkY' => $variant->getLinkY(),
                'squareSizeCm' => $variant->getSquareSizeCm(),
                'isDefault' => $variant->isDefault(),
                'localeAssets' => $localeAssets,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function serializeTrackedCompanies(): array
    {
        $rows = [];
        foreach ($this->trackedCompanyRepository->findBy([], ['code' => 'ASC']) as $company) {
            $cvVariant = $company->getCvDocumentVariant();
            $lmVariant = $company->getLmDocumentVariant();
            $rows[] = [
                'code' => $company->getCode(),
                'name' => $company->getName(),
                'countryCode' => $company->getCountryCode(),
                'createdAt' => $company->getCreatedAt()->format(DateTimeImmutable::ATOM),
                'updatedAt' => $company->getUpdatedAt()->format(DateTimeImmutable::ATOM),
                'archivedAt' => $company->getArchivedAt()?->format(DateTimeImmutable::ATOM),
                'recruiterName' => $company->getRecruiterName(),
                'addressLine1' => $company->getAddressLine1(),
                'addressLine2' => $company->getAddressLine2(),
                'addressPostalCode' => $company->getAddressPostalCode(),
                'addressCity' => $company->getAddressCity(),
                'phone' => $company->getPhone(),
                'email' => $company->getEmail(),
                'cvVariantExportKey' => $cvVariant !== null
                    ? self::variantExportKey($cvVariant->getKind(), $cvVariant->getNameNormalized())
                    : null,
                'lmVariantExportKey' => $lmVariant !== null
                    ? self::variantExportKey($lmVariant->getKind(), $lmVariant->getNameNormalized())
                    : null,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function serializeCompanyCvSectionOverrides(): array
    {
        $rows = [];
        foreach ($this->companyCvSectionOverrideRepository->findBy([], ['id' => 'ASC']) as $override) {
            $rows[] = [
                'companyCode' => $override->getTrackedCompany()->getCode(),
                'sectionKey' => $override->getSectionKey(),
                'contentJson' => $override->getContentJson(),
                'updatedAt' => $override->getUpdatedAt()->format(DateTimeImmutable::ATOM),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function serializeCompanyVisits(): array
    {
        $rows = [];
        foreach ($this->companyCvVisitRepository->findBy([], ['visitDate' => 'DESC', 'id' => 'ASC']) as $visit) {
            $rows[] = [
                'companyCode' => $visit->getCompany()->getCode(),
                'visitDate' => $visit->getVisitDate()->format('Y-m-d'),
                'visitorKey' => $visit->getVisitorKey(),
                'startedAt' => $visit->getStartedAt()->format(DateTimeImmutable::ATOM),
                'lastActivityAt' => $visit->getLastActivityAt()->format(DateTimeImmutable::ATOM),
                'journeyJson' => $visit->getJourneyJson(),
                'ipAddress' => $visit->getIpAddress(),
                'countryCode' => $visit->getCountryCode(),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function serializeConnectionLogs(): array
    {
        $rows = [];
        foreach ($this->cvConnectionLogRepository->findBy([], ['occurredAt' => 'ASC', 'id' => 'ASC']) as $log) {
            $company = $log->getCompany();
            $visit = $log->getVisit();
            $visitKey = null;
            if ($visit !== null) {
                $visitKey = [
                    'companyCode' => $visit->getCompany()->getCode(),
                    'visitDate' => $visit->getVisitDate()->format('Y-m-d'),
                    'visitorKey' => $visit->getVisitorKey(),
                ];
            }

            $rows[] = [
                'occurredAt' => $log->getOccurredAt()->format(DateTimeImmutable::ATOM),
                'connectionKind' => $log->getConnectionKind(),
                'formatRaw' => $log->getFormatRaw(),
                'companyCode' => $company?->getCode(),
                'companyCodeSnapshot' => $log->getCompanyCodeSnapshot(),
                'companyNameSnapshot' => $log->getCompanyNameSnapshot(),
                'visitKey' => $visitKey,
                'ipAddress' => $log->getIpAddress(),
                'countryCode' => $log->getCountryCode(),
                'userAgent' => $log->getUserAgent(),
                'gatePassed' => $log->isGatePassed(),
                'attestationMethod' => $log->getAttestationMethod(),
                'technicalScore' => $log->getTechnicalScore(),
                'countableForCompany' => $log->isCountableForCompany(),
                'isAdminBypass' => $log->isAdminBypass(),
                'requestPath' => $log->getRequestPath(),
                'requestRoute' => $log->getRequestRoute(),
            ];
        }

        return $rows;
    }

    private function wipeEmploymentDatabase(): void
    {
        $this->entityManager->createQuery('DELETE FROM App\Entity\CvConnectionLog')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\CompanyRecruiterVisitNotification')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\CompanyCvVisit')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\TrackedCompany')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\EmploymentDocumentVariant')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\EmploymentCountry')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\EmploymentPrintPlacement')->execute();
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return array<string, EmploymentDocumentVariant>
     */
    private function restoreDocumentVariants(array $rows): array
    {
        /** @var array<string, EmploymentDocumentVariant> $byExportKey */
        $byExportKey = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $kind = isset($row['kind']) && is_string($row['kind']) ? trim($row['kind']) : '';
            $name = isset($row['name']) && is_string($row['name']) ? trim($row['name']) : '';
            if ($kind === '' || $name === '') {
                continue;
            }

            $variant = new EmploymentDocumentVariant($kind, $name);
            $variant->setPlacement(
                $this->normalizeLinkCm($row['linkX'] ?? null, '2.50'),
                $this->normalizeLinkCm($row['linkY'] ?? null, '2.50'),
                isset($row['squareSizeCm']) && is_string($row['squareSizeCm']) ? $row['squareSizeCm'] : '2.00',
            );
            $variant->setIsDefault(($row['isDefault'] ?? false) === true);

            $archivedAt = $this->parseOptionalDateTime($row['archivedAt'] ?? null);
            if ($archivedAt !== null) {
                $variant->archive();
            }

            $legacyId = isset($row['legacyId']) && is_numeric($row['legacyId']) ? (int) $row['legacyId'] : 0;
            $exportKey = isset($row['exportKey']) && is_string($row['exportKey']) && $row['exportKey'] !== ''
                ? $row['exportKey']
                : self::variantExportKey($kind, $variant->getNameNormalized());

            $this->entityManager->persist($variant);
            $this->entityManager->flush();

            $localeAssets = is_array($row['localeAssets'] ?? null) ? $row['localeAssets'] : [];
            foreach ($localeAssets as $assetRow) {
                if (!is_array($assetRow)) {
                    continue;
                }

                $locale = isset($assetRow['locale']) && is_string($assetRow['locale']) ? trim($assetRow['locale']) : '';
                if ($locale === '') {
                    continue;
                }

                $asset = new EmploymentDocumentLocaleAsset($variant, $locale);
                $newId = (int) $variant->getId();
                $templatePath = $this->remapStoragePathForVariant(
                    $this->nullableString($assetRow['templateStoragePath'] ?? null),
                    $kind,
                    $legacyId,
                    $newId,
                );
                $pdfPath = $this->remapStoragePathForVariant(
                    $this->nullableString($assetRow['pdfStoragePath'] ?? null),
                    $kind,
                    $legacyId,
                    $newId,
                );
                if ($templatePath !== null) {
                    $asset->setTemplateFile(
                        $templatePath,
                        $this->nullableString($assetRow['templateOriginalFilename'] ?? null) ?? 'template',
                    );
                }
                if ($pdfPath !== null) {
                    $asset->setPdfFile(
                        $pdfPath,
                        $this->nullableString($assetRow['pdfOriginalFilename'] ?? null) ?? 'document.pdf',
                    );
                }

                $variant->getLocaleAssets()->add($asset);
                $this->entityManager->persist($asset);
            }

            $byExportKey[$exportKey] = $variant;
        }

        return $byExportKey;
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function restorePrintPlacements(array $rows): void
    {
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $kind = isset($row['kind']) && is_string($row['kind']) ? trim($row['kind']) : '';
            if ($kind === '') {
                continue;
            }

            $placement = new EmploymentPrintPlacement(
                $kind,
                $this->normalizeLinkCm($row['linkX'] ?? null, '2.50'),
                $this->normalizeLinkCm($row['linkY'] ?? null, '2.50'),
                isset($row['squareSizeCm']) && is_string($row['squareSizeCm']) ? $row['squareSizeCm'] : '2.00',
            );
            $this->entityManager->persist($placement);
        }
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function restoreCountries(array $rows): void
    {
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $code = isset($row['code']) && is_string($row['code']) ? trim($row['code']) : '';
            $label = isset($row['label']) && is_string($row['label']) ? trim($row['label']) : '';
            $presentationLocale = isset($row['presentationLocale']) && is_string($row['presentationLocale'])
                ? trim($row['presentationLocale'])
                : 'fr';
            if ($code === '' || $label === '') {
                continue;
            }

            $country = new EmploymentCountry($code, $label, $presentationLocale);
            $this->entityManager->persist($country);
        }
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param array<string, EmploymentDocumentVariant> $variantByExportKey
     *
     * @return array<string, TrackedCompany>
     */
    private function restoreTrackedCompanies(array $rows, array $variantByExportKey): array
    {
        /** @var array<string, TrackedCompany> $byCode */
        $byCode = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $code = isset($row['code']) && is_string($row['code']) ? trim($row['code']) : '';
            $name = isset($row['name']) && is_string($row['name']) ? trim($row['name']) : '';
            if ($code === '' || $name === '') {
                continue;
            }

            $countryCode = $this->nullableString($row['countryCode'] ?? null);
            $company = new TrackedCompany($code, $name, $countryCode);
            $company->setContactDetails(
                $this->nullableString($row['recruiterName'] ?? null),
                $this->nullableString($row['addressLine1'] ?? null),
                $this->nullableString($row['addressLine2'] ?? null),
                $this->nullableString($row['addressPostalCode'] ?? null),
                $this->nullableString($row['addressCity'] ?? null),
                $this->nullableString($row['phone'] ?? null),
                $this->nullableString($row['email'] ?? null),
            );

            $cvKey = $this->nullableString($row['cvVariantExportKey'] ?? null);
            $lmKey = $this->nullableString($row['lmVariantExportKey'] ?? null);
            $company->setDocumentVariants(
                $cvKey !== null && isset($variantByExportKey[$cvKey]) ? $variantByExportKey[$cvKey] : null,
                $lmKey !== null && isset($variantByExportKey[$lmKey]) ? $variantByExportKey[$lmKey] : null,
            );

            $archivedAt = $this->parseOptionalDateTime($row['archivedAt'] ?? null);
            if ($archivedAt !== null) {
                $company->archive($archivedAt);
            }

            $this->entityManager->persist($company);
            $byCode[$code] = $company;
        }

        return $byCode;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param array<string, TrackedCompany> $companyByCode
     * @return void
     */
    private function restoreCompanyCvSectionOverrides(array $rows, array $companyByCode): void
    {
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $companyCode = isset($row['companyCode']) && is_string($row['companyCode']) ? trim($row['companyCode']) : '';
            $sectionKey = isset($row['sectionKey']) && is_string($row['sectionKey']) ? trim($row['sectionKey']) : '';
            $contentJson = isset($row['contentJson']) && is_string($row['contentJson']) ? $row['contentJson'] : '';
            if (
                $companyCode === ''
                || $sectionKey === ''
                || $contentJson === ''
                || !CompanyCvCustomizationSectionKey::isValid($sectionKey)
                || !isset($companyByCode[$companyCode])
            ) {
                continue;
            }

            $override = new CompanyCvSectionOverride($companyByCode[$companyCode], $sectionKey, $contentJson);
            $this->entityManager->persist($override);
        }
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param array<string, TrackedCompany> $companyByCode
     *
     * @return array<string, CompanyCvVisit>
     */
    private function restoreCompanyVisits(array $rows, array $companyByCode): array
    {
        /** @var array<string, CompanyCvVisit> $byKey */
        $byKey = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $companyCode = isset($row['companyCode']) && is_string($row['companyCode']) ? trim($row['companyCode']) : '';
            $visitDateRaw = isset($row['visitDate']) && is_string($row['visitDate']) ? trim($row['visitDate']) : '';
            $visitorKey = isset($row['visitorKey']) && is_string($row['visitorKey']) ? trim($row['visitorKey']) : '';
            if ($companyCode === '' || $visitDateRaw === '' || $visitorKey === '' || !isset($companyByCode[$companyCode])) {
                continue;
            }

            $visitDate = DateTimeImmutable::createFromFormat('!Y-m-d', $visitDateRaw);
            $startedAt = $this->parseRequiredDateTime($row['startedAt'] ?? null) ?? new DateTimeImmutable();
            if ($visitDate === false) {
                continue;
            }

            $visit = new CompanyCvVisit(
                $companyByCode[$companyCode],
                $visitDate,
                $visitorKey,
                $startedAt,
                $this->nullableString($row['ipAddress'] ?? null),
                $this->nullableString($row['countryCode'] ?? null),
            );

            $journey = is_array($row['journeyJson'] ?? null) ? $row['journeyJson'] : [];
            foreach ($journey as $step) {
                if (!is_array($step)) {
                    continue;
                }

                $route = isset($step['route']) && is_string($step['route']) ? $step['route'] : '';
                $path = isset($step['path']) && is_string($step['path']) ? $step['path'] : '';
                $viewedAt = $this->parseRequiredDateTime($step['viewedAt'] ?? null) ?? $startedAt;
                if ($route !== '' && $path !== '') {
                    $visit->appendJourneyStep($route, $path, $viewedAt);
                }
            }

            $this->entityManager->persist($visit);
            $byKey[self::visitKey($companyCode, $visitDateRaw, $visitorKey)] = $visit;
        }

        return $byKey;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param array<string, TrackedCompany> $companyByCode
     * @param array<string, CompanyCvVisit> $visitByKey
     */
    private function restoreConnectionLogs(
        array $rows,
        array $companyByCode,
        array $visitByKey,
    ): void {
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $connectionKind = isset($row['connectionKind']) && is_string($row['connectionKind'])
                ? trim($row['connectionKind'])
                : '';
            $occurredAt = $this->parseRequiredDateTime($row['occurredAt'] ?? null);
            if ($connectionKind === '' || $occurredAt === null) {
                continue;
            }

            $log = new CvConnectionLog($connectionKind, $occurredAt);
            $log->setFormatRaw($this->nullableString($row['formatRaw'] ?? null));
            $log->setCompanyCodeSnapshot($this->nullableString($row['companyCodeSnapshot'] ?? null));
            $log->setCompanyNameSnapshot($this->nullableString($row['companyNameSnapshot'] ?? null));
            $log->setIpAddress($this->nullableString($row['ipAddress'] ?? null));
            $log->setCountryCode($this->nullableString($row['countryCode'] ?? null));
            $log->setUserAgent($this->nullableString($row['userAgent'] ?? null));
            $log->setGatePassed(($row['gatePassed'] ?? false) === true);
            $log->setAttestationMethod($this->nullableString($row['attestationMethod'] ?? null));
            $log->setTechnicalScore(isset($row['technicalScore']) && is_numeric($row['technicalScore'])
                ? (int) $row['technicalScore']
                : null);
            $log->setCountableForCompany(($row['countableForCompany'] ?? false) === true);
            $log->setIsAdminBypass(($row['isAdminBypass'] ?? false) === true);
            $log->setRequestPath($this->nullableString($row['requestPath'] ?? null));
            $log->setRequestRoute($this->nullableString($row['requestRoute'] ?? null));

            $companyCode = $this->nullableString($row['companyCode'] ?? null);
            if ($companyCode !== null && isset($companyByCode[$companyCode])) {
                $log->setCompany($companyByCode[$companyCode]);
            }

            $visitKeyRow = is_array($row['visitKey'] ?? null) ? $row['visitKey'] : null;
            if ($visitKeyRow !== null) {
                $visitCompanyCode = isset($visitKeyRow['companyCode']) && is_string($visitKeyRow['companyCode'])
                    ? trim($visitKeyRow['companyCode'])
                    : '';
                $visitDate = isset($visitKeyRow['visitDate']) && is_string($visitKeyRow['visitDate'])
                    ? trim($visitKeyRow['visitDate'])
                    : '';
                $visitorKey = isset($visitKeyRow['visitorKey']) && is_string($visitKeyRow['visitorKey'])
                    ? trim($visitKeyRow['visitorKey'])
                    : '';
                if ($visitCompanyCode !== '' && $visitDate !== '' && $visitorKey !== '') {
                    $key = self::visitKey($visitCompanyCode, $visitDate, $visitorKey);
                    if (isset($visitByKey[$key])) {
                        $log->setVisit($visitByKey[$key]);
                    }
                }
            }

            $this->entityManager->persist($log);
        }
    }

    /**
     * @param list<array<string, mixed>> $variantRows
     * @param array<string, EmploymentDocumentVariant> $variantByExportKey
     *
     * @return array<string, string> Map of old storage path to new storage path.
     */
    private function buildPathRemapFromVariants(array $variantRows, array $variantByExportKey): array
    {
        $remap = [];
        foreach ($variantRows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $legacyId = isset($row['legacyId']) && is_numeric($row['legacyId']) ? (int) $row['legacyId'] : 0;
            $exportKey = isset($row['exportKey']) && is_string($row['exportKey']) ? $row['exportKey'] : '';
            if ($legacyId <= 0 || $exportKey === '' || !isset($variantByExportKey[$exportKey])) {
                continue;
            }

            $newId = (int) $variantByExportKey[$exportKey]->getId();
            if ($newId <= 0 || $newId === $legacyId) {
                continue;
            }

            $kind = isset($row['kind']) && is_string($row['kind']) ? trim($row['kind']) : '';
            if ($kind === '') {
                continue;
            }

            $oldPrefix = sprintf('%s/%s/%d/', self::EMPLOYMENT_STORAGE_ROOT, $kind, $legacyId);
            $newPrefix = sprintf('%s/%s/%d/', self::EMPLOYMENT_STORAGE_ROOT, $kind, $newId);
            $localeAssets = is_array($row['localeAssets'] ?? null) ? $row['localeAssets'] : [];
            foreach ($localeAssets as $assetRow) {
                if (!is_array($assetRow)) {
                    continue;
                }

                foreach (['templateStoragePath', 'pdfStoragePath'] as $field) {
                    $path = $this->nullableString($assetRow[$field] ?? null);
                    if ($path !== null && str_starts_with($path, $oldPrefix)) {
                        $remap[$path] = $newPrefix.substr($path, strlen($oldPrefix));
                    }
                }
            }
        }

        return $remap;
    }

    /**
     * @param array<string, string> $entryContents
     * @param array<string, string> $pathRemap
     */
    private function replaceEmploymentStorageTree(array $entryContents, array $pathRemap): void
    {
        $storageRoot = $this->projectDir.'/'.self::EMPLOYMENT_STORAGE_ROOT;
        $filesystem = new Filesystem();
        if (is_dir($storageRoot)) {
            $filesystem->remove($storageRoot);
        }

        $prefix = CustomizationBackupPaths::EMPLOYMENT_FILES_PREFIX;
        $prefixLength = strlen($prefix);

        foreach ($entryContents as $entryPath => $bytes) {
            if (!str_starts_with($entryPath, $prefix)) {
                continue;
            }

            $relative = substr($entryPath, $prefixLength);
            if ($relative === '' || str_contains($relative, '..')) {
                throw CustomizationBackupException::withReason('path_traversal_blocked', [
                    '%path%' => $entryPath,
                ]);
            }

            $targetRelative = $pathRemap[$relative] ?? $relative;
            if (!$this->isSafeEmploymentStoragePath($targetRelative)) {
                throw CustomizationBackupException::withReason('path_traversal_blocked', [
                    '%path%' => $entryPath,
                ]);
            }

            $target = $this->projectDir.'/'.$targetRelative;
            $directory = dirname($target);
            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw CustomizationBackupException::withReason('directory_create_failed', [
                    '%path%' => $targetRelative,
                ]);
            }

            if (file_put_contents($target, $bytes) === false) {
                throw CustomizationBackupException::withReason('file_write_failed', [
                    '%path%' => $targetRelative,
                ]);
            }
        }
    }

    private function remapStoragePathForVariant(?string $path, string $kind, int $legacyId, int $newId): ?string
    {
        if ($path === null || $legacyId <= 0 || $newId <= 0 || $legacyId === $newId) {
            return $path;
        }

        $oldPrefix = sprintf('%s/%s/%d/', self::EMPLOYMENT_STORAGE_ROOT, $kind, $legacyId);
        $newPrefix = sprintf('%s/%s/%d/', self::EMPLOYMENT_STORAGE_ROOT, $kind, $newId);
        if (!str_starts_with($path, $oldPrefix)) {
            return $path;
        }

        return $newPrefix.substr($path, strlen($oldPrefix));
    }

    private function isSafeEmploymentStoragePath(string $relativePath): bool
    {
        if ($relativePath === '' || str_contains($relativePath, '..')) {
            return false;
        }

        return str_starts_with($relativePath, self::EMPLOYMENT_STORAGE_ROOT.'/');
    }

    /**
     * @param array<string, string> $entryContents
     *
     * @return list<array<string, mixed>>
     */
    private function decodeJsonList(array $entryContents, string $path): array
    {
        $raw = $entryContents[$path] ?? null;
        if (!is_string($raw)) {
            throw CustomizationBackupException::withReason('json_entry_missing', [
                '%path%' => $path,
            ]);
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw CustomizationBackupException::withReason('json_entry_invalid', [
                '%path%' => $path,
            ]);
        }

        return $decoded;
    }

    /**
     * @brief Decode a JSON list entry when present, or return an empty list for legacy archives.
     *
     * @param array<string, string> $entryContents Extracted ZIP entries.
     * @param string $path Archive entry path.
     * @return list<array<string, mixed>>
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function decodeOptionalJsonList(array $entryContents, string $path): array
    {
        if (!isset($entryContents[$path])) {
            return [];
        }

        return $this->decodeJsonList($entryContents, $path);
    }

    private function encodeJson(mixed $payload): string
    {
        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private static function variantExportKey(string $kind, string $nameNormalized): string
    {
        return $kind.'|'.$nameNormalized;
    }

    private static function visitKey(string $companyCode, string $visitDate, string $visitorKey): string
    {
        return $companyCode.'|'.$visitDate.'|'.$visitorKey;
    }

    /**
     * @brief Normalize QR link coordinate to centimeters for backup restore.
     *
     * @param mixed $value Raw backup value.
     * @param string $defaultCm Default when missing or invalid.
     * @return string
     * @date 2026-06-12
     * @author Stephane H.
     */
    private function normalizeLinkCm(mixed $value, string $defaultCm): string
    {
        if (!is_numeric($value)) {
            return $defaultCm;
        }

        $float = (float) $value;
        if ($float >= 10.0) {
            $float = $float * 2.54 / 72.0;
        }

        if ($float < 0.0) {
            $float = 0.0;
        }
        if ($float > 50.0) {
            $float = 50.0;
        }

        return number_format($float, 2, '.', '');
    }

    private function nullableString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function parseOptionalDateTime(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    private function parseRequiredDateTime(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}
