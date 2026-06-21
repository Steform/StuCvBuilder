<?php

namespace App\Repository;

use App\Entity\User;
use App\Service\Util\LikeSearchEscaper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Class UserRepository.
 *
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    /**
     * @brief Build user repository.
     * @param ManagerRegistry $registry Doctrine registry.
     * @return void
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * @brief Paginate users with optional search query.
     * @param int $page Current page number.
     * @param int $pageSize Page size.
     * @param string $searchTerm Optional search term.
     * @return array{items: list<User>, total: int}
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function paginateUsers(int $page, int $pageSize, string $searchTerm = ''): array
    {
        $safePage = max(1, $page);
        $safePageSize = max(1, min($pageSize, 200));
        $queryBuilder = $this->createQueryBuilder('app_user')
            ->orderBy('app_user.id', 'DESC');

        $normalizedSearch = trim($searchTerm);
        if ($normalizedSearch !== '') {
            $escaped = LikeSearchEscaper::escape($normalizedSearch);
            $queryBuilder
                ->andWhere('app_user.email LIKE :search OR app_user.pseudonym LIKE :search')
                ->setParameter('search', '%'.$escaped.'%');
        }

        $items = $queryBuilder
            ->setFirstResult(($safePage - 1) * $safePageSize)
            ->setMaxResults($safePageSize)
            ->getQuery()
            ->getResult();

        $countBuilder = $this->createQueryBuilder('app_user')
            ->select('COUNT(app_user.id)');
        if ($normalizedSearch !== '') {
            $escaped = LikeSearchEscaper::escape($normalizedSearch);
            $countBuilder
                ->andWhere('app_user.email LIKE :search OR app_user.pseudonym LIKE :search')
                ->setParameter('search', '%'.$escaped.'%');
        }

        return [
            'items' => $items,
            'total' => (int) $countBuilder->getQuery()->getSingleScalarResult(),
        ];
    }

    /**
     * @brief Count active administrators.
     * @param void No input parameter.
     * @return int
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function countActiveAdmins(): int
    {
        return (int) $this->createQueryBuilder('app_user')
            ->select('COUNT(app_user.id)')
            ->andWhere('app_user.active = :active')
            ->andWhere('JSON_CONTAINS(app_user.roles, :adminRole) = 1')
            ->setParameter('active', true)
            ->setParameter('adminRole', '["ROLE_ADMIN"]')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
