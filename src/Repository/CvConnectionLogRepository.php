<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CvConnectionLog;
use App\Entity\TrackedCompany;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CvConnectionLog>
 */
class CvConnectionLogRepository extends ServiceEntityRepository
{
    /**
     * @brief Build CV connection log repository.
     *
     * @param ManagerRegistry $registry Doctrine registry.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CvConnectionLog::class);
    }

    /**
     * @brief Paginated admin connection log list.
     *
     * @param array<string, mixed> $filters Filter map.
     * @param int $page Page 1-based.
     * @param int $perPage Page size.
     * @return array{items: list<CvConnectionLog>, total: int}
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function findForAdminList(array $filters, int $page, int $perPage): array
    {
        $qb = $this->createQueryBuilder('log')
            ->leftJoin('log.company', 'company')
            ->addSelect('company')
            ->orderBy('log.occurredAt', 'DESC');

        $kind = (string) ($filters['kind'] ?? '');
        if ($kind !== '') {
            $qb->andWhere('log.connectionKind = :kind')->setParameter('kind', $kind);
        }

        $country = (string) ($filters['country'] ?? '');
        if ($country !== '') {
            $qb->andWhere('log.countryCode = :country')->setParameter('country', strtoupper($country));
        }

        $companyId = (int) ($filters['companyId'] ?? 0);
        if ($companyId > 0) {
            $qb->andWhere('log.company = :companyId')->setParameter('companyId', $companyId);
        }

        $format = trim((string) ($filters['format'] ?? ''));
        if ($format !== '') {
            $qb->andWhere('log.formatRaw LIKE :format OR log.companyCodeSnapshot LIKE :format')
                ->setParameter('format', '%'.$format.'%');
        }

        $ip = trim((string) ($filters['ip'] ?? ''));
        if ($ip !== '') {
            $qb->andWhere('log.ipAddress LIKE :ip')->setParameter('ip', '%'.$ip.'%');
        }

        if (($filters['gatePassed'] ?? '') === '1') {
            $qb->andWhere('log.gatePassed = true');
        } elseif (($filters['gatePassed'] ?? '') === '0') {
            $qb->andWhere('log.gatePassed = false');
        }

        if (($filters['countable'] ?? '') === '1') {
            $qb->andWhere('log.countableForCompany = true');
        } elseif (($filters['countable'] ?? '') === '0') {
            $qb->andWhere('log.countableForCompany = false');
        }

        $from = $filters['from'] ?? null;
        if ($from instanceof \DateTimeImmutable) {
            $qb->andWhere('log.occurredAt >= :from')->setParameter('from', $from);
        }

        $to = $filters['to'] ?? null;
        if ($to instanceof \DateTimeImmutable) {
            $qb->andWhere('log.occurredAt <= :to')->setParameter('to', $to);
        }

        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(log.id)')->getQuery()->getSingleScalarResult();

        $items = $qb
            ->setFirstResult(max(0, ($page - 1) * $perPage))
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @brief Find log with company for detail page.
     *
     * @param int $id Log id.
     * @return CvConnectionLog|null
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function findOneForAdminShow(int $id): ?CvConnectionLog
    {
        return $this->createQueryBuilder('log')
            ->leftJoin('log.company', 'company')
            ->addSelect('company')
            ->leftJoin('log.visit', 'visit')
            ->addSelect('visit')
            ->andWhere('log.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
