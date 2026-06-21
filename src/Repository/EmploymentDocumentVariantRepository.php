<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\EmploymentDocumentVariant;
use App\Employment\EmploymentDocumentKind;
use App\Service\Util\LikeSearchEscaper;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EmploymentDocumentVariant>
 */
class EmploymentDocumentVariantRepository extends ServiceEntityRepository
{
    /**
     * @brief Build employment document variant repository.
     *
     * @param ManagerRegistry $registry Doctrine registry.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmploymentDocumentVariant::class);
    }

    /**
     * @brief Paginated admin list with filters and sort.
     *
     * @param string $kind cv or lm.
     * @param string $search Normalized search query.
     * @param bool $includeArchived Include archived variants.
     * @param string|null $createdAfter Inclusive start date Y-m-d or null.
     * @param string|null $createdBefore Inclusive end date Y-m-d or null.
     * @param string|null $updatedAfter Inclusive start date Y-m-d or null.
     * @param string|null $updatedBefore Inclusive end date Y-m-d or null.
     * @param string $sort name|created|updated.
     * @param string $sortDirection asc|desc.
     * @param int $page Page number 1-based.
     * @param int $perPage Page size.
     * @return array{items: list<EmploymentDocumentVariant>, total: int}
     * @date 2026-06-01
     * @author Stephane H.
     */
    /**
     * @brief List active variants for company assignment selects.
     *
     * @param string $kind cv or lm.
     * @return list<EmploymentDocumentVariant>
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function findActiveByKindForCompanySelect(string $kind): array
    {
        if (!EmploymentDocumentKind::isValid($kind)) {
            return [];
        }

        $qb = $this->createQueryBuilder('v')
            ->andWhere('v.kind = :kind')
            ->andWhere('v.archivedAt IS NULL')
            ->setParameter('kind', $kind);

        return $qb
            ->orderBy('v.isDefault', 'DESC')
            ->addOrderBy('v.nameNormalized', 'ASC')
            ->addOrderBy('v.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @brief Find first active variant of a kind excluding one id.
     *
     * @param string $kind cv or lm.
     * @param int $excludedVariantId Variant id to exclude.
     * @return EmploymentDocumentVariant|null
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function findFirstActiveByKindExcludingId(string $kind, int $excludedVariantId): ?EmploymentDocumentVariant
    {
        if (!EmploymentDocumentKind::isValid($kind) || $excludedVariantId < 1) {
            return null;
        }

        return $this->createQueryBuilder('v')
            ->andWhere('v.kind = :kind')
            ->andWhere('v.archivedAt IS NULL')
            ->andWhere('v.id != :excludedVariantId')
            ->setParameter('kind', $kind)
            ->setParameter('excludedVariantId', $excludedVariantId)
            ->orderBy('v.isDefault', 'DESC')
            ->addOrderBy('v.createdAt', 'ASC')
            ->addOrderBy('v.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @brief Clear default flag on all variants of a kind except one optional id.
     *
     * @param string $kind cv or lm.
     * @param int|null $exceptVariantId Variant id to keep as default, or null to clear all.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function clearDefaultForKindExcept(string $kind, ?int $exceptVariantId = null): void
    {
        if (!EmploymentDocumentKind::isValid($kind)) {
            return;
        }

        $qb = $this->createQueryBuilder('v')
            ->update()
            ->set('v.isDefault', ':false')
            ->where('v.kind = :kind')
            ->andWhere('v.isDefault = true')
            ->setParameter('false', false)
            ->setParameter('kind', $kind);

        if ($exceptVariantId !== null && $exceptVariantId > 0) {
            $qb->andWhere('v.id != :exceptId')
                ->setParameter('exceptId', $exceptVariantId);
        }

        $qb->getQuery()->execute();
    }

    /**
     * @brief Clear default flag on all CV variants except one optional id.
     *
     * @param int|null $exceptVariantId Variant id to keep as default, or null to clear all.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function clearDefaultCvExcept(?int $exceptVariantId = null): void
    {
        $this->clearDefaultForKindExcept(EmploymentDocumentKind::CV, $exceptVariantId);
    }

    /**
     * @brief Find active default variant for a document kind.
     *
     * @param string $kind cv or lm.
     * @return EmploymentDocumentVariant|null
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function findDefaultByKind(string $kind): ?EmploymentDocumentVariant
    {
        if (!EmploymentDocumentKind::isValid($kind)) {
            return null;
        }

        return $this->createQueryBuilder('v')
            ->andWhere('v.kind = :kind')
            ->andWhere('v.isDefault = true')
            ->andWhere('v.archivedAt IS NULL')
            ->setParameter('kind', $kind)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @brief Find active default CV variant if any.
     *
     * @return EmploymentDocumentVariant|null
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function findDefaultCv(): ?EmploymentDocumentVariant
    {
        return $this->findDefaultByKind(EmploymentDocumentKind::CV);
    }

    /**
     * @brief Find active default LM variant if any.
     *
     * @return EmploymentDocumentVariant|null
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function findDefaultLm(): ?EmploymentDocumentVariant
    {
        return $this->findDefaultByKind(EmploymentDocumentKind::LM);
    }

    /**
     * @brief Count non-archived variants for a document kind.
     *
     * @param string $kind cv or lm.
     * @return int
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function countActiveByKind(string $kind): int
    {
        if (!EmploymentDocumentKind::isValid($kind)) {
            return 0;
        }

        return (int) $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->andWhere('v.kind = :kind')
            ->andWhere('v.archivedAt IS NULL')
            ->setParameter('kind', $kind)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @brief Find active variant by id and expected kind.
     *
     * @param int $id Variant id.
     * @param string $kind cv or lm.
     * @return EmploymentDocumentVariant|null
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function findActiveByIdAndKind(int $id, string $kind): ?EmploymentDocumentVariant
    {
        if ($id < 1 || !EmploymentDocumentKind::isValid($kind)) {
            return null;
        }

        return $this->createQueryBuilder('v')
            ->andWhere('v.id = :id')
            ->andWhere('v.kind = :kind')
            ->andWhere('v.archivedAt IS NULL')
            ->setParameter('id', $id)
            ->setParameter('kind', $kind)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @brief Load active variant with locale assets for public PDF resolution.
     *
     * @param int $id Variant primary key.
     * @param string $kind Expected kind (cv or lm).
     * @return EmploymentDocumentVariant|null
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function findActiveWithLocaleAssetsById(int $id, string $kind): ?EmploymentDocumentVariant
    {
        if ($id < 1 || !EmploymentDocumentKind::isValid($kind)) {
            return null;
        }

        return $this->createQueryBuilder('v')
            ->leftJoin('v.localeAssets', 'assets')->addSelect('assets')
            ->andWhere('v.id = :id')
            ->andWhere('v.kind = :kind')
            ->andWhere('v.archivedAt IS NULL')
            ->setParameter('id', $id)
            ->setParameter('kind', $kind)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @brief Paginated admin list with filters and sort.
     *
     * @param string $kind cv or lm.
     * @param string $search Normalized search query.
     * @param bool $includeArchived Include archived variants.
     * @param string|null $createdAfter Inclusive start date Y-m-d or null.
     * @param string|null $createdBefore Inclusive end date Y-m-d or null.
     * @param string|null $updatedAfter Inclusive start date Y-m-d or null.
     * @param string|null $updatedBefore Inclusive end date Y-m-d or null.
     * @param string $sort name|created|updated.
     * @param string $sortDirection asc|desc.
     * @param int $page Page number 1-based.
     * @param int $perPage Page size.
     * @return array{items: list<EmploymentDocumentVariant>, total: int}
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function findForAdminList(
        string $kind,
        string $search,
        bool $includeArchived,
        ?string $createdAfter,
        ?string $createdBefore,
        ?string $updatedAfter,
        ?string $updatedBefore,
        string $sort,
        string $sortDirection,
        int $page,
        int $perPage,
    ): array {
        $qb = $this->createQueryBuilder('v')
            ->andWhere('v.kind = :kind')
            ->setParameter('kind', $kind);

        if (!$includeArchived) {
            $qb->andWhere('v.archivedAt IS NULL');
        }

        if ($search !== '') {
            $qb->andWhere('v.nameNormalized LIKE :search')
                ->setParameter('search', '%'.LikeSearchEscaper::escape($search).'%');
        }

        $this->applyDateFilter($qb, 'v.createdAt', $createdAfter, $createdBefore);
        $this->applyDateFilter($qb, 'v.updatedAt', $updatedAfter, $updatedBefore);

        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(v.id)')->getQuery()->getSingleScalarResult();

        $this->applyAdminListOrdering($qb, $sort, $sortDirection);

        $items = $qb
            ->setFirstResult(max(0, ($page - 1) * $perPage))
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @brief Apply ORDER BY for admin document list.
     *
     * @param QueryBuilder $qb Variant list query builder.
     * @param string $sort Sort field key.
     * @param string $sortDirection asc or desc.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function applyAdminListOrdering(QueryBuilder $qb, string $sort, string $sortDirection): void
    {
        $direction = strtolower($sortDirection) === 'asc' ? 'ASC' : 'DESC';

        match ($sort) {
            'name' => $qb->orderBy('v.nameNormalized', $direction)->addOrderBy('v.id', 'DESC'),
            'updated' => $qb->orderBy('v.updatedAt', $direction)->addOrderBy('v.id', 'DESC'),
            default => $qb->orderBy('v.createdAt', $direction)->addOrderBy('v.id', 'DESC'),
        };
    }

    /**
     * @brief Add inclusive date range filters on a datetime field.
     *
     * @param QueryBuilder $qb Query builder.
     * @param string $field DQL field path.
     * @param string|null $after Inclusive start Y-m-d.
     * @param string|null $before Inclusive end Y-m-d.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function applyDateFilter(
        QueryBuilder $qb,
        string $field,
        ?string $after,
        ?string $before,
    ): void {
        if ($after !== null && $after !== '') {
            $start = DateTimeImmutable::createFromFormat('Y-m-d', $after);
            if ($start instanceof DateTimeImmutable) {
                $qb->andWhere($field.' >= :'.$this->parameterKey($field, 'after'))
                    ->setParameter($this->parameterKey($field, 'after'), $start->setTime(0, 0));
            }
        }

        if ($before !== null && $before !== '') {
            $end = DateTimeImmutable::createFromFormat('Y-m-d', $before);
            if ($end instanceof DateTimeImmutable) {
                $qb->andWhere($field.' <= :'.$this->parameterKey($field, 'before'))
                    ->setParameter($this->parameterKey($field, 'before'), $end->setTime(23, 59, 59));
            }
        }
    }

    /**
     * @brief Build unique DQL parameter name fragment.
     *
     * @param string $field DQL field path.
     * @param string $suffix after|before.
     * @return string
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function parameterKey(string $field, string $suffix): string
    {
        return str_replace(['.', '(', ')'], '_', $field).'_'.$suffix;
    }
}
