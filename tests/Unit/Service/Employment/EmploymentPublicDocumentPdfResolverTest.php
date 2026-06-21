<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Employment;

use App\Entity\EmploymentDocumentLocaleAsset;
use App\Entity\EmploymentDocumentVariant;
use App\Entity\TrackedCompany;
use App\Employment\EmploymentDocumentKind;
use App\Repository\EmploymentDocumentVariantRepository;
use App\Repository\TrackedCompanyRepository;
use App\Service\Employment\EmploymentDocumentStorageService;
use App\Service\Employment\EmploymentPublicDocumentPdfResolver;
use App\Service\Locale\LocaleConfigurationService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @brief Unit tests for public employment document PDF resolution.
 */
final class EmploymentPublicDocumentPdfResolverTest extends TestCase
{
    /**
     * @brief Uses company CV variant when assigned and PDF exists.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testResolveCvUsesCompanyCvVariant(): void
    {
        $variant = $this->buildVariantWithPdf(EmploymentDocumentKind::CV, 10, 'fr', 'var/employment_documents/cv/10/fr/pdf.pdf');
        $company = new TrackedCompany('CODE1234567', 'Acme', null);
        $company->setDocumentVariants($variant, null);

        $trackedCompanyRepository = $this->createMock(TrackedCompanyRepository::class);
        $trackedCompanyRepository
            ->expects(self::once())
            ->method('findActiveByCodeWithDocumentVariants')
            ->with('CODE1234567')
            ->willReturn($company);

        $variantRepository = $this->createMock(EmploymentDocumentVariantRepository::class);
        $variantRepository
            ->expects(self::once())
            ->method('findActiveWithLocaleAssetsById')
            ->with(10, EmploymentDocumentKind::CV)
            ->willReturn($variant);
        $variantRepository->method('findDefaultByKind')->willReturn(null);
        $variantRepository->method('countActiveByKind')->willReturn(2);

        $storage = $this->createMock(EmploymentDocumentStorageService::class);
        $storage
            ->method('resolveAbsolutePath')
            ->with('var/employment_documents/cv/10/fr/pdf.pdf')
            ->willReturn('/tmp/cv-fr.pdf');

        $resolver = $this->buildResolver($trackedCompanyRepository, $variantRepository, $storage);

        $result = $resolver->resolveCv('CODE1234567', 'fr');

        self::assertNotNull($result);
        self::assertSame('/tmp/cv-fr.pdf', $result['absolutePath']);
        self::assertSame($variant, $result['variant']);
        self::assertInstanceOf(EmploymentDocumentLocaleAsset::class, $result['localeAsset']);
    }

    /**
     * @brief Uses company LM variant when assigned and PDF exists.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testResolveLmUsesCompanyLmVariant(): void
    {
        $variant = $this->buildVariantWithPdf(EmploymentDocumentKind::LM, 11, 'fr', 'var/employment_documents/lm/11/fr/pdf.pdf');
        $company = new TrackedCompany('CODE1234567', 'Acme', null);
        $company->setDocumentVariants(null, $variant);

        $trackedCompanyRepository = $this->createMock(TrackedCompanyRepository::class);
        $trackedCompanyRepository
            ->method('findActiveByCodeWithDocumentVariants')
            ->willReturn($company);

        $variantRepository = $this->createMock(EmploymentDocumentVariantRepository::class);
        $variantRepository
            ->method('findActiveWithLocaleAssetsById')
            ->with(11, EmploymentDocumentKind::LM)
            ->willReturn($variant);
        $variantRepository->method('findDefaultByKind')->willReturn(null);
        $variantRepository->method('countActiveByKind')->willReturn(2);

        $storage = $this->createMock(EmploymentDocumentStorageService::class);
        $storage->method('resolveAbsolutePath')->willReturn('/tmp/lm-fr.pdf');

        $resolver = $this->buildResolver($trackedCompanyRepository, $variantRepository, $storage);

        $result = $resolver->resolveLm('CODE1234567', 'fr');

        self::assertNotNull($result);
        self::assertSame('/tmp/lm-fr.pdf', $result['absolutePath']);
    }

    /**
     * @brief Returns null when company format is set but no company LM variant is assigned.
     *
     * @return void
     * @date 2026-06-17
     * @author Stephane H.
     */
    public function testResolveLmReturnsNullWhenCompanyHasNoAssignedVariant(): void
    {
        $company = new TrackedCompany('CODE1234567', 'Acme', null);
        $company->setDocumentVariants(null, null);

        $default = $this->buildVariantWithPdf(EmploymentDocumentKind::LM, 21, 'fr', 'var/employment_documents/lm/21/fr/pdf.pdf');

        $trackedCompanyRepository = $this->createMock(TrackedCompanyRepository::class);
        $trackedCompanyRepository->method('findActiveByCodeWithDocumentVariants')->willReturn($company);

        $variantRepository = $this->createMock(EmploymentDocumentVariantRepository::class);
        $variantRepository->expects(self::never())->method('findDefaultByKind');

        $storage = $this->createMock(EmploymentDocumentStorageService::class);

        $resolver = $this->buildResolver($trackedCompanyRepository, $variantRepository, $storage);

        self::assertNull($resolver->resolveLm('CODE1234567', 'fr'));
    }

    /**
     * @brief Falls back to default LM when no company format is provided.
     *
     * @return void
     * @date 2026-06-17
     * @author Stephane H.
     */
    public function testResolveLmFallsBackToDefaultWhenNoCompanyFormat(): void
    {
        $default = $this->buildVariantWithPdf(EmploymentDocumentKind::LM, 21, 'fr', 'var/employment_documents/lm/21/fr/pdf.pdf');

        $trackedCompanyRepository = $this->createMock(TrackedCompanyRepository::class);
        $trackedCompanyRepository->expects(self::never())->method('findActiveByCodeWithDocumentVariants');

        $variantRepository = $this->createMock(EmploymentDocumentVariantRepository::class);
        $variantRepository->method('findDefaultByKind')->with(EmploymentDocumentKind::LM)->willReturn($default);
        $variantRepository
            ->method('findActiveWithLocaleAssetsById')
            ->with(21, EmploymentDocumentKind::LM)
            ->willReturn($default);

        $storage = $this->createMock(EmploymentDocumentStorageService::class);
        $storage->method('resolveAbsolutePath')->willReturn('/tmp/default-lm.pdf');

        $resolver = $this->buildResolver($trackedCompanyRepository, $variantRepository, $storage);

        self::assertNotNull($resolver->resolveLm('', 'fr'));
    }

    /**
     * @brief Returns null when no variant chain yields a PDF file.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testResolveReturnsNullWhenNoPdfAvailable(): void
    {
        $variantRepository = $this->createMock(EmploymentDocumentVariantRepository::class);
        $variantRepository->method('findDefaultByKind')->willReturn(null);
        $variantRepository->method('countActiveByKind')->willReturn(0);

        $resolver = $this->buildResolver(
            $this->createMock(TrackedCompanyRepository::class),
            $variantRepository,
            $this->createMock(EmploymentDocumentStorageService::class),
        );

        self::assertNull($resolver->resolveCv('', 'fr'));
        self::assertNull($resolver->resolveLm('', 'fr'));
    }

    /**
     * @brief Build resolver with mocked dependencies.
     *
     * @param TrackedCompanyRepository $trackedCompanyRepository Company repository mock.
     * @param EmploymentDocumentVariantRepository $variantRepository Variant repository mock.
     * @param EmploymentDocumentStorageService $storage Storage mock.
     * @return EmploymentPublicDocumentPdfResolver
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function buildResolver(
        TrackedCompanyRepository $trackedCompanyRepository,
        EmploymentDocumentVariantRepository $variantRepository,
        EmploymentDocumentStorageService $storage,
    ): EmploymentPublicDocumentPdfResolver {
        $localeConfiguration = $this->createMock(LocaleConfigurationService::class);
        $localeConfiguration->method('getConfiguration')->willReturn([
            'activeLocales' => ['fr', 'en'],
            'defaultLocale' => 'fr',
        ]);

        return new EmploymentPublicDocumentPdfResolver(
            $trackedCompanyRepository,
            $variantRepository,
            $storage,
            $localeConfiguration,
        );
    }

    /**
     * @brief Build variant entity with one locale PDF path.
     *
     * @param string $kind Document kind.
     * @param int $id Synthetic id.
     * @param string $locale Locale code.
     * @param string $pdfPath Relative PDF storage path.
     * @return EmploymentDocumentVariant
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function buildVariantWithPdf(string $kind, int $id, string $locale, string $pdfPath): EmploymentDocumentVariant
    {
        $variant = new EmploymentDocumentVariant($kind, 'Test');
        $this->setEntityId($variant, $id);
        $asset = new EmploymentDocumentLocaleAsset($variant, $locale);
        $asset->setPdfFile($pdfPath, 'document.pdf');
        $variant->getLocaleAssets()->add($asset);

        return $variant;
    }

    /**
     * @brief Assign synthetic id on Doctrine entity.
     *
     * @param object $entity Entity instance.
     * @param int $id Primary key.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function setEntityId(object $entity, int $id): void
    {
        $property = (new ReflectionClass($entity))->getProperty('id');
        $property->setValue($entity, $id);
    }
}
