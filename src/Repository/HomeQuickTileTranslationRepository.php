<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\HomeQuickTileTranslation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HomeQuickTileTranslation>
 */
class HomeQuickTileTranslationRepository extends ServiceEntityRepository
{
    /**
     * @brief Build repository for home quick tile translations.
     * @param ManagerRegistry $registry Doctrine registry.
     * @return void
     * @date 2026-05-18
     * @author Stephane H.
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HomeQuickTileTranslation::class);
    }
}
