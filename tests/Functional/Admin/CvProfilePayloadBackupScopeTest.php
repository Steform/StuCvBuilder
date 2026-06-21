<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Cv\AboutSectionPatternCustomizationContract;
use App\Cv\CvProfilePersistenceScope;
use App\Cv\SectionTransitionContract;
use App\Service\Customization\CustomizationBackupExportService;
use App\Service\Cv\FlagshipProjectsContract;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * @brief Ensure CV backup export applies {@see CvProfilePersistenceScope} sanitization.
 *
 * @date 2026-05-27
 * @author Stephane H.
 */
final class CvProfilePayloadBackupScopeTest extends KernelTestCase
{
    /**
     * @brief Export service source must sanitize CV profile JSON before archiving.
     *
     * @return void
     * @date 2026-05-27
     * @author Stephane H.
     */
    public function testExportServiceUsesCvProfilePersistenceScope(): void
    {
        $source = @file_get_contents(dirname(__DIR__, 3).'/src/Service/Customization/CustomizationBackupExportService.php') ?: '';
        self::assertStringContainsString('CvProfilePersistenceScope::sanitizeForPersistence', $source);
    }

    /**
     * @brief Sanitizer must drop legacy About atmosphere keys and keep pattern customization.
     *
     * @return void
     * @date 2026-05-27
     * @author Stephane H.
     */
    public function testSanitizeStripsAtmosphereAndKeepsPatternForBackupShape(): void
    {
        $sanitized = CvProfilePersistenceScope::sanitizeForPersistence([
            'aboutSectionAtmosphereStyle' => 'style_2',
            'aboutSectionAtmosphereCssSanitized' => 'opacity: 1;',
            SectionTransitionContract::KEY => ['situation' => 'fade_vertical'],
            AboutSectionPatternCustomizationContract::KEY => [
                'baseColor' => '#aabbcc',
                'toneMixPercent' => ['tone1' => 0, 'tone2' => 5, 'tone3' => 20, 'tone4' => 40],
                'surfaceMixPercent' => 50,
            ],
        ]);

        self::assertArrayNotHasKey('aboutSectionAtmosphereStyle', $sanitized);
        self::assertArrayNotHasKey(SectionTransitionContract::KEY, $sanitized);
        self::assertSame('#aabbcc', $sanitized[AboutSectionPatternCustomizationContract::KEY]['baseColor']);
    }

    /**
     * @brief Flagship project rows must survive backup sanitization unchanged.
     *
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function testSanitizeKeepsFlagshipProjectsForBackupShape(): void
    {
        $projectId = FlagshipProjectsContract::generateUuidV4();
        $sanitized = CvProfilePersistenceScope::sanitizeForPersistence([
            FlagshipProjectsContract::KEY_SECTION_ENABLED => true,
            FlagshipProjectsContract::KEY_ENTRIES_BY_LOCALE => [
                'fr' => [[
                    'id' => $projectId,
                    'title' => 'Backup flagship',
                    'description' => 'Persisted for restore',
                    'tags' => ['WebP'],
                    'previewAlt' => 'Preview',
                    'previewImagePath' => FlagshipProjectsContract::PREVIEW_IMAGE_PATH_PREFIX.'project-backup-test.webp',
                    'githubUrl' => 'https://github.com/example/backup',
                    'demoUrl' => null,
                    'isVisible' => true,
                ]],
            ],
        ]);

        self::assertTrue($sanitized[FlagshipProjectsContract::KEY_SECTION_ENABLED]);
        self::assertSame('Backup flagship', $sanitized[FlagshipProjectsContract::KEY_ENTRIES_BY_LOCALE]['fr'][0]['title']);
    }
}
