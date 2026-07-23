<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cv;

use App\Cv\CompanyCvAssetPaths;
use App\Service\Cv\ExperienceContract;
use PHPUnit\Framework\TestCase;

final class CompanyCvAssetPathsTest extends TestCase
{
    /**
     * @brief Purgeable experience logos rewrite under company/code while keeping the custom prefix.
     *
     * @return void
     * @date 2026-07-23
     * @author Stephane H.
     */
    public function testRewriteKeepsExperienceCustomPrefix(): void
    {
        $source = ExperienceContract::EXPERIENCE_LOGO_PATH_PREFIX.'logo.webp';
        $rewritten = CompanyCvAssetPaths::rewriteCustomizablePathForCompany($source, 'ABCDEFGHIJKL');

        self::assertSame(
            ExperienceContract::EXPERIENCE_LOGO_PATH_PREFIX.'company/ABCDEFGHIJKL/logo.webp',
            $rewritten
        );
        self::assertTrue(CompanyCvAssetPaths::isCompanyScopedPath((string) $rewritten, 'ABCDEFGHIJKL'));
    }
}
