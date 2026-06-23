<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\HomeQuickTileRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Global custom quick tile on the public home landing.
 */
#[ORM\Entity(repositoryClass: HomeQuickTileRepository::class)]
#[ORM\Table(name: 'home_quick_tile')]
#[ORM\Index(name: 'idx_home_quick_tile_customization_sort', columns: ['customization_id', 'sort_order'])]
class HomeQuickTile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: HomeCustomization::class, inversedBy: 'quickTiles')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?HomeCustomization $customization = null;

    #[ORM\Column(name: 'sort_order', type: 'integer')]
    private int $sortOrder = 0;

    #[ORM\Column(length: 2048)]
    private string $linkUrl = '';

    #[ORM\Column(name: 'open_in_new_tab', type: 'boolean')]
    private bool $openInNewTab = false;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $iconRelativePath = null;

    #[ORM\Column(type: 'boolean')]
    private bool $enabled = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    /**
     * @var Collection<int, HomeQuickTileTranslation>
     */
    #[ORM\OneToMany(targetEntity: HomeQuickTileTranslation::class, mappedBy: 'tile', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $translations;

    /**
     * @brief Build home quick tile aggregate with timestamps.
     * @return void
     * @date 2026-05-18
     * @author Stephane H.
     */
    public function __construct()
    {
        $now = new DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->translations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomization(): ?HomeCustomization
    {
        return $this->customization;
    }

    /**
     * @brief Attach tile to parent home customization singleton.
     * @param HomeCustomization|null $customization Parent customization row.
     * @return void
     * @date 2026-05-18
     * @author Stephane H.
     */
    public function setCustomization(?HomeCustomization $customization): void
    {
        $this->customization = $customization;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    /**
     * @brief Set display order among sibling tiles.
     * @param int $sortOrder Sort index (lower first).
     * @return void
     * @date 2026-05-18
     * @author Stephane H.
     */
    public function setSortOrder(int $sortOrder): void
    {
        $this->sortOrder = $sortOrder;
    }

    public function getLinkUrl(): string
    {
        return $this->linkUrl;
    }

    /**
     * @brief Set validated link target URL or internal path.
     * @param string $linkUrl Destination href.
     * @return void
     * @date 2026-05-18
     * @author Stephane H.
     */
    public function setLinkUrl(string $linkUrl): void
    {
        $this->linkUrl = $linkUrl;
    }

    public function isOpenInNewTab(): bool
    {
        return $this->openInNewTab;
    }

    /**
     * @brief Set whether the tile link opens a new browser tab.
     * @param bool $openInNewTab True for target blank.
     * @return void
     * @date 2026-05-18
     * @author Stephane H.
     */
    public function setOpenInNewTab(bool $openInNewTab): void
    {
        $this->openInNewTab = $openInNewTab;
    }

    public function getIconRelativePath(): ?string
    {
        return $this->iconRelativePath;
    }

    /**
     * @brief Set icon path relative to public/.
     * @param string|null $iconRelativePath Relative asset path.
     * @return void
     * @date 2026-05-18
     * @author Stephane H.
     */
    public function setIconRelativePath(?string $iconRelativePath): void
    {
        $this->iconRelativePath = $iconRelativePath;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @brief Enable or hide tile on the public home strip.
     * @param bool $enabled True when visible to visitors.
     * @return void
     * @date 2026-05-18
     * @author Stephane H.
     */
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @brief Touch updated timestamp before flush.
     * @return void
     * @date 2026-05-18
     * @author Stephane H.
     */
    public function markUpdated(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * @brief Return translation rows.
     * @return Collection<int, HomeQuickTileTranslation>
     * @date 2026-05-18
     * @author Stephane H.
     */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    /**
     * @brief Add localized label row.
     * @param HomeQuickTileTranslation $translation Translation entity.
     * @return void
     * @date 2026-05-18
     * @author Stephane H.
     */
    public function addTranslation(HomeQuickTileTranslation $translation): void
    {
        if (!$this->translations->contains($translation)) {
            $this->translations->add($translation);
            $translation->setTile($this);
        }
    }

    /**
     * @brief Remove localized label row.
     * @param HomeQuickTileTranslation $translation Translation entity.
     * @return void
     * @date 2026-05-18
     * @author Stephane H.
     */
    public function removeTranslation(HomeQuickTileTranslation $translation): void
    {
        $this->translations->removeElement($translation);
    }

    /**
     * @brief Resolve label for a locale with empty-string fallback.
     * @param string $locale Requested locale code.
     * @return string
     * @date 2026-05-18
     * @author Stephane H.
     */
    public function getLabelForLocale(string $locale): string
    {
        foreach ($this->translations as $translation) {
            if (strtolower($translation->getLocale()) === strtolower($locale)) {
                return $translation->getLabel();
            }
        }

        return '';
    }
}
