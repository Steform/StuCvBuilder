<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Employment;

use App\Employment\EmploymentDocumentKind;
use App\Service\Employment\EmploymentDocumentStorageService;
use PHPUnit\Framework\TestCase;

/**
 * @brief Unit tests for stamped PDF cache path naming.
 */
final class EmploymentDocumentStampedPdfCacheServiceTest extends TestCase
{
    /**
     * @brief Cache path uses current stamped PDF revision without age segment.
     *
     * @return void
     * @date 2026-06-15
     * @author Stephane H.
     */
    public function testBuildStampedRelativePathUsesCurrentRevision(): void
    {
        $storage = new EmploymentDocumentStorageService(dirname(__DIR__, 4));

        $cvPath = $storage->buildStampedRelativePath(EmploymentDocumentKind::CV, 7, 'fr', 'acme');
        $lmPath = $storage->buildStampedRelativePath(EmploymentDocumentKind::LM, 7, 'fr', 'acme');

        self::assertSame('var/employment_documents/cv/7/fr/stamped-acme-tight18.pdf', $cvPath);
        self::assertSame('var/employment_documents/lm/7/fr/stamped-acme-lm-tight18.pdf', $lmPath);
    }
}
