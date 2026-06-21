<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\EmploymentPrintPlacementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Global QR / link placement coordinates for CV or LM print layouts.
 */
#[ORM\Entity(repositoryClass: EmploymentPrintPlacementRepository::class)]
#[ORM\Table(name: 'employment_print_placement', uniqueConstraints: [
    new ORM\UniqueConstraint(name: 'uniq_employment_print_placement_kind', columns: ['kind']),
])]
class EmploymentPrintPlacement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 8)]
    private string $kind;

    #[ORM\Column(name: 'link_x', type: Types::DECIMAL, precision: 5, scale: 2)]
    private string $linkX = '2.50';

    #[ORM\Column(name: 'link_y', type: Types::DECIMAL, precision: 5, scale: 2)]
    private string $linkY = '2.50';

    #[ORM\Column(name: 'square_size_cm', type: Types::DECIMAL, precision: 5, scale: 2)]
    private string $squareSizeCm = '2.00';

    /**
     * @brief Build placement row for a document kind.
     *
     * @param string $kind cv or lm.
     * @param string $linkXCm Horizontal position in centimeters.
     * @param string $linkYCm Vertical position in centimeters.
     * @param string $squareSizeCm Square side length in centimeters.
     * @return void
     * @date 2026-06-12
     * @author Stephane H.
     */
    public function __construct(string $kind, string $linkXCm, string $linkYCm, string $squareSizeCm = '2.00')
    {
        $this->kind = $kind;
        $this->linkX = $linkXCm;
        $this->linkY = $linkYCm;
        $this->squareSizeCm = $squareSizeCm;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getKind(): string
    {
        return $this->kind;
    }

    /**
     * @brief Return horizontal position in centimeters.
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
     * @brief Return vertical position in centimeters.
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
     * @brief Return square side length in centimeters as decimal string.
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
     * @brief Update horizontal link position in centimeters.
     *
     * @param string $linkXCm New X value.
     * @return void
     * @date 2026-06-12
     * @author Stephane H.
     */
    public function setLinkX(string $linkXCm): void
    {
        $this->linkX = $linkXCm;
    }

    /**
     * @brief Update vertical link position in centimeters.
     *
     * @param string $linkYCm New Y value.
     * @return void
     * @date 2026-06-12
     * @author Stephane H.
     */
    public function setLinkY(string $linkYCm): void
    {
        $this->linkY = $linkYCm;
    }

    /**
     * @brief Update square side length in centimeters.
     *
     * @param string $squareSizeCm Decimal string with two fractional digits.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function setSquareSizeCm(string $squareSizeCm): void
    {
        $this->squareSizeCm = $squareSizeCm;
    }
}
