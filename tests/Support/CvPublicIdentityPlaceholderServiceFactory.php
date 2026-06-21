<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Service\Cv\CvAgeYearsCalculator;
use App\Service\Cv\CvPublicIdentityPlaceholderService;
use App\Service\Employment\EmploymentPublicDocumentPdfResolver;
use App\Service\RichText\RichHtmlSanitizer;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @brief Factory for {@see CvPublicIdentityPlaceholderService} in unit tests.
 * @date 2026-06-17
 * @author Stephane H.
 */
final class CvPublicIdentityPlaceholderServiceFactory
{
    /**
     * @brief Build placeholder service with a permissive or strict LM PDF resolver stub.
     *
     * @param bool $companyLmPdfAvailable When false, resolver returns null for company format codes.
     * @return CvPublicIdentityPlaceholderService
     * @date 2026-06-17
     * @author Stephane H.
     */
    public static function create(bool $companyLmPdfAvailable = true): CvPublicIdentityPlaceholderService
    {
        return self::createWithTranslator(CvPdfPlaceholderTestTranslator::create(), $companyLmPdfAvailable);
    }

    /**
     * @brief Build placeholder service with a custom translator for isolated tests.
     *
     * @param TranslatorInterface $translator Translator used by the placeholder service.
     * @param bool $companyLmPdfAvailable When false, resolver returns null for company format codes.
     * @return CvPublicIdentityPlaceholderService
     * @date 2026-06-17
     * @author Stephane H.
     */
    public static function createWithTranslator(TranslatorInterface $translator, bool $companyLmPdfAvailable = true): CvPublicIdentityPlaceholderService
    {
        return new CvPublicIdentityPlaceholderService(
            $translator,
            new RichHtmlSanitizer(),
            new CvAgeYearsCalculator(),
            self::createPdfResolverMock($companyLmPdfAvailable),
        );
    }

    /**
     * @brief Build LM PDF resolver mock for placeholder tests.
     *
     * @param bool $companyLmPdfAvailable When false, `resolveLm` returns null.
     * @return EmploymentPublicDocumentPdfResolver
     * @date 2026-06-17
     * @author Stephane H.
     */
    private static function createPdfResolverMock(bool $companyLmPdfAvailable): EmploymentPublicDocumentPdfResolver
    {
        /** @var EmploymentPublicDocumentPdfResolver&\PHPUnit\Framework\MockObject\MockObject $mock */
        $mock = TestCaseMockFactory::create(EmploymentPublicDocumentPdfResolver::class);
        $mock->method('resolveLm')->willReturn(
            $companyLmPdfAvailable
                ? ['absolutePath' => '/tmp/lm.pdf', 'downloadFilename' => 'lm.pdf']
                : null
        );

        return $mock;
    }
}
