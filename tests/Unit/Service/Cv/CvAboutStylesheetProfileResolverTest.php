<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Cv;

use App\Employment\CompanyCvContentMode;
use App\Entity\CvProfile;
use App\Entity\TrackedCompany;
use App\Repository\CvProfileRepository;
use App\Repository\TrackedCompanyRepository;
use App\Service\Cv\CvAboutStylesheetProfileResolver;
use PHPUnit\Framework\TestCase;

/**
 * @brief Unit tests for About stylesheet profile resolution (global vs company custom).
 */
final class CvAboutStylesheetProfileResolverTest extends TestCase
{
    /**
     * @brief Without company code, global profile JSON is returned.
     *
     * @return void
     * @date 2026-10-05
     * @author Stephane H.
     */
    public function testResolveFallsBackToGlobalWithoutCompanyCode(): void
    {
        $global = new CvProfile('Global', '{"source":"global"}');
        $cvProfileRepository = $this->createMock(CvProfileRepository::class);
        $cvProfileRepository->expects(self::once())->method('findGlobal')->willReturn($global);
        $cvProfileRepository->expects(self::never())->method('findOneForCompany');

        $resolver = new CvAboutStylesheetProfileResolver(
            $cvProfileRepository,
            $this->createMock(TrackedCompanyRepository::class),
        );

        self::assertSame('{"source":"global"}', $resolver->resolveContentJson(null));
    }

    /**
     * @brief Custom company returns its clone JSON.
     *
     * @return void
     * @date 2026-10-05
     * @author Stephane H.
     */
    public function testResolveUsesCompanyCloneWhenCustom(): void
    {
        $company = new TrackedCompany('Ab3xY9kLm2Qp', 'Acme');
        $company->setCvContentMode(CompanyCvContentMode::CUSTOM);
        $clone = new CvProfile('Acme', '{"source":"company"}');

        $trackedCompanyRepository = $this->createMock(TrackedCompanyRepository::class);
        $trackedCompanyRepository->method('findActiveByCode')->with('Ab3xY9kLm2Qp')->willReturn($company);

        $cvProfileRepository = $this->createMock(CvProfileRepository::class);
        $cvProfileRepository->expects(self::once())->method('findOneForCompany')->with($company)->willReturn($clone);
        $cvProfileRepository->expects(self::never())->method('findGlobal');

        $resolver = new CvAboutStylesheetProfileResolver($cvProfileRepository, $trackedCompanyRepository);

        self::assertSame('{"source":"company"}', $resolver->resolveContentJson('Ab3xY9kLm2Qp'));
    }

    /**
     * @brief Synced company still uses global JSON.
     *
     * @return void
     * @date 2026-10-05
     * @author Stephane H.
     */
    public function testResolveUsesGlobalWhenCompanyIsSynced(): void
    {
        $company = new TrackedCompany('Ab3xY9kLm2Qp', 'Acme');
        $global = new CvProfile('Global', '{"source":"global"}');

        $trackedCompanyRepository = $this->createMock(TrackedCompanyRepository::class);
        $trackedCompanyRepository->method('findActiveByCode')->willReturn($company);

        $cvProfileRepository = $this->createMock(CvProfileRepository::class);
        $cvProfileRepository->expects(self::never())->method('findOneForCompany');
        $cvProfileRepository->expects(self::once())->method('findGlobal')->willReturn($global);

        $resolver = new CvAboutStylesheetProfileResolver($cvProfileRepository, $trackedCompanyRepository);

        self::assertSame('{"source":"global"}', $resolver->resolveContentJson('Ab3xY9kLm2Qp'));
    }
}
