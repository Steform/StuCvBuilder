<?php

declare(strict_types=1);

namespace App\Service\Employment;

use App\Entity\EmploymentDocumentLocaleAsset;
use App\Entity\EmploymentDocumentVariant;
use App\Employment\EmploymentDocumentKind;
use App\Repository\EmploymentDocumentVariantRepository;
use App\Repository\EmploymentPrintPlacementRepository;
use App\Repository\TrackedCompanyRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Admin CRUD for employment CV/LM document variants.
 */
class EmploymentDocumentVariantManagementService
{
    /**
     * @brief Build employment document variant management service.
     *
     * @param EntityManagerInterface $entityManager ORM entity manager.
     * @param EmploymentDocumentStorageService $storageService File storage helper.
     * @param EmploymentPrintPlacementRepository $placementRepository Print placement repository.
     * @param EmploymentPrintPlacementManagementService $placementManagementService Placement parser and defaults.
     * @param EmploymentDocumentVariantRepository $variantRepository Variant repository.
     * @param TrackedCompanyRepository $trackedCompanyRepository Tracked company repository.
     * @return void
     * @date 2026-06-15
     * @author Stephane H.
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EmploymentDocumentStorageService $storageService,
        private readonly EmploymentPrintPlacementRepository $placementRepository,
        private readonly EmploymentPrintPlacementManagementService $placementManagementService,
        private readonly EmploymentDocumentVariantRepository $variantRepository,
        private readonly TrackedCompanyRepository $trackedCompanyRepository,
    ) {
    }

    /**
     * @brief Ensure default print placement rows exist.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function ensurePrintPlacementsExist(): void
    {
        $this->placementRepository->ensureDefaultsExist();
        $this->ensureLoneActiveKindIsDefault(EmploymentDocumentKind::CV);
        $this->ensureLoneActiveKindIsDefault(EmploymentDocumentKind::LM);
        $this->entityManager->flush();
    }

    /**
     * @brief Normalize admin search query.
     *
     * @param string $search Raw search input.
     * @return string
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function normalizeSearchQuery(string $search): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', trim($search)) ?? trim($search);

        return EmploymentDocumentVariant::normalizeName($collapsed);
    }

    /**
     * @brief Create document variant with locale uploads.
     *
     * @param string $kind cv or lm.
     * @param string $name Display name.
     * @param list<EmploymentDocumentLocaleAssetInput> $localeInputs Per-locale uploads.
     * @param string $linkX Raw horizontal placement input.
     * @param string $linkY Raw vertical placement input.
     * @param string $squareSizeCm Raw square size in cm.
     * @param bool $setAsDefault When true, mark as default for this kind (clears other defaults of same kind).
     * @return array{variant: EmploymentDocumentVariant|null, error: string|null}
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function create(
        string $kind,
        string $name,
        array $localeInputs,
        string $linkX,
        string $linkY,
        string $squareSizeCm,
        bool $setAsDefault = false,
    ): array
    {
        if (!EmploymentDocumentKind::isValid($kind)) {
            return ['variant' => null, 'error' => 'employment.documents.flash.kind_invalid'];
        }

        $name = trim($name);
        if ($name === '') {
            return ['variant' => null, 'error' => 'employment.documents.flash.name_required'];
        }

        $placement = $this->placementManagementService->parsePlacementFields($linkX, $linkY, $squareSizeCm);
        if (isset($placement['error'])) {
            return ['variant' => null, 'error' => $placement['error']];
        }

        $variant = new EmploymentDocumentVariant($kind, $name);
        $variant->setPlacement($placement['linkX'], $placement['linkY'], $placement['squareSizeCm']);
        $this->entityManager->persist($variant);
        $this->entityManager->flush();

        try {
            $this->applyLocaleInputs($variant, $localeInputs);
        } catch (\InvalidArgumentException $exception) {
            $this->entityManager->remove($variant);
            $this->entityManager->flush();

            return ['variant' => null, 'error' => $exception->getMessage()];
        }

        if ($variant->countCompleteLocalePairs() < 1) {
            $this->removeVariantWithFiles($variant);

            return ['variant' => null, 'error' => 'employment.documents.flash.locale_pair_required'];
        }

        $this->applyDefaultKindFlag($variant, $setAsDefault);
        $this->entityManager->flush();

        return ['variant' => $variant, 'error' => null];
    }

    /**
     * @brief Update variant name and locale files.
     *
     * @param EmploymentDocumentVariant $variant Existing variant.
     * @param string $name New display name.
     * @param list<EmploymentDocumentLocaleAssetInput> $localeInputs Per-locale uploads.
     * @param string $linkX Raw horizontal placement input.
     * @param string $linkY Raw vertical placement input.
     * @param string $squareSizeCm Raw square size in cm.
     * @param bool $setAsDefault When true, mark as default for this kind (clears other defaults of same kind).
     * @return string|null Error translation key or null on success.
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function update(
        EmploymentDocumentVariant $variant,
        string $name,
        array $localeInputs,
        string $linkX,
        string $linkY,
        string $squareSizeCm,
        bool $setAsDefault = false,
    ): ?string {
        $name = trim($name);
        if ($name === '') {
            return 'employment.documents.flash.name_required';
        }

        $placement = $this->placementManagementService->parsePlacementFields($linkX, $linkY, $squareSizeCm);
        if (isset($placement['error'])) {
            return $placement['error'];
        }

        $previousPlacement = $variant->getLinkX().'|'.$variant->getLinkY().'|'.$variant->getSquareSizeCm();
        $nextPlacement = $placement['linkX'].'|'.$placement['linkY'].'|'.$placement['squareSizeCm'];

        $variant->setName($name);
        $variant->setPlacement($placement['linkX'], $placement['linkY'], $placement['squareSizeCm']);

        $variantId = $variant->getId();
        if ($variantId !== null && $variantId > 0 && $previousPlacement !== $nextPlacement) {
            $this->storageService->deleteStampedPdfsForVariant($variant->getKind(), $variantId);
        }

        if ($localeInputs !== []) {
            try {
                $this->applyLocaleInputs($variant, $localeInputs);
            } catch (\InvalidArgumentException $exception) {
                return $exception->getMessage();
            }
        }

        if ($variant->countCompleteLocalePairs() < 1) {
            return 'employment.documents.flash.locale_pair_required';
        }

        $this->applyDefaultKindFlag($variant, $setAsDefault);
        $this->entityManager->flush();

        return null;
    }

    /**
     * @brief Whether archiving is blocked for this variant.
     *
     * @param EmploymentDocumentVariant $variant Document variant.
     * @return string|null Error translation key or null when archive is allowed.
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function getArchiveBlockReason(EmploymentDocumentVariant $variant): ?string
    {
        if ($variant->isArchived()) {
            return null;
        }

        $variantId = $variant->getId();
        if ($variantId === null || $variantId < 1) {
            return null;
        }

        if ($variant->getKind() === EmploymentDocumentKind::CV) {
            if ($this->variantRepository->countActiveByKind(EmploymentDocumentKind::CV) <= 1) {
                return 'employment.documents.flash.only_active_cv_cannot_archive';
            }
        }

        if ($variant->getKind() === EmploymentDocumentKind::LM) {
            if ($this->variantRepository->countActiveByKind(EmploymentDocumentKind::LM) <= 1) {
                return 'employment.documents.flash.only_active_lm_cannot_archive';
            }
        }

        return null;
    }

    /**
     * @brief Archive variant without deleting stored files.
     *
     * @param EmploymentDocumentVariant $variant Variant to archive.
     * @return string|null Error translation key or null on success.
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function archive(EmploymentDocumentVariant $variant): ?string
    {
        if ($variant->isArchived()) {
            return null;
        }

        $blockReason = $this->getArchiveBlockReason($variant);
        if ($blockReason !== null) {
            return $blockReason;
        }

        $variantId = $variant->getId();
        if ($variantId !== null && $variantId > 0) {
            $replacementVariantId = $this->resolveArchiveReplacementVariantId($variant);
            if ($replacementVariantId === null) {
                return $variant->getKind() === EmploymentDocumentKind::LM
                    ? 'employment.documents.flash.lm_assigned_to_company'
                    : 'employment.documents.flash.cv_assigned_to_company';
            }

            $this->trackedCompanyRepository->reassignActiveCompaniesDocumentVariant(
                $variant->getKind(),
                $variantId,
                $replacementVariantId,
            );
        }

        if ($variant->isDefault()) {
            $variant->setIsDefault(false);
        }

        $kind = $variant->getKind();
        $variant->archive();
        $this->entityManager->flush();
        $this->ensureLoneActiveKindIsDefault($kind);
        $this->entityManager->flush();

        return null;
    }

    /**
     * @brief Unarchive variant and ensure default consistency.
     *
     * @param EmploymentDocumentVariant $variant Variant to unarchive.
     * @return void
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function unarchive(EmploymentDocumentVariant $variant): void
    {
        if (!$variant->isArchived()) {
            return;
        }

        $kind = $variant->getKind();
        $variant->unarchive();
        $this->ensureLoneActiveKindIsDefault($kind);
        $this->entityManager->flush();
    }

    /**
     * @brief Resolve replacement variant id used when archiving a variant.
     *
     * @param EmploymentDocumentVariant $variant Variant to archive.
     * @return int|null Replacement variant id or null when none can be selected.
     * @date 2026-06-16
     * @author Stephane H.
     */
    private function resolveArchiveReplacementVariantId(EmploymentDocumentVariant $variant): ?int
    {
        $variantId = $variant->getId();
        if ($variantId === null || $variantId < 1) {
            return null;
        }

        $kind = $variant->getKind();
        $defaultVariant = $this->variantRepository->findDefaultByKind($kind);
        if ($defaultVariant instanceof EmploymentDocumentVariant) {
            $defaultVariantId = $defaultVariant->getId();
            if ($defaultVariantId !== null && $defaultVariantId > 0 && $defaultVariantId !== $variantId) {
                return $defaultVariantId;
            }
        }

        $fallbackVariant = $this->variantRepository->findFirstActiveByKindExcludingId($kind, $variantId);
        if (!$fallbackVariant instanceof EmploymentDocumentVariant) {
            return null;
        }

        $fallbackVariantId = $fallbackVariant->getId();

        return ($fallbackVariantId !== null && $fallbackVariantId > 0) ? $fallbackVariantId : null;
    }

    /**
     * @brief Parse optional Y-m-d filter value.
     *
     * @param string $value Raw query value.
     * @return string|null Normalized date or null when empty/invalid.
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function normalizeDateFilter(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $parsed instanceof DateTimeImmutable ? $parsed->format('Y-m-d') : null;
    }

    /**
     * @brief Apply default flag for the variant kind (cv or lm).
     *
     * @param EmploymentDocumentVariant $variant Target variant.
     * @param bool $setAsDefault Whether admin requested default for this kind.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function applyDefaultKindFlag(EmploymentDocumentVariant $variant, bool $setAsDefault): void
    {
        $kind = $variant->getKind();
        if (!EmploymentDocumentKind::isValid($kind)) {
            return;
        }

        if ($this->variantRepository->countActiveByKind($kind) <= 1) {
            $variantId = $variant->getId();
            if ($variantId !== null && $variantId > 0) {
                $this->variantRepository->clearDefaultForKindExcept($kind, $variantId);
            }

            $variant->setIsDefault(true);

            return;
        }

        if (!$setAsDefault) {
            $variant->setIsDefault(false);

            return;
        }

        $variantId = $variant->getId();
        if ($variantId !== null && $variantId > 0) {
            $this->variantRepository->clearDefaultForKindExcept($kind, $variantId);
        }

        $variant->setIsDefault(true);
    }

    /**
     * @brief When exactly one active variant exists for a kind, force it as default.
     *
     * @param string $kind cv or lm.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function ensureLoneActiveKindIsDefault(string $kind): void
    {
        if (!EmploymentDocumentKind::isValid($kind)) {
            return;
        }

        if ($this->variantRepository->countActiveByKind($kind) !== 1) {
            return;
        }

        $active = $this->variantRepository->findActiveByKindForCompanySelect($kind);
        if ($active === []) {
            return;
        }

        $only = $active[0];
        $variantId = $only->getId();
        if ($variantId === null || $variantId < 1) {
            return;
        }

        if ($only->isDefault()) {
            return;
        }

        $this->variantRepository->clearDefaultForKindExcept($kind, $variantId);
        $only->setIsDefault(true);
    }

    /**
     * @brief Apply locale uploads and removals to variant.
     *
     * @param EmploymentDocumentVariant $variant Parent variant.
     * @param list<EmploymentDocumentLocaleAssetInput> $localeInputs Upload rows.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function applyLocaleInputs(EmploymentDocumentVariant $variant, array $localeInputs): void
    {
        $variantId = (int) $variant->getId();
        if ($variantId < 1) {
            throw new \LogicException('Variant must be persisted before storing files.');
        }

        foreach ($localeInputs as $input) {
            if (!$input instanceof EmploymentDocumentLocaleAssetInput) {
                continue;
            }

            $asset = $variant->findLocaleAsset($input->locale);
            if (!$asset instanceof EmploymentDocumentLocaleAsset) {
                $asset = new EmploymentDocumentLocaleAsset($variant, $input->locale);
                $variant->getLocaleAssets()->add($asset);
                $this->entityManager->persist($asset);
            }

            if ($input->removeTemplate) {
                $this->storageService->deleteIfNeeded($asset->getTemplateStoragePath());
                $asset->clearTemplate();
            }

            if ($input->removePdf) {
                $this->storageService->deleteIfNeeded($asset->getPdfStoragePath());
                $this->storageService->deleteStampedPdfsForLocale($variant->getKind(), $variantId, $input->locale);
                $asset->clearPdf();
            }

            if ($input->templateFile instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
                $this->storageService->deleteIfNeeded($asset->getTemplateStoragePath());
                $path = $this->storageService->storeTemplate(
                    $variant->getKind(),
                    $variantId,
                    $input->locale,
                    $input->templateFile,
                );
                $asset->setTemplateFile($path, (string) $input->templateFile->getClientOriginalName());
            }

            if ($input->pdfFile instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
                $this->storageService->deleteIfNeeded($asset->getPdfStoragePath());
                $this->storageService->deleteStampedPdfsForLocale($variant->getKind(), $variantId, $input->locale);
                $path = $this->storageService->storePdf(
                    $variant->getKind(),
                    $variantId,
                    $input->locale,
                    $input->pdfFile,
                );
                $asset->setPdfFile($path, (string) $input->pdfFile->getClientOriginalName());
            }

            $hasTemplate = $asset->getTemplateStoragePath() !== null && $asset->getTemplateStoragePath() !== '';
            $hasPdf = $asset->getPdfStoragePath() !== null && $asset->getPdfStoragePath() !== '';
            if ($hasTemplate xor $hasPdf) {
                throw new \InvalidArgumentException('employment.documents.flash.locale_pair_incomplete');
            }
        }
    }

    /**
     * @brief Remove variant and delete all stored files.
     *
     * @param EmploymentDocumentVariant $variant Variant to remove.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function removeVariantWithFiles(EmploymentDocumentVariant $variant): void
    {
        $variantId = (int) $variant->getId();
        if ($variantId > 0) {
            $this->storageService->deleteStampedPdfsForVariant($variant->getKind(), $variantId);
        }

        foreach ($variant->getLocaleAssets() as $asset) {
            $this->storageService->deleteIfNeeded($asset->getTemplateStoragePath());
            $this->storageService->deleteIfNeeded($asset->getPdfStoragePath());
        }

        $this->entityManager->remove($variant);
        $this->entityManager->flush();
    }
}
