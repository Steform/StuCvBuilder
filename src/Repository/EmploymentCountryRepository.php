<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\EmploymentCountry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EmploymentCountry>
 */
class EmploymentCountryRepository extends ServiceEntityRepository
{
    /**
     * @brief Build employment country repository.
     *
     * @param ManagerRegistry $registry Doctrine registry.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmploymentCountry::class);
    }

    /**
     * @brief List countries ordered by label for admin selects.
     *
     * @param void No input parameter.
     * @return list<EmploymentCountry>
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function findAllOrderedByLabel(): array
    {
        /** @var list<EmploymentCountry> $items */
        $items = $this->createQueryBuilder('c')
            ->orderBy('c.label', 'ASC')
            ->addOrderBy('c.code', 'ASC')
            ->getQuery()
            ->getResult();

        return $items;
    }

    /**
     * @brief Find country by ISO code.
     *
     * @param string $code ISO 3166-1 alpha-2 code.
     * @return EmploymentCountry|null
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function findOneByCode(string $code): ?EmploymentCountry
    {
        $upper = strtoupper(trim($code));
        if ($upper === '') {
            return null;
        }

        return $this->findOneBy(['code' => $upper]);
    }
}
