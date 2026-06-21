<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CompanyCvSectionOverride;
use App\Entity\TrackedCompany;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CompanyCvSectionOverride>
 */
class CompanyCvSectionOverrideRepository extends ServiceEntityRepository
{
    /**
     * @brief Wire repository for {@see CompanyCvSectionOverride}.
     *
     * @param ManagerRegistry $registry Doctrine registry.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CompanyCvSectionOverride::class);
    }

    /**
     * @brief Load override row for one company section.
     *
     * @param TrackedCompany $company Tracked company.
     * @param string $sectionKey Section slug.
     * @return CompanyCvSectionOverride|null
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function findOneForCompanySection(TrackedCompany $company, string $sectionKey): ?CompanyCvSectionOverride
    {
        return $this->findOneBy([
            'trackedCompany' => $company,
            'sectionKey' => $sectionKey,
        ]);
    }

    /**
     * @brief List customized section keys for a company.
     *
     * @param TrackedCompany $company Tracked company.
     * @return list<string>
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function findSectionKeysForCompany(TrackedCompany $company): array
    {
        /** @var list<array{sectionKey: string}> $rows */
        $rows = $this->createQueryBuilder('o')
            ->select('o.sectionKey')
            ->andWhere('o.trackedCompany = :company')
            ->setParameter('company', $company)
            ->getQuery()
            ->getArrayResult();

        $keys = [];
        foreach ($rows as $row) {
            $keys[] = $row['sectionKey'];
        }

        return $keys;
    }
}
