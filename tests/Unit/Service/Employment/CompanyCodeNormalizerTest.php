<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Employment;

use App\Service\Employment\CompanyCodeNormalizer;
use PHPUnit\Framework\TestCase;

class CompanyCodeNormalizerTest extends TestCase
{
    /**
     * @brief Accept valid 12-character alphanumeric codes preserving case.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testNormalizeAcceptsValidCode(): void
    {
        $normalizer = new CompanyCodeNormalizer();
        self::assertSame('Ab3xY9kLm2Qp', $normalizer->normalize('Ab3xY9kLm2Qp'));
    }

    /**
     * @brief Reject wrong length and invalid characters.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testNormalizeRejectsInvalid(): void
    {
        $normalizer = new CompanyCodeNormalizer();
        self::assertSame('', $normalizer->normalize('short'));
        self::assertSame('', $normalizer->normalize('Ab3xY9kLm2Q!'));
        self::assertSame('', $normalizer->normalize('acme-corp-old'));
    }
}
