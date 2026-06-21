<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\EmploymentDocumentLocaleAssetRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Per-locale source template and PDF files for a document variant.
 */
#[ORM\Entity(repositoryClass: EmploymentDocumentLocaleAssetRepository::class)]
#[ORM\Table(name: 'employment_document_locale_asset', uniqueConstraints: [
    new ORM\UniqueConstraint(name: 'uniq_employment_document_locale', columns: ['variant_id', 'locale']),
])]
class EmploymentDocumentLocaleAsset
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: EmploymentDocumentVariant::class, inversedBy: 'localeAssets')]
    #[ORM\JoinColumn(name: 'variant_id', nullable: false, onDelete: 'CASCADE')]
    private EmploymentDocumentVariant $variant;

    #[ORM\Column(length: 8)]
    private string $locale;

    #[ORM\Column(name: 'template_storage_path', length: 512, nullable: true)]
    private ?string $templateStoragePath = null;

    #[ORM\Column(name: 'template_original_filename', length: 255, nullable: true)]
    private ?string $templateOriginalFilename = null;

    #[ORM\Column(name: 'pdf_storage_path', length: 512, nullable: true)]
    private ?string $pdfStoragePath = null;

    #[ORM\Column(name: 'pdf_original_filename', length: 255, nullable: true)]
    private ?string $pdfOriginalFilename = null;

    /**
     * @brief Attach locale row to variant.
     *
     * @param EmploymentDocumentVariant $variant Parent variant.
     * @param string $locale Locale code.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function __construct(EmploymentDocumentVariant $variant, string $locale)
    {
        $this->variant = $variant;
        $this->locale = $locale;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVariant(): EmploymentDocumentVariant
    {
        return $this->variant;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getTemplateStoragePath(): ?string
    {
        return $this->templateStoragePath;
    }

    public function getTemplateOriginalFilename(): ?string
    {
        return $this->templateOriginalFilename;
    }

    public function getPdfStoragePath(): ?string
    {
        return $this->pdfStoragePath;
    }

    public function getPdfOriginalFilename(): ?string
    {
        return $this->pdfOriginalFilename;
    }

    /**
     * @brief Whether both template and PDF paths are set.
     *
     * @return bool
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function hasCompletePair(): bool
    {
        return $this->templateStoragePath !== null
            && $this->templateStoragePath !== ''
            && $this->pdfStoragePath !== null
            && $this->pdfStoragePath !== '';
    }

    /**
     * @brief Assign stored template file metadata.
     *
     * @param string $storagePath Relative path under project var/.
     * @param string $originalFilename Original upload name.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function setTemplateFile(string $storagePath, string $originalFilename): void
    {
        $this->templateStoragePath = $storagePath;
        $this->templateOriginalFilename = $originalFilename;
    }

    /**
     * @brief Assign stored PDF file metadata.
     *
     * @param string $storagePath Relative path under project var/.
     * @param string $originalFilename Original upload name.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function setPdfFile(string $storagePath, string $originalFilename): void
    {
        $this->pdfStoragePath = $storagePath;
        $this->pdfOriginalFilename = $originalFilename;
    }

    /**
     * @brief Clear template file reference.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function clearTemplate(): void
    {
        $this->templateStoragePath = null;
        $this->templateOriginalFilename = null;
    }

    /**
     * @brief Clear PDF file reference.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function clearPdf(): void
    {
        $this->pdfStoragePath = null;
        $this->pdfOriginalFilename = null;
    }
}
