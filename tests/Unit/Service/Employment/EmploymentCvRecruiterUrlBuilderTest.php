<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Employment;

use App\Entity\TrackedCompany;
use App\Employment\EmploymentDocumentKind;
use App\Repository\TrackedCompanyRepository;
use App\Service\Employment\EmploymentCvRecruiterUrlBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @brief Unit tests for recruiter CV URL builder used in PDF QR codes.
 */
final class EmploymentCvRecruiterUrlBuilderTest extends TestCase
{
    /**
     * @brief Build absolute CV URL without format when code is empty.
     *
     * @return void
     * @date 2026-06-12
     * @author Stephane H.
     */
    public function testBuildWithoutFormatUsesCvShowOnly(): void
    {
        $repository = $this->createMock(TrackedCompanyRepository::class);
        $repository->expects(self::never())->method('findActiveByCode');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->expects(self::once())
            ->method('generate')
            ->with('cv_show', [], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://example.test/cv/');

        $builder = new EmploymentCvRecruiterUrlBuilder($urlGenerator, $repository);

        self::assertSame('https://example.test/cv/', $builder->build(''));
    }

    /**
     * @brief Build absolute CV URL with format when company is active.
     *
     * @return void
     * @date 2026-06-12
     * @author Stephane H.
     */
    public function testBuildWithActiveCompanyFormat(): void
    {
        $repository = $this->createMock(TrackedCompanyRepository::class);
        $repository
            ->method('findActiveByCode')
            ->with('Ab3xY9kLm2Qp')
            ->willReturn(new TrackedCompany('Ab3xY9kLm2Qp', 'Acme', null));

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->expects(self::once())
            ->method('generate')
            ->with('cv_show', ['format' => 'Ab3xY9kLm2Qp'], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://example.test/cv/?format=Ab3xY9kLm2Qp');

        $builder = new EmploymentCvRecruiterUrlBuilder($urlGenerator, $repository);

        self::assertSame('https://example.test/cv/?format=Ab3xY9kLm2Qp', $builder->build('Ab3xY9kLm2Qp'));
    }

    /**
     * @brief Ignore unknown format codes and fall back to default CV URL.
     *
     * @return void
     * @date 2026-06-12
     * @author Stephane H.
     */
    public function testBuildIgnoresUnknownFormatCode(): void
    {
        $repository = $this->createMock(TrackedCompanyRepository::class);
        $repository->method('findActiveByCode')->with('unknown-code')->willReturn(null);

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->expects(self::once())
            ->method('generate')
            ->with('cv_show', [], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://example.test/cv/');

        $builder = new EmploymentCvRecruiterUrlBuilder($urlGenerator, $repository);

        self::assertSame('https://example.test/cv/', $builder->build('unknown-code'));
    }

    /**
     * @brief Build absolute LM PDF URL without format when code is empty.
     *
     * @return void
     * @date 2026-06-12
     * @author Stephane H.
     */
    public function testBuildLmWithoutFormatUsesLmPdfRoute(): void
    {
        $repository = $this->createMock(TrackedCompanyRepository::class);
        $repository->expects(self::never())->method('findActiveByCode');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->expects(self::once())
            ->method('generate')
            ->with('cv_lm_pdf', [], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://example.test/cv/lm-pdf');

        $builder = new EmploymentCvRecruiterUrlBuilder($urlGenerator, $repository);

        self::assertSame('https://example.test/cv/lm-pdf', $builder->build('', EmploymentDocumentKind::LM));
    }

    /**
     * @brief Build absolute LM PDF URL with format when company is active.
     *
     * @return void
     * @date 2026-06-12
     * @author Stephane H.
     */
    public function testBuildLmWithActiveCompanyFormat(): void
    {
        $repository = $this->createMock(TrackedCompanyRepository::class);
        $repository
            ->method('findActiveByCode')
            ->with('Ab3xY9kLm2Qp')
            ->willReturn(new TrackedCompany('Ab3xY9kLm2Qp', 'Acme', null));

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->expects(self::once())
            ->method('generate')
            ->with('cv_lm_pdf', ['format' => 'Ab3xY9kLm2Qp'], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://example.test/cv/lm-pdf?format=Ab3xY9kLm2Qp');

        $builder = new EmploymentCvRecruiterUrlBuilder($urlGenerator, $repository);

        self::assertSame(
            'https://example.test/cv/lm-pdf?format=Ab3xY9kLm2Qp',
            $builder->build('Ab3xY9kLm2Qp', EmploymentDocumentKind::LM),
        );
    }
}
