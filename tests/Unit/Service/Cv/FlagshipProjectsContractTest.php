<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Cv;

use App\Service\Cv\CvFlagshipProjectsSettingsService;
use App\Service\Cv\FlagshipProjectsContract;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @brief Unit tests for {@see FlagshipProjectsContract} and related flagship project resolution.
 *
 * @date 2026-05-31
 * @author Stephane H.
 */
final class FlagshipProjectsContractTest extends TestCase
{
    /**
     * @brief Missing persisted key must keep the section enabled on the public CV.
     *
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function testIsSectionEnabledFromPayloadDefaultsToTrueWhenUnset(): void
    {
        self::assertTrue(FlagshipProjectsContract::isSectionEnabledFromPayload([]));
    }

    /**
     * @brief Explicit false in payload must hide the section.
     *
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function testIsSectionEnabledFromPayloadRespectsFalse(): void
    {
        self::assertFalse(FlagshipProjectsContract::isSectionEnabledFromPayload([
            FlagshipProjectsContract::KEY_SECTION_ENABLED => false,
        ]));
    }

    /**
     * @brief Checkbox hidden field pair must resolve enabled and disabled states.
     *
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function testParseSectionEnabledFromRequest(): void
    {
        $enabledRequest = Request::create('/', 'POST', [
            'flagship_projects_section_enabled' => ['0', '1'],
        ]);
        $disabledRequest = Request::create('/', 'POST', [
            'flagship_projects_section_enabled' => '0',
        ]);

        self::assertTrue(FlagshipProjectsContract::parseSectionEnabledFromRequest($enabledRequest));
        self::assertFalse(FlagshipProjectsContract::parseSectionEnabledFromRequest($disabledRequest));
    }

    /**
     * @brief Project rows must normalize preview paths to WebP-only custom uploads or the fallback asset.
     *
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function testNormalizeEntryRejectsNonWebpCustomPreviewPath(): void
    {
        $entry = FlagshipProjectsContract::normalizeEntry([
            'id' => FlagshipProjectsContract::generateUuidV4(),
            'title' => 'StuSlider',
            'description' => 'Demo',
            'tags' => ['TypeScript'],
            'previewAlt' => 'Preview',
            'previewImagePath' => 'images/cv/projects/custom/project-test.png',
            'githubUrl' => 'https://github.com/example/repo',
            'demoUrl' => 'https://example.com/demo/',
            'isVisible' => true,
        ], 0);

        self::assertNotNull($entry);
        self::assertSame(FlagshipProjectsContract::DEFAULT_PROJECT_PREVIEW_IMAGE_PATH, $entry['previewImagePath']);
    }

    /**
     * @brief Resolver must return an empty project list when nothing is persisted.
     *
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function testSettingsServiceReturnsEmptyWhenUnset(): void
    {
        $service = new CvFlagshipProjectsSettingsService();
        $resolved = $service->resolveFromContentJson('{}', ['fr'], 'fr', 'fr');

        self::assertFalse($resolved['hasPersistedMap']);
        self::assertSame([], $resolved['projects']);
        self::assertSame([], $resolved['projectsFull']);
        self::assertFalse($resolved['hasSecondaryVisible']);
    }

    /**
     * @brief Canonical POST payload must expand into locale rows for multiple projects.
     *
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function testParseRawEntriesFromRequestSupportsMultipleProjects(): void
    {
        $firstId = FlagshipProjectsContract::generateUuidV4();
        $secondId = FlagshipProjectsContract::generateUuidV4();
        $request = Request::create('/', 'POST', [
            'flagship_projects' => [
                'entries' => [
                    $firstId => [
                        'sort_order' => '1',
                        'github_url' => 'https://github.com/example/first',
                        'demo_url' => 'https://example.com/first/',
                        'is_visible' => '1',
                        'preview_image_path' => FlagshipProjectsContract::DEFAULT_PROJECT_PREVIEW_IMAGE_PATH,
                        'locales' => [
                            'fr' => [
                                'title' => 'Projet A',
                                'description' => 'Description A',
                                'tags' => "PHP\nSymfony",
                                'preview_alt' => 'Preview A',
                            ],
                        ],
                    ],
                    $secondId => [
                        'sort_order' => '0',
                        'github_url' => 'https://github.com/example/second',
                        'demo_url' => '',
                        'is_visible' => '1',
                        'preview_image_path' => FlagshipProjectsContract::DEFAULT_PROJECT_PREVIEW_IMAGE_PATH,
                        'locales' => [
                            'fr' => [
                                'title' => 'Projet B',
                                'description' => '',
                                'tags' => '',
                                'preview_alt' => '',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $parsed = FlagshipProjectsContract::parseRawEntriesFromRequest($request, ['fr'], 'fr');
        self::assertIsArray($parsed);
        self::assertCount(2, $parsed['entriesByLocale']['fr']);
        self::assertSame('Projet B', $parsed['entriesByLocale']['fr'][0]['title']);
        self::assertSame('Projet A', $parsed['entriesByLocale']['fr'][1]['title']);

        $normalized = FlagshipProjectsContract::normalizeEntriesByLocale($parsed['entriesByLocale'], 'fr');
        self::assertIsArray($normalized);
        self::assertCount(2, $normalized['fr']);
    }

    /**
     * @brief More than six submitted project cards must be rejected at parse time.
     *
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function testParseRawEntriesFromRequestRejectsMoreThanMaxProjects(): void
    {
        $entries = [];
        for ($index = 0; $index < 7; ++$index) {
            $projectId = FlagshipProjectsContract::generateUuidV4();
            $entries[$projectId] = [
                'sort_order' => (string) $index,
                'github_url' => '',
                'demo_url' => '',
                'is_visible' => '1',
                'preview_image_path' => FlagshipProjectsContract::DEFAULT_PROJECT_PREVIEW_IMAGE_PATH,
                'locales' => [
                    'fr' => [
                        'title' => 'Projet '.$index,
                        'description' => '',
                        'tags' => '',
                        'preview_alt' => '',
                    ],
                ],
            ];
        }

        $request = Request::create('/', 'POST', [
            'flagship_projects' => ['entries' => $entries],
        ]);

        self::assertNull(FlagshipProjectsContract::parseRawEntriesFromRequest($request, ['fr'], 'fr'));
    }

    /**
     * @brief Admin canonical cards must preserve locale-specific copy for each project id.
     *
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function testBuildCanonicalProjectsForAdminGroupsLocalesByProjectId(): void
    {
        $service = new CvFlagshipProjectsSettingsService();
        $projectId = FlagshipProjectsContract::generateUuidV4();

        $canonical = $service->buildCanonicalProjectsForAdmin([
            'fr' => [[
                'id' => $projectId,
                'sortOrder' => 0,
                'title' => 'FR title',
                'description' => 'FR description',
                'tags' => ['PHP'],
                'previewAlt' => 'FR alt',
                'previewImagePath' => FlagshipProjectsContract::DEFAULT_PROJECT_PREVIEW_IMAGE_PATH,
                'githubUrl' => 'https://github.com/example/project',
                'demoUrl' => null,
                'isVisible' => true,
            ]],
            'en' => [[
                'id' => $projectId,
                'sortOrder' => 0,
                'title' => 'EN title',
                'description' => 'EN description',
                'tags' => ['Symfony'],
                'previewAlt' => 'EN alt',
                'previewImagePath' => FlagshipProjectsContract::DEFAULT_PROJECT_PREVIEW_IMAGE_PATH,
                'githubUrl' => 'https://github.com/example/project',
                'demoUrl' => null,
                'isVisible' => true,
            ]],
        ], ['fr', 'en'], 'fr');

        self::assertCount(1, $canonical);
        self::assertSame('FR title', $canonical[0]['locales']['fr']['title']);
        self::assertSame('EN title', $canonical[0]['locales']['en']['title']);
    }

    /**
     * @brief Full projects list must expose hidden-on-primary markers and secondary visibility flag.
     *
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function testResolveAllMarksHiddenProjectsAndSecondaryVisibility(): void
    {
        $service = new CvFlagshipProjectsSettingsService();
        $visibleId = FlagshipProjectsContract::generateUuidV4();
        $hiddenId = FlagshipProjectsContract::generateUuidV4();
        $entries = [
            [
                'id' => $visibleId,
                'sortOrder' => 0,
                'title' => 'Visible',
                'description' => '',
                'tags' => [],
                'previewAlt' => 'Visible',
                'previewImagePath' => FlagshipProjectsContract::DEFAULT_PROJECT_PREVIEW_IMAGE_PATH,
                'githubUrl' => null,
                'demoUrl' => null,
                'isVisible' => true,
            ],
            [
                'id' => $hiddenId,
                'sortOrder' => 1,
                'title' => 'Hidden',
                'description' => '',
                'tags' => [],
                'previewAlt' => 'Hidden',
                'previewImagePath' => FlagshipProjectsContract::DEFAULT_PROJECT_PREVIEW_IMAGE_PATH,
                'githubUrl' => null,
                'demoUrl' => null,
                'isVisible' => false,
            ],
        ];

        self::assertTrue($service->hasSecondaryVisible($entries));
        $full = $service->resolveAll($entries);
        self::assertCount(2, $full);
        self::assertFalse($full[0]['hiddenOnPrimary']);
        self::assertTrue($full[1]['hiddenOnPrimary']);
        self::assertCount(1, $service->filterVisible($entries));
    }

    /**
     * @brief Deterministic fallback ids and admin-generated ids must pass project id validation.
     *
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function testDeterministicFallbackProjectIdIsAccepted(): void
    {
        $legacyId = '569bcabd-ebcf-0b76-18fe-978e5b37eb01';
        $currentId = FlagshipProjectsContract::generateDeterministicUuid('placeholder-project');

        self::assertTrue(FlagshipProjectsContract::isValidProjectId($legacyId));
        self::assertTrue(FlagshipProjectsContract::isValidProjectId($currentId));
    }

    /**
     * @brief GitHub hosts must be detected separately from other code repository URLs.
     *
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function testIsGithubCodeUrlDetectsGithubHostsOnly(): void
    {
        self::assertTrue(FlagshipProjectsContract::isGithubCodeUrl('https://github.com/example/repo'));
        self::assertTrue(FlagshipProjectsContract::isGithubCodeUrl('https://gist.github.com/example/demo'));
        self::assertFalse(FlagshipProjectsContract::isGithubCodeUrl('https://gitlab.com/example/repo'));
        self::assertFalse(FlagshipProjectsContract::isGithubCodeUrl('https://example.com/code'));
    }

    /**
     * @brief Site link labels must persist per locale and remain optional.
     *
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function testNormalizeEntryPersistsSiteLinkLabel(): void
    {
        $entry = FlagshipProjectsContract::normalizeEntry([
            'id' => FlagshipProjectsContract::generateUuidV4(),
            'title' => 'Project',
            'description' => '',
            'tags' => [],
            'previewAlt' => 'Preview',
            'previewImagePath' => FlagshipProjectsContract::DEFAULT_PROJECT_PREVIEW_IMAGE_PATH,
            'githubUrl' => 'https://gitlab.com/example/repo',
            'demoUrl' => 'https://example.com/project/',
            'siteLinkLabel' => 'Le site',
            'isVisible' => true,
        ], 0);

        self::assertNotNull($entry);
        self::assertSame('Le site', $entry['siteLinkLabel']);
    }

    /**
     * @brief Project cards must accept any number of tags at persistence time.
     *
     * @return void
     * @date 2026-06-11
     * @author Stephane H.
     */
    public function testNormalizeEntryAcceptsUnlimitedTags(): void
    {
        $tags = [];
        for ($index = 1; $index <= 12; ++$index) {
            $tags[] = 'Tag '.$index;
        }

        $entry = FlagshipProjectsContract::normalizeEntry([
            'id' => FlagshipProjectsContract::generateUuidV4(),
            'title' => 'Project',
            'description' => '',
            'tags' => $tags,
            'previewAlt' => 'Preview',
            'previewImagePath' => FlagshipProjectsContract::DEFAULT_PROJECT_PREVIEW_IMAGE_PATH,
            'githubUrl' => null,
            'demoUrl' => null,
            'siteLinkLabel' => '',
            'isVisible' => true,
        ], 0);

        self::assertNotNull($entry);
        self::assertCount(12, $entry['tags']);
        self::assertSame('Tag 12', $entry['tags'][11]);
    }
}
