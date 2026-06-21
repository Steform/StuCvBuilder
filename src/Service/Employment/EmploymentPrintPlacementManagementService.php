<?php

declare(strict_types=1);

namespace App\Service\Employment;

use App\Employment\EmploymentDocumentKind;
use App\Entity\EmploymentPrintPlacement;
use App\Repository\EmploymentPrintPlacementRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Admin updates for global CV/LM QR link placement settings.
 */
class EmploymentPrintPlacementManagementService
{
    private const MIN_COORDINATE_CM = 0.0;

    private const MIN_SIZE_CM = 0.1;

    private const MAX_CM = 50.0;

    /**
     * @brief Build print placement management service.
     *
     * @param EntityManagerInterface $entityManager ORM entity manager.
     * @param EmploymentPrintPlacementRepository $placementRepository Placement repository.
     * @return void
     * @date 2026-06-12
     * @author Stephane H.
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EmploymentPrintPlacementRepository $placementRepository,
    ) {
    }

    /**
     * @brief Ensure default rows exist for CV and LM kinds.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function ensureDefaultsExist(): void
    {
        $this->placementRepository->ensureDefaultsExist();
    }

    /**
     * @brief Update placement coordinates for a document kind.
     *
     * @param string $kind cv or lm.
     * @param string $linkX Raw X input in centimeters.
     * @param string $linkY Raw Y input in centimeters.
     * @param string $squareSizeCm Raw size in cm input.
     * @return string|null Error translation key or null on success.
     * @date 2026-06-12
     * @author Stephane H.
     */
    public function update(string $kind, string $linkX, string $linkY, string $squareSizeCm): ?string
    {
        if (!EmploymentDocumentKind::isValid($kind)) {
            return 'employment.documents.flash.kind_invalid';
        }

        $x = $this->parseCoordinateCm($linkX);
        if ($x === null) {
            return 'employment.documents.placement.flash.x_invalid';
        }

        $y = $this->parseCoordinateCm($linkY);
        if ($y === null) {
            return 'employment.documents.placement.flash.y_invalid';
        }

        $size = $this->parseSizeCm($squareSizeCm);
        if ($size === null) {
            return 'employment.documents.placement.flash.size_invalid';
        }

        $this->ensureDefaultsExist();
        $placement = $this->placementRepository->findOneByKind($kind);
        if (!$placement instanceof EmploymentPrintPlacement) {
            return 'employment.documents.placement.flash.not_found';
        }

        $placement->setLinkX($x);
        $placement->setLinkY($y);
        $placement->setSquareSizeCm($size);
        $this->entityManager->flush();

        return null;
    }

    /**
     * @brief Parse placement fields for a document variant form.
     *
     * @param string $linkX Raw X input in centimeters.
     * @param string $linkY Raw Y input in centimeters.
     * @param string $squareSizeCm Raw size in cm input.
     * @return array{linkX: string, linkY: string, squareSizeCm: string}|array{error: string}
     * @date 2026-06-12
     * @author Stephane H.
     */
    public function parsePlacementFields(string $linkX, string $linkY, string $squareSizeCm): array
    {
        $x = $this->parseCoordinateCm($linkX);
        if ($x === null) {
            return ['error' => 'employment.documents.placement.flash.x_invalid'];
        }

        $y = $this->parseCoordinateCm($linkY);
        if ($y === null) {
            return ['error' => 'employment.documents.placement.flash.y_invalid'];
        }

        $size = $this->parseSizeCm($squareSizeCm);
        if ($size === null) {
            return ['error' => 'employment.documents.placement.flash.size_invalid'];
        }

        return [
            'linkX' => $x,
            'linkY' => $y,
            'squareSizeCm' => $size,
        ];
    }

    /**
     * @brief Parse non-negative decimal coordinate in centimeters.
     *
     * @param string $value Raw input.
     * @return string|null Normalized decimal string or null when invalid.
     * @date 2026-06-12
     * @author Stephane H.
     */
    private function parseCoordinateCm(string $value): ?string
    {
        $value = trim(str_replace(',', '.', $value));
        if ($value === '' || !is_numeric($value)) {
            return null;
        }

        $float = (float) $value;
        if ($float < self::MIN_COORDINATE_CM || $float > self::MAX_CM) {
            return null;
        }

        return number_format($float, 2, '.', '');
    }

    /**
     * @brief Parse positive decimal size in centimeters.
     *
     * @param string $value Raw input.
     * @return string|null Normalized decimal string or null when invalid.
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function parseSizeCm(string $value): ?string
    {
        $value = trim(str_replace(',', '.', $value));
        if ($value === '' || !is_numeric($value)) {
            return null;
        }

        $float = (float) $value;
        if ($float < self::MIN_SIZE_CM || $float > self::MAX_CM) {
            return null;
        }

        return number_format($float, 2, '.', '');
    }
}
