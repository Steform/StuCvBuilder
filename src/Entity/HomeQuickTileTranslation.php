<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\HomeQuickTileTranslationRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Localized button label for a home quick tile.
 */
#[ORM\Entity(repositoryClass: HomeQuickTileTranslationRepository::class)]
#[ORM\Table(name: 'home_quick_tile_translation')]
#[ORM\UniqueConstraint(name: 'uniq_home_quick_tile_locale', columns: ['tile_id', 'locale'])]
class HomeQuickTileTranslation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: HomeQuickTile::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?HomeQuickTile $tile = null;

    #[ORM\Column(length: 8)]
    private string $locale = '';

    #[ORM\Column(length: 128)]
    private string $label = '';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTile(): ?HomeQuickTile
    {
        return $this->tile;
    }

    /**
     * @brief Set parent tile.
     * @param HomeQuickTile|null $tile Parent tile row.
     * @return void
     * @date 2026-05-18
     * @author Stephane H.
     */
    public function setTile(?HomeQuickTile $tile): void
    {
        $this->tile = $tile;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * @brief Set BCP-47 locale code.
     * @param string $locale Locale code.
     * @return void
     * @date 2026-05-18
     * @author Stephane H.
     */
    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @brief Set visible tile label for the locale.
     * @param string $label Short label under the icon.
     * @return void
     * @date 2026-05-18
     * @author Stephane H.
     */
    public function setLabel(string $label): void
    {
        $this->label = $label;
    }
}
