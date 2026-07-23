<?php

namespace App\Repository;

use App\Entity\CvProfile;
use App\Entity\TrackedCompany;
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

    /**
     * @brief Find the site-wide global CV profile (no company binding).
     *
     * @return CvProfile|null Latest global profile when several exist.
     * @date 2026-07-23
     * @author Stephane H.
     */
    public function findGlobal(): ?CvProfile
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.trackedCompany IS NULL')
            ->orderBy('p.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @brief Find the CV profile clone bound to a tracked company.
     *
     * @param TrackedCompany $company Tracked company.
     * @return CvProfile|null
     * @date 2026-07-23
     * @author Stephane H.
     */
    public function findOneForCompany(TrackedCompany $company): ?CvProfile
    {
        return $this->findOneBy(['trackedCompany' => $company]);
    }

    /**
     * @brief List all company-bound CV profile clones.
     *
     * @return list<CvProfile>
     * @date 2026-07-23
     * @author Stephane H.
     */
    public function findAllCompanyProfiles(): array
    {
        /** @var list<CvProfile> $profiles */
        $profiles = $this->createQueryBuilder('p')
            ->andWhere('p.trackedCompany IS NOT NULL')
            ->orderBy('p.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $profiles;
    }
}
