<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\EmploymentDocumentLocaleAsset;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EmploymentDocumentLocaleAsset>
 */
class EmploymentDocumentLocaleAssetRepository extends ServiceEntityRepository
{
    /**
     * @brief Build employment document locale asset repository.
     *
     * @param ManagerRegistry $registry Doctrine registry.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmploymentDocumentLocaleAsset::class);
    }
}
