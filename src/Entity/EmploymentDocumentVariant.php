<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\EmploymentDocumentVariantRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Admin-managed CV or cover-letter document variant (print template set).
 */
#[ORM\Entity(repositoryClass: EmploymentDocumentVariantRepository::class)]
#[ORM\Table(name: 'employment_document_variant', indexes: [
    new ORM\Index(name: 'idx_employment_document_variant_kind', columns: ['kind']),
    new ORM\Index(name: 'idx_employment_document_variant_name_normalized', columns: ['name_normalized']),
    new ORM\Index(name: 'idx_employment_document_variant_created_at', columns: ['created_at']),
    new ORM\Index(name: 'idx_employment_document_variant_updated_at', columns: ['updated_at']),
    new ORM\Index(name: 'idx_employment_document_variant_archived_at', columns: ['archived_at']),
])]
class EmploymentDocumentVariant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 8)]
    private string $kind;

    #[ORM\Column(length: 160)]
    private string $name;

    #[ORM\Column(name: 'name_normalized', length: 160)]
    private string $nameNormalized;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    #[ORM\Column(name: 'archived_at', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $archivedAt = null;

    #[ORM\Column(name: 'link_x', type: Types::DECIMAL, precision: 5, scale: 2)]
    private string $linkX = '2.50';

    #[ORM\Column(name: 'link_y', type: Types::DECIMAL, precision: 5, scale: 2)]
    private string $linkY = '2.50';

    #[ORM\Column(name: 'square_size_cm', type: Types::DECIMAL, precision: 5, scale: 2)]
    private string $squareSizeCm = '2.00';

    #[ORM\Column(name: 'is_default', type: 'boolean', options: ['default' => false])]
    private bool $isDefault = false;

    /**
     * @var Collection<int, EmploymentDocumentLocaleAsset>
     */
    #[ORM\OneToMany(mappedBy: 'variant', targetEntity: EmploymentDocumentLocaleAsset::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $localeAssets;

    /**
     * @brief Create document variant with normalized name.
     *
     * @param string $kind cv or lm.
     * @param string $name Display name.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function __construct(string $kind, string $name)
    {
        $this->kind = $kind;
        $this->setName($name);
        $now = new DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->localeAssets = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getKind(): string
    {
        return $this->kind;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getNameNormalized(): string
    {
        return $this->nameNormalized;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getArchivedAt(): ?DateTimeImmutable
    {
        return $this->archivedAt;
    }

    public function isArchived(): bool
    {
        return $this->archivedAt !== null;
    }

    /**
     * @brief Whether this CV variant is the global default (CV kind only).
     *
     * @return bool
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    /**
     * @brief Set default CV flag (meaningful only when kind is cv).
     *
     * @param bool $isDefault True when this CV is the default template.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function setIsDefault(bool $isDefault): void
    {
        $this->isDefault = $isDefault;
        $this->touch();
    }

    /**
     * @brief Return horizontal QR position in centimeters.
     *
     * @return string
     * @date 2026-06-12
     * @author Stephane H.
     */
    public function getLinkX(): string
    {
        return $this->linkX;
    }

    /**
     * @brief Return vertical QR position in centimeters.
     *
     * @return string
     * @date 2026-06-12
     * @author Stephane H.
     */
    public function getLinkY(): string
    {
        return $this->linkY;
    }

    /**
     * @brief Return square side length in centimeters.
     *
     * @return string
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function getSquareSizeCm(): string
    {
        return $this->squareSizeCm;
    }

    /**
     * @brief Set QR / link placement for this variant.
     *
     * @param string $linkXCm Horizontal position in centimeters.
     * @param string $linkYCm Vertical position in centimeters.
     * @param string $squareSizeCm Square side in centimeters.
     * @return void
     * @date 2026-06-12
     * @author Stephane H.
     */
    public function setPlacement(string $linkXCm, string $linkYCm, string $squareSizeCm): void
    {
        $this->linkX = $linkXCm;
        $this->linkY = $linkYCm;
        $this->squareSizeCm = $squareSizeCm;
        $this->touch();
    }

    /**
     * @return Collection<int, EmploymentDocumentLocaleAsset>
     */
    public function getLocaleAssets(): Collection
    {
        return $this->localeAssets;
    }

    /**
     * @brief Update display name and normalized search key.
     *
     * @param string $name New display name.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function setName(string $name): void
    {
        $this->name = $name;
        $this->nameNormalized = self::normalizeName($name);
        $this->touch();
    }

    /**
     * @brief Mark variant archived.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function archive(): void
    {
        $this->archivedAt = new DateTimeImmutable();
        $this->touch();
    }

    /**
     * @brief Mark variant as active again.
     *
     * @return void
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function unarchive(): void
    {
        $this->archivedAt = null;
        $this->touch();
    }

    /**
     * @brief Find locale asset by code or null.
     *
     * @param string $locale Locale code.
     * @return EmploymentDocumentLocaleAsset|null
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function findLocaleAsset(string $locale): ?EmploymentDocumentLocaleAsset
    {
        foreach ($this->localeAssets as $asset) {
            if ($asset->getLocale() === $locale) {
                return $asset;
            }
        }

        return null;
    }

    /**
     * @brief Count locales with both template and PDF stored.
     *
     * @return int
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function countCompleteLocalePairs(): int
    {
        $count = 0;
        foreach ($this->localeAssets as $asset) {
            if ($asset->hasCompletePair()) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * @brief Normalize name for case-insensitive search.
     *
     * @param string $name Raw name.
     * @return string
     * @date 2026-06-01
     * @author Stephane H.
     */
    public static function normalizeName(string $name): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', trim($name)) ?? trim($name);
        if (function_exists('transliterator_transliterate')) {
            $ascii = transliterator_transliterate('Any-Latin; Latin-ASCII', $collapsed);
            if (is_string($ascii) && $ascii !== '') {
                $collapsed = $ascii;
            }
        }

        return mb_strtolower($collapsed);
    }

    /**
     * @brief Bump updated timestamp.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
