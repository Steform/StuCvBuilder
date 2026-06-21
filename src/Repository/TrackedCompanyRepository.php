<?php

declare(strict_types=1);

namespace App\Repository;

use App\Employment\CompanyArchivedFilter;
use App\Employment\CompanyConsultationLevel;
use App\Employment\EmploymentDocumentKind;
use App\Entity\TrackedCompany;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TrackedCompany>
 */
class TrackedCompanyRepository extends ServiceEntityRepository
{
    /**
     * @brief Build tracked company repository.
     *
     * @param ManagerRegistry $registry Doctrine registry.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TrackedCompany::class);
    }

    /**
     * @brief Find company by exact code.
     *
     * @param string $code Company code.
     * @return TrackedCompany|null
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function findOneByCode(string $code): ?TrackedCompany
    {
        return $this->findOneBy(['code' => $code]);
    }

    /**
     * @brief Find active (non-archived) company by code.
     *
     * @param string $code Company code.
     * @return TrackedCompany|null
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function findActiveByCode(string $code): ?TrackedCompany
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.code = :code')
            ->andWhere('c.archivedAt IS NULL')
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @brief Find active company with optional CV document variant eager-loaded.
     *
     * @param string $code Company code.
     * @return TrackedCompany|null
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function findActiveByCodeWithCvVariant(string $code): ?TrackedCompany
    {
        return $this->findActiveByCodeWithDocumentVariants($code);
    }

    /**
     * @brief Find active company with CV and LM document variants eager-loaded.
     *
     * @param string $code Company code.
     * @return TrackedCompany|null
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function findActiveByCodeWithDocumentVariants(string $code): ?TrackedCompany
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.cvDocumentVariant', 'cvVariant')->addSelect('cvVariant')
            ->leftJoin('c.lmDocumentVariant', 'lmVariant')->addSelect('lmVariant')
            ->andWhere('c.code = :code')
            ->andWhere('c.archivedAt IS NULL')
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @brief Paginated admin list with filters.
     *
     * @param string $search Normalized search query.
     * @param string $countryCode Country filter or empty.
     * @param string $archivedFilter One of active|archived|all.
     * @param string $sort One of name|code|country|last_visit|created.
     * @param string $sortDirection asc or desc.
     * @param int $page Page number 1-based.
     * @param int $perPage Page size.
     * @return array{items: list<TrackedCompany>, total: int}
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function findForAdminList(
        string $search,
        string $countryCode,
        string $archivedFilter,
        string $sort,
        string $sortDirection,
        int $page,
        int $perPage,
    ): array {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.cvDocumentVariant', 'cvVariant')->addSelect('cvVariant')
            ->leftJoin('c.lmDocumentVariant', 'lmVariant')->addSelect('lmVariant');

        $archivedFilter = CompanyArchivedFilter::normalize($archivedFilter);
        if ($archivedFilter === CompanyArchivedFilter::ARCHIVED) {
            $qb->andWhere('c.archivedAt IS NOT NULL');
        } elseif ($archivedFilter === CompanyArchivedFilter::ACTIVE) {
            $qb->andWhere('c.archivedAt IS NULL');
        }

        if ($countryCode !== '') {
            $qb->andWhere('c.countryCode = :countryCode')
                ->setParameter('countryCode', strtoupper($countryCode));
        }

        if ($search !== '') {
            $qb->andWhere('c.nameNormalized LIKE :search OR c.code LIKE :searchExact')
                ->setParameter('search', '%'.$search.'%')
                ->setParameter('searchExact', '%'.$search.'%');
        }

        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(c.id)')->getQuery()->getSingleScalarResult();

        $this->applyAdminListOrdering($qb, $sort, $sortDirection);

        $items = $qb
            ->setFirstResult(max(0, ($page - 1) * $perPage))
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @brief Apply ORDER BY for admin company list.
     *
     * @param QueryBuilder $qb Company list query builder.
     * @param string $sort Sort field key.
     * @param string $sortDirection asc or desc.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function applyAdminListOrdering(QueryBuilder $qb, string $sort, string $sortDirection): void
    {
        $direction = strtoupper($sortDirection) === 'ASC' ? 'ASC' : 'DESC';

        if ($sort === 'last_visit') {
            $qb->addSelect(
                '('.$this->getEntityManager()->createQueryBuilder()
                    ->select('MAX(vsort.lastActivityAt)')
                    ->from('App\Entity\CompanyCvVisit', 'vsort')
                    ->where('vsort.company = c')
                    ->getDQL().') AS HIDDEN sortLastVisit'
            )
                ->orderBy('sortLastVisit', $direction)
                ->addOrderBy('c.name', 'ASC');

            return;
        }

        $field = match ($sort) {
            'name' => 'c.name',
            'code' => 'c.code',
            'country' => 'c.countryCode',
            default => 'c.createdAt',
        };

        $qb->orderBy($field, $direction);

        if ($sort === 'country') {
            $qb->addOrderBy('c.name', 'ASC');
        }
    }

    /**
     * @brief Get last official visit timestamp per company id.
     *
     * @param list<int> $companyIds Company identifiers.
     * @return array<int, \DateTimeImmutable>
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function findLastVisitAtByCompanyIds(array $companyIds): array
    {
        if ($companyIds === []) {
            return [];
        }

        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(v.company) AS companyId', 'MAX(v.lastActivityAt) AS lastVisit')
            ->from('App\Entity\CompanyCvVisit', 'v')
            ->andWhere('v.company IN (:ids)')
            ->setParameter('ids', $companyIds)
            ->groupBy('v.company')
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            $id = (int) ($row['companyId'] ?? 0);
            $last = $row['lastVisit'] ?? null;
            if ($id > 0 && $last instanceof \DateTimeImmutable) {
                $map[$id] = $last;
            } elseif ($id > 0 && is_string($last)) {
                $map[$id] = new \DateTimeImmutable($last);
            }
        }

        return $map;
    }

    /**
     * @brief Resolve consultation level per company for admin list badges.
     *
     * Level 0: no linked connection attempt.
     * Level 1: known-format attempt(s) without gate passed.
     * Level 2: at least one official visit (gate passed).
     *
     * @param list<int> $companyIds Company identifiers.
     * @return array<int, int> Map companyId => level (0|1|2).
     * @date 2026-06-17
     * @author Stephane H.
     */
    public function findConsultationLevelByCompanyIds(array $companyIds): array
    {
        if ($companyIds === []) {
            return [];
        }

        $levels = [];
        foreach ($companyIds as $companyId) {
            $levels[(int) $companyId] = CompanyConsultationLevel::NONE;
        }

        $companyRows = $this->createQueryBuilder('c')
            ->select('c.id', 'c.code')
            ->andWhere('c.id IN (:ids)')
            ->setParameter('ids', $companyIds)
            ->getQuery()
            ->getArrayResult();

        $idByCode = [];
        $codes = [];
        foreach ($companyRows as $row) {
            $id = (int) ($row['id'] ?? 0);
            $code = (string) ($row['code'] ?? '');
            if ($id < 1 || $code === '') {
                continue;
            }

            $idByCode[$code] = $id;
            $codes[] = $code;
        }

        if ($codes === []) {
            return $levels;
        }

        $officialRows = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(v.company) AS companyId')
            ->from('App\Entity\CompanyCvVisit', 'v')
            ->andWhere('v.company IN (:ids)')
            ->setParameter('ids', $companyIds)
            ->groupBy('v.company')
            ->getQuery()
            ->getArrayResult();

        foreach ($officialRows as $row) {
            $id = (int) ($row['companyId'] ?? 0);
            if ($id > 0) {
                $levels[$id] = CompanyConsultationLevel::OFFICIAL;
            }
        }

        $attemptRows = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(log.company) AS companyId', 'log.companyCodeSnapshot AS codeSnapshot', 'log.formatRaw AS formatRaw')
            ->from('App\Entity\CvConnectionLog', 'log')
            ->andWhere('log.gatePassed = false')
            ->andWhere('log.countableForCompany = false')
            ->andWhere('log.company IN (:ids) OR log.companyCodeSnapshot IN (:codes) OR log.formatRaw IN (:codes)')
            ->setParameter('ids', $companyIds)
            ->setParameter('codes', $codes)
            ->getQuery()
            ->getArrayResult();

        foreach ($attemptRows as $row) {
            $candidateIds = [];
            $linkedId = (int) ($row['companyId'] ?? 0);
            if ($linkedId > 0) {
                $candidateIds[] = $linkedId;
            }

            $snapshot = (string) ($row['codeSnapshot'] ?? '');
            if ($snapshot !== '' && isset($idByCode[$snapshot])) {
                $candidateIds[] = $idByCode[$snapshot];
            }

            $formatRaw = (string) ($row['formatRaw'] ?? '');
            if ($formatRaw !== '' && isset($idByCode[$formatRaw])) {
                $candidateIds[] = $idByCode[$formatRaw];
            }

            foreach (array_unique($candidateIds) as $candidateId) {
                if (($levels[$candidateId] ?? CompanyConsultationLevel::NONE) === CompanyConsultationLevel::NONE) {
                    $levels[$candidateId] = CompanyConsultationLevel::ATTEMPT_WITHOUT_GATE;
                }
            }
        }

        return $levels;
    }

    /**
     * @brief Count active companies referencing a CV document variant.
     *
     * @param int $variantId CV variant primary key.
     * @return int
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function countActiveReferencingCvVariant(int $variantId): int
    {
        if ($variantId < 1) {
            return 0;
        }

        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.archivedAt IS NULL')
            ->andWhere('c.cvDocumentVariant = :variantId')
            ->setParameter('variantId', $variantId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @brief Find first active company code referencing a document variant.
     *
     * @param int $variantId Document variant primary key.
     * @param string $kind cv or lm.
     * @return string|null Company format code or null when none.
     * @date 2026-06-12
     * @author Stephane H.
     */
    public function findFirstActiveCodeReferencingVariant(int $variantId, string $kind): ?string
    {
        if ($variantId < 1) {
            return null;
        }

        $qb = $this->createQueryBuilder('c')
            ->select('c.code')
            ->andWhere('c.archivedAt IS NULL')
            ->orderBy('c.code', 'ASC')
            ->setMaxResults(1)
            ->setParameter('variantId', $variantId);

        if ($kind === EmploymentDocumentKind::CV) {
            $qb->andWhere('c.cvDocumentVariant = :variantId');
        } elseif ($kind === EmploymentDocumentKind::LM) {
            $qb->andWhere('c.lmDocumentVariant = :variantId');
        } else {
            return null;
        }

        $result = $qb->getQuery()->getOneOrNullResult();
        if (!is_array($result)) {
            return null;
        }

        $code = $result['code'] ?? null;

        return is_string($code) && $code !== '' ? $code : null;
    }

    /**
     * @brief Count active companies referencing an LM document variant.
     *
     * @param int $variantId LM variant primary key.
     * @return int
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function countActiveReferencingLmVariant(int $variantId): int
    {
        if ($variantId < 1) {
            return 0;
        }

        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.archivedAt IS NULL')
            ->andWhere('c.lmDocumentVariant = :variantId')
            ->setParameter('variantId', $variantId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @brief Reassign active companies from one variant to another for a document kind.
     *
     * @param string $kind cv or lm.
     * @param int $fromVariantId Source variant id.
     * @param int $toVariantId Replacement variant id.
     * @return int Number of updated company rows.
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function reassignActiveCompaniesDocumentVariant(string $kind, int $fromVariantId, int $toVariantId): int
    {
        if ($fromVariantId < 1 || $toVariantId < 1 || $fromVariantId === $toVariantId) {
            return 0;
        }

        $field = match ($kind) {
            EmploymentDocumentKind::CV => 'c.cvDocumentVariant',
            EmploymentDocumentKind::LM => 'c.lmDocumentVariant',
            default => null,
        };

        if ($field === null) {
            return 0;
        }

        return (int) $this->createQueryBuilder('c')
            ->update()
            ->set($field, ':toVariantId')
            ->andWhere('c.archivedAt IS NULL')
            ->andWhere($field.' = :fromVariantId')
            ->setParameter('toVariantId', $toVariantId)
            ->setParameter('fromVariantId', $fromVariantId)
            ->getQuery()
            ->execute();
    }
}
