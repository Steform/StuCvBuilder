<?php

namespace App\Repository;

use App\Entity\CvProfile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository CvProfileRepository.
 *
 * @extends ServiceEntityRepository<CvProfile>
 */
class CvProfileRepository extends ServiceEntityRepository
{
    /**
     * @brief Build CV profile repository.
     * @param ManagerRegistry $registry Doctrine manager registry.
     * @return void
     * @date 2026-04-24
     * @author Stephane H.
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CvProfile::class);
    }
}
