<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\HomeCustomization;
use App\Entity\HomeQuickTile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HomeQuickTile>
 */
class HomeQuickTileRepository extends ServiceEntityRepository
{
    /**
     * @brief Build repository for home quick tiles.
     * @param ManagerRegistry $registry Doctrine registry.
     * @return void
     * @date 2026-05-18
     * @author Stephane H.
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HomeQuickTile::class);
    }

    /**
     * @brief Load enabled tiles ordered for public home rendering.
     * @param HomeCustomization $customization Parent customization singleton.
     * @return list<HomeQuickTile>
     * @date 2026-05-18
     * @author Stephane H.
     */
    public function findEnabledOrdered(HomeCustomization $customization): array
    {
        /** @var list<HomeQuickTile> $tiles */
        $tiles = $this->createQueryBuilder('t')
            ->andWhere('t.customization = :customization')
            ->andWhere('t.enabled = true')
            ->setParameter('customization', $customization)
            ->orderBy('t.sortOrder', 'ASC')
            ->addOrderBy('t.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $tiles;
    }

    /**
     * @brief Load all tiles for admin management (including disabled).
     * @param HomeCustomization $customization Parent customization singleton.
     * @return list<HomeQuickTile>
     * @date 2026-05-18
     * @author Stephane H.
     */
    public function findAllOrdered(HomeCustomization $customization): array
    {
        /** @var list<HomeQuickTile> $tiles */
        $tiles = $this->createQueryBuilder('t')
            ->andWhere('t.customization = :customization')
            ->setParameter('customization', $customization)
            ->orderBy('t.sortOrder', 'ASC')
            ->addOrderBy('t.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $tiles;
    }

    /**
     * @brief Delete all quick tiles for one home customization row (tile translations cascade in DB).
     *
     * @param HomeCustomization $customization Parent customization singleton.
     * @return int Number of deleted tile rows.
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function deleteAllForCustomization(HomeCustomization $customization): int
    {
        return (int) $this->getEntityManager()
            ->createQuery('DELETE FROM App\Entity\HomeQuickTile t WHERE t.customization = :home')
            ->setParameter('home', $customization)
            ->execute();
    }
}
