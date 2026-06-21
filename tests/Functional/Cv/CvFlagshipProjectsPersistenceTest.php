<?php

declare(strict_types=1);

namespace App\Tests\Functional\Cv;

use App\Cv\CvProfilePersistenceScope;
use App\Service\Cv\CvFlagshipProjectPreviewUploadService;
use App\Service\Cv\CvFlagshipProjectsSettingsService;
use App\Service\Cv\FlagshipProjectsContract;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * @brief Functional checks for flagship projects JSON persistence and service wiring.
 *
 * @date 2026-05-31
 * @author Stephane H.
 */
final class CvFlagshipProjectsPersistenceTest extends KernelTestCase
{
    /**
     * @brief Flagship services must be registered in the container.
     *
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function testFlagshipServicesAreRegistered(): void
    {
        self::bootKernel();

        self::assertInstanceOf(
            CvFlagshipProjectsSettingsService::class,
            static::getContainer()->get(CvFlagshipProjectsSettingsService::class)
        );
        self::assertInstanceOf(
            CvFlagshipProjectPreviewUploadService::class,
            static::getContainer()->get(CvFlagshipProjectPreviewUploadService::class)
        );
    }

    /**
     * @brief Serialized payload round-trip keeps flagship project rows and preview path.
     *
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function testFlagshipProjectsPayloadRoundTrip(): void
    {
        $service = new CvFlagshipProjectsSettingsService();
        $projectId = FlagshipProjectsContract::generateUuidV4();
        $previewPath = FlagshipProjectsContract::PREVIEW_IMAGE_PATH_PREFIX.'project-roundtrip-test.webp';
        $payload = [
            FlagshipProjectsContract::KEY_SECTION_ENABLED => true,
            FlagshipProjectsContract::KEY_ENTRIES_BY_LOCALE => [
                'fr' => [[
                    'id' => $projectId,
                    'sortOrder' => 0,
                    'title' => 'Demo project',
                    'description' => 'Description',
                    'tags' => ['PHP', 'Symfony'],
                    'previewAlt' => 'Preview alt',
                    'previewImagePath' => $previewPath,
                    'githubUrl' => 'https://github.com/example/demo',
                    'demoUrl' => 'https://example.com/demo/',
                    'isVisible' => true,
                ]],
            ],
        ];

        $sanitized = CvProfilePersistenceScope::sanitizeForPersistence($payload);
        $json = (string) json_encode($sanitized, JSON_UNESCAPED_UNICODE);
        $resolved = $service->resolveFromContentJson($json, ['fr'], 'fr', 'fr');

        self::assertTrue($resolved['hasPersistedMap']);
        self::assertCount(1, $resolved['projects']);
        self::assertSame('Demo project', $resolved['projects'][0]['title']);
        self::assertSame($previewPath, $resolved['projects'][0]['previewImagePath']);
    }

    /**
     * @brief Multiple persisted projects must resolve in sort order for the public CV.
     *
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function testMultipleFlagshipProjectsPayloadRoundTrip(): void
    {
        $service = new CvFlagshipProjectsSettingsService();
        $firstId = FlagshipProjectsContract::generateUuidV4();
        $secondId = FlagshipProjectsContract::generateUuidV4();
        $payload = [
            FlagshipProjectsContract::KEY_SECTION_ENABLED => true,
            FlagshipProjectsContract::KEY_ENTRIES_BY_LOCALE => [
                'fr' => [
                    [
                        'id' => $firstId,
                        'sortOrder' => 0,
                        'title' => 'Premier projet',
                        'description' => 'Description 1',
                        'tags' => ['PHP'],
                        'previewAlt' => 'Alt 1',
                        'previewImagePath' => FlagshipProjectsContract::FALLBACK_PREVIEW_IMAGE_PATH,
                        'githubUrl' => 'https://github.com/example/first',
                        'demoUrl' => null,
                        'isVisible' => true,
                    ],
                    [
                        'id' => $secondId,
                        'sortOrder' => 1,
                        'title' => 'Second projet',
                        'description' => 'Description 2',
                        'tags' => ['Symfony'],
                        'previewAlt' => 'Alt 2',
                        'previewImagePath' => FlagshipProjectsContract::FALLBACK_PREVIEW_IMAGE_PATH,
                        'githubUrl' => 'https://github.com/example/second',
                        'demoUrl' => 'https://example.com/second/',
                        'isVisible' => false,
                    ],
                ],
            ],
        ];

        $sanitized = CvProfilePersistenceScope::sanitizeForPersistence($payload);
        $json = (string) json_encode($sanitized, JSON_THROW_ON_ERROR);
        $resolved = $service->resolveFromContentJson($json, ['fr'], 'fr', 'fr');

        self::assertCount(2, $resolved['entriesByLocale']['fr']);
        self::assertCount(2, $resolved['canonicalProjects']);
        self::assertCount(1, $resolved['projects']);
        self::assertSame('Premier projet', $resolved['projects'][0]['title']);
        self::assertFalse($resolved['canonicalProjects'][1]['isVisible']);
    }

    /**
     * @brief Default project preview asset must exist as WebP only.
     *
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function testDefaultPreviewAssetExistsAsWebpOnly(): void
    {
        $projectRoot = dirname(__DIR__, 3);
        $defaultWebp = $projectRoot.'/public/'.FlagshipProjectsContract::DEFAULT_PROJECT_PREVIEW_IMAGE_PATH;
        $legacyStusliderWebp = $projectRoot.'/public/images/cv/projects/stuslider-demo.webp';
        $legacyStusliderPng = $projectRoot.'/public/images/cv/projects/stuslider-demo.png';

        self::assertFileExists($defaultWebp);
        self::assertFileDoesNotExist($legacyStusliderWebp);
        self::assertFileDoesNotExist($legacyStusliderPng);
    }
}
