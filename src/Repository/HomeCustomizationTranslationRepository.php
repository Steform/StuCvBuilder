<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\HomeCustomization;
use App\Entity\HomeCustomizationTranslation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository HomeCustomizationTranslationRepository.
 *
 * @extends ServiceEntityRepository<HomeCustomizationTranslation>
 */
class HomeCustomizationTranslationRepository extends ServiceEntityRepository
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
        parent::__construct($registry, HomeCustomizationTranslation::class);
    }

    /**
     * @brief Delete all intro translations for one home customization row.
     *
     * @param HomeCustomization $customization Parent customization singleton.
     * @return int Number of deleted translation rows.
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function deleteAllForCustomization(HomeCustomization $customization): int
    {
        return (int) $this->getEntityManager()
            ->createQuery('DELETE FROM App\Entity\HomeCustomizationTranslation t WHERE t.customization = :home')
            ->setParameter('home', $customization)
            ->execute();
    }
}
