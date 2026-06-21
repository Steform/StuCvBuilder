<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\EmploymentPrintPlacement;
use App\Employment\EmploymentDocumentKind;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EmploymentPrintPlacement>
 */
class EmploymentPrintPlacementRepository extends ServiceEntityRepository
{
    /**
     * @brief Build employment print placement repository.
     *
     * @param ManagerRegistry $registry Doctrine registry.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmploymentPrintPlacement::class);
    }

    /**
     * @brief Find placement row for document kind.
     *
     * @param string $kind cv or lm.
     * @return EmploymentPrintPlacement|null
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function findOneByKind(string $kind): ?EmploymentPrintPlacement
    {
        return $this->findOneBy(['kind' => $kind]);
    }

    /**
     * @brief Ensure default placement rows exist for CV and LM.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function ensureDefaultsExist(): void
    {
        $em = $this->getEntityManager();
        $defaults = [
            EmploymentDocumentKind::CV => ['2.50', '2.50', '2.00'],
            EmploymentDocumentKind::LM => ['2.50', '7.00', '2.00'],
        ];

        foreach ($defaults as $kind => [$x, $y, $sizeCm]) {
            if ($this->findOneByKind($kind) instanceof EmploymentPrintPlacement) {
                continue;
            }

            $em->persist(new EmploymentPrintPlacement($kind, $x, $y, $sizeCm));
        }

        $em->flush();
    }
}
