<?php

namespace App\Repository;

use App\Entity\HomeCustomization;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository HomeCustomizationRepository.
 *
 * @extends ServiceEntityRepository<HomeCustomization>
 */
class HomeCustomizationRepository extends ServiceEntityRepository
{
    /**
     * @brief Build repository.
     * @param ManagerRegistry $registry Doctrine registry.
     * @return void
     * @date 2026-05-08
     * @author Stephane H.
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HomeCustomization::class);
    }

    /**
     * @brief Return singleton customization row or null if missing.
     * @return HomeCustomization|null
     * @date 2026-05-08
     * @author Stephane H.
     */
    public function getSingleton(): ?HomeCustomization
    {
        return $this->findOneBy([]);
    }
}
