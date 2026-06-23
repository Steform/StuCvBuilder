<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use App\Employment\CompanyConsultationLevel;
use App\Repository\TrackedCompanyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

/**
 * @brief Unit tests for tracked company consultation level aggregation.
 */
final class TrackedCompanyRepositoryConsultationLevelTest extends TestCase
{
    /**
     * @brief Official visits override gate-failed attempts and none stays none.
     *
     * @return void
     * @date 2026-06-17
     * @author Stephane H.
     */
    public function testFindConsultationLevelByCompanyIdsResolvesThreeLevels(): void
    {
        $repository = $this->createTestRepository(
            companyRows: [
                ['id' => 1, 'code' => 'CODEONE11111'],
                ['id' => 2, 'code' => 'CODETWO22222'],
                ['id' => 3, 'code' => 'CODETHREE333'],
            ],
            officialRows: [
                ['companyId' => 1],
            ],
            attemptRows: [
                ['companyId' => 0, 'codeSnapshot' => '', 'formatRaw' => 'CODETWO22222'],
                ['companyId' => 1, 'codeSnapshot' => 'CODEONE11111', 'formatRaw' => 'CODEONE11111'],
            ],
        );

        $levels = $repository->findConsultationLevelByCompanyIds([1, 2, 3]);

        self::assertSame(CompanyConsultationLevel::OFFICIAL, $levels[1]);
        self::assertSame(CompanyConsultationLevel::ATTEMPT_WITHOUT_GATE, $levels[2]);
        self::assertSame(CompanyConsultationLevel::NONE, $levels[3]);
    }

    /**
     * @brief Build repository test double with deterministic query outputs.
     *
     * @param list<array{id: int, code: string}> $companyRows Company id/code rows.
     * @param list<array{companyId: int}> $officialRows Official visit rows.
     * @param list<array{companyId: int, codeSnapshot: string, formatRaw: string}> $attemptRows Attempt rows.
     * @return TrackedCompanyRepository
     * @date 2026-06-17
     * @author Stephane H.
     */
    private function createTestRepository(
        array $companyRows,
        array $officialRows,
        array $attemptRows,
    ): TrackedCompanyRepository {
        $companyQuery = $this->createConfiguredMock(Query::class, [
            'getArrayResult' => $companyRows,
        ]);
        $companyQb = $this->createMock(QueryBuilder::class);
        $companyQb->method('select')->willReturnSelf();
        $companyQb->method('andWhere')->willReturnSelf();
        $companyQb->method('setParameter')->willReturnSelf();
        $companyQb->method('getQuery')->willReturn($companyQuery);

        $officialQuery = $this->createConfiguredMock(Query::class, [
            'getArrayResult' => $officialRows,
        ]);
        $officialQb = $this->createMock(QueryBuilder::class);
        $officialQb->method('select')->willReturnSelf();
        $officialQb->method('from')->willReturnSelf();
        $officialQb->method('andWhere')->willReturnSelf();
        $officialQb->method('setParameter')->willReturnSelf();
        $officialQb->method('groupBy')->willReturnSelf();
        $officialQb->method('getQuery')->willReturn($officialQuery);

        $attemptQuery = $this->createConfiguredMock(Query::class, [
            'getArrayResult' => $attemptRows,
        ]);
        $attemptQb = $this->createMock(QueryBuilder::class);
        $attemptQb->method('select')->willReturnSelf();
        $attemptQb->method('from')->willReturnSelf();
        $attemptQb->method('andWhere')->willReturnSelf();
        $attemptQb->method('setParameter')->willReturnSelf();
        $attemptQb->method('getQuery')->willReturn($attemptQuery);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(self::exactly(2))
            ->method('createQueryBuilder')
            ->willReturnOnConsecutiveCalls($officialQb, $attemptQb);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($entityManager);

        return new class($registry, $entityManager, $companyQb) extends TrackedCompanyRepository {
            public function __construct(
                ManagerRegistry $registry,
                private readonly EntityManagerInterface $testEntityManager,
                private readonly QueryBuilder $companyQb,
            ) {
                parent::__construct($registry);
            }

            public function createQueryBuilder($alias, $indexBy = null): QueryBuilder
            {
                return $this->companyQb;
            }

            public function getEntityManager(): EntityManagerInterface
            {
                return $this->testEntityManager;
            }
        };
    }
}
