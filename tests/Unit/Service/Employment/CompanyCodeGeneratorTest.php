<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Employment;

use App\Repository\TrackedCompanyRepository;
use App\Service\Employment\CompanyCodeGenerator;
use App\Service\Employment\CompanyCodeNormalizer;
use PHPUnit\Framework\TestCase;

class CompanyCodeGeneratorTest extends TestCase
{
    /**
     * @brief Generated codes are 12 alphanumeric characters and unique.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testGenerateProducesValidUniqueCode(): void
    {
        $repository = $this->createMock(TrackedCompanyRepository::class);
        $repository->method('findOneByCode')->willReturn(null);

        $generator = new CompanyCodeGenerator($repository);
        $code = $generator->generate();

        self::assertSame(12, strlen($code));
        self::assertNotSame('', (new CompanyCodeNormalizer())->normalize($code));
    }
}
