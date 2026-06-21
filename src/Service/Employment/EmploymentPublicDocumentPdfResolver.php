<?php

declare(strict_types=1);

namespace App\Service\Employment;

use App\Entity\EmploymentDocumentLocaleAsset;
use App\Entity\EmploymentDocumentVariant;
use App\Entity\TrackedCompany;
use App\Employment\EmploymentDocumentKind;
use App\Repository\EmploymentDocumentVariantRepository;
use App\Repository\TrackedCompanyRepository;
use App\Service\Locale\LocaleConfigurationService;

/**
 * Resolves stored employment CV/LM PDF files for public downloads.
 */
final class EmploymentPublicDocumentPdfResolver
{
    /**
     * @brief Build public employment document PDF resolver.
     *
     * @param TrackedCompanyRepository $trackedCompanyRepository Company repository.
     * @param EmploymentDocumentVariantRepository $variantRepository Document variant repository.
     * @param EmploymentDocumentStorageService $storageService File storage helper.
     * @param LocaleConfigurationService $localeConfigurationService Active locale configuration.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function __construct(
        private readonly TrackedCompanyRepository $trackedCompanyRepository,
        private readonly EmploymentDocumentVariantRepository $variantRepository,
        private readonly EmploymentDocumentStorageService $storageService,
        private readonly LocaleConfigurationService $localeConfigurationService,
    ) {
    }

    /**
     * @brief Resolve public CV PDF for company format or default CV variant.
     *
     * @param string $formatCode Sticky or query company format code (may be empty).
     * @param string $requestLocale Viewer locale for PDF language fallback.
     * @return array{absolutePath: string, downloadFilename: string, variant: EmploymentDocumentVariant, localeAsset: EmploymentDocumentLocaleAsset, kind: string, locale: string}|null
     * @date 2026-06-12
     * @author Stephane H.
     */
    public function resolveCv(string $formatCode, string $requestLocale): ?array
    {
        return $this->resolve(EmploymentDocumentKind::CV, $formatCode, $requestLocale);
    }

    /**
     * @brief Resolve public cover-letter PDF for company format or default LM variant.
     *
     * @param string $formatCode Sticky or query company format code (may be empty).
     * @param string $requestLocale Viewer locale for PDF language fallback.
     * @return array{absolutePath: string, downloadFilename: string, variant: EmploymentDocumentVariant, localeAsset: EmploymentDocumentLocaleAsset, kind: string, locale: string}|null
     * @date 2026-06-12
     * @author Stephane H.
     */
    public function resolveLm(string $formatCode, string $requestLocale): ?array
    {
        return $this->resolve(
            EmploymentDocumentKind::LM,
            $formatCode,
            $requestLocale,
            trim($formatCode) !== '',
        );
    }

    /**
     * @brief Resolve absolute PDF path and download filename for a public document request.
     *
     * @param string $kind cv or lm.
     * @param string $formatCode Sticky or query company format code (may be empty).
     * @param string $requestLocale Viewer locale for PDF language fallback.
     * @param bool $companyAssignedOnly When true, skip default and sole-active fallbacks.
     * @return array{absolutePath: string, downloadFilename: string, variant: EmploymentDocumentVariant, localeAsset: EmploymentDocumentLocaleAsset, kind: string, locale: string}|null
     * @date 2026-06-17
     * @author Stephane H.
     */
    private function resolve(string $kind, string $formatCode, string $requestLocale, bool $companyAssignedOnly = false): ?array
    {
        if (!EmploymentDocumentKind::isValid($kind)) {
            return null;
        }

        foreach ($this->buildVariantTryChain($kind, $formatCode, $companyAssignedOnly) as $variant) {
            $resolved = $this->resolvePdfForVariant($variant, $kind, $requestLocale);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    /**
     * @brief Ordered variants: company-specific, then default, then sole active fallback.
     *
     * @param string $kind cv or lm.
     * @param string $formatCode Company format code or empty.
     * @param bool $companyAssignedOnly When true, only return the company-assigned variant.
     * @return list<EmploymentDocumentVariant>
     * @date 2026-06-17
     * @author Stephane H.
     */
    private function buildVariantTryChain(string $kind, string $formatCode, bool $companyAssignedOnly = false): array
    {
        $chain = [];
        $seenIds = [];

        $companyVariant = $this->resolveCompanyVariant($kind, $formatCode);
        if ($companyVariant instanceof EmploymentDocumentVariant) {
            $loaded = $this->loadActiveVariantWithAssets($companyVariant, $kind);
            if ($loaded instanceof EmploymentDocumentVariant) {
                $chain[] = $loaded;
                $seenIds[$loaded->getId() ?? 0] = true;
            }
        }

        if ($companyAssignedOnly) {
            return $chain;
        }

        $defaultVariant = $this->variantRepository->findDefaultByKind($kind);
        if ($defaultVariant instanceof EmploymentDocumentVariant) {
            $loaded = $this->loadActiveVariantWithAssets($defaultVariant, $kind);
            $id = $loaded?->getId() ?? 0;
            if ($loaded instanceof EmploymentDocumentVariant && $id > 0 && !isset($seenIds[$id])) {
                $chain[] = $loaded;
                $seenIds[$id] = true;
            }
        }

        if ($chain === [] && $this->variantRepository->countActiveByKind($kind) === 1) {
            $onlyList = $this->variantRepository->findActiveByKindForCompanySelect($kind);
            $only = $onlyList[0] ?? null;
            if ($only instanceof EmploymentDocumentVariant) {
                $loaded = $this->loadActiveVariantWithAssets($only, $kind);
                if ($loaded instanceof EmploymentDocumentVariant) {
                    $chain[] = $loaded;
                }
            }
        }

        return $chain;
    }

    /**
     * @brief Resolve company-assigned active variant when format matches an active company.
     *
     * @param string $kind cv or lm.
     * @param string $formatCode Normalized company code or empty.
     * @return EmploymentDocumentVariant|null
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function resolveCompanyVariant(string $kind, string $formatCode): ?EmploymentDocumentVariant
    {
        $formatCode = trim($formatCode);
        if ($formatCode === '') {
            return null;
        }

        $company = $this->trackedCompanyRepository->findActiveByCodeWithDocumentVariants($formatCode);
        if (!$company instanceof TrackedCompany) {
            return null;
        }

        $variant = $kind === EmploymentDocumentKind::LM
            ? $company->getLmDocumentVariant()
            : $company->getCvDocumentVariant();

        if (!$variant instanceof EmploymentDocumentVariant || $variant->isArchived()) {
            return null;
        }

        if ($variant->getKind() !== $kind) {
            return null;
        }

        return $variant;
    }

    /**
     * @brief Reload variant with locale assets when active.
     *
     * @param EmploymentDocumentVariant $variant Candidate variant.
     * @param string $kind Expected kind.
     * @return EmploymentDocumentVariant|null
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function loadActiveVariantWithAssets(EmploymentDocumentVariant $variant, string $kind): ?EmploymentDocumentVariant
    {
        if ($variant->isArchived()) {
            return null;
        }

        $id = $variant->getId();
        if ($id === null || $id < 1) {
            return null;
        }

        $loaded = $this->variantRepository->findActiveWithLocaleAssetsById($id, $kind);

        return $loaded instanceof EmploymentDocumentVariant ? $loaded : null;
    }

    /**
     * @brief Pick first locale with a stored PDF for the variant.
     *
     * @param EmploymentDocumentVariant $variant Loaded variant with locale assets.
     * @param string $kind cv or lm (filename prefix).
     * @param string $requestLocale Preferred viewer locale.
     * @return array{absolutePath: string, downloadFilename: string, variant: EmploymentDocumentVariant, localeAsset: EmploymentDocumentLocaleAsset, kind: string, locale: string}|null
     * @date 2026-06-12
     * @author Stephane H.
     */
    private function resolvePdfForVariant(EmploymentDocumentVariant $variant, string $kind, string $requestLocale): ?array
    {
        foreach ($this->buildLocaleTryOrder($requestLocale) as $locale) {
            $asset = $variant->findLocaleAsset($locale);
            if (!$asset instanceof EmploymentDocumentLocaleAsset) {
                continue;
            }

            $relativePath = $asset->getPdfStoragePath();
            if ($relativePath === null || $relativePath === '') {
                continue;
            }

            $absolute = $this->storageService->resolveAbsolutePath($relativePath);
            if ($absolute === null) {
                continue;
            }

            $downloadName = $asset->getPdfOriginalFilename();
            if ($downloadName === null || trim($downloadName) === '') {
                $downloadName = sprintf('%s-%s.pdf', $kind, $locale);
            }

            return [
                'absolutePath' => $absolute,
                'downloadFilename' => $downloadName,
                'variant' => $variant,
                'localeAsset' => $asset,
                'kind' => $kind,
                'locale' => $locale,
            ];
        }

        return null;
    }

    /**
     * @brief Build locale try order: request locale, site default, then other active locales.
     *
     * @param string $requestLocale HTTP request locale.
     * @return list<string>
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function buildLocaleTryOrder(string $requestLocale): array
    {
        $config = $this->localeConfigurationService->getConfiguration();
        /** @var list<string> $activeLocales */
        $activeLocales = is_array($config['activeLocales'] ?? null) ? $config['activeLocales'] : ['fr'];
        $defaultLocale = is_string($config['defaultLocale'] ?? null) ? $config['defaultLocale'] : ($activeLocales[0] ?? 'fr');

        $order = [];
        $normalizedRequest = strtolower(trim($requestLocale));
        if ($normalizedRequest !== '' && in_array($normalizedRequest, $activeLocales, true)) {
            $order[] = $normalizedRequest;
        }

        if (!in_array($defaultLocale, $order, true) && in_array($defaultLocale, $activeLocales, true)) {
            $order[] = $defaultLocale;
        }

        foreach ($activeLocales as $locale) {
            if (!in_array($locale, $order, true)) {
                $order[] = $locale;
            }
        }

        return $order;
    }
}
