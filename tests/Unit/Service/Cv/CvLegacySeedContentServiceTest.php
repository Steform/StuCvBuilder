<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Cv;

use App\Cv\SkillsTreeContract;
use App\Service\Cv\CertificationContract;
use App\Service\Cv\CvLegacySeedContentService;
use App\Service\Cv\EducationContract;
use App\Service\Cv\ExperienceContract;
use App\Service\Cv\FlagshipProjectsContract;
use App\Service\Cv\SituationContentContract;
use PHPUnit\Framework\TestCase;

/**
 * @brief Unit tests for {@see CvLegacySeedContentService}.
 * @date 2026-06-21
 * @author Stephane H.
 */
final class CvLegacySeedContentServiceTest extends TestCase
{
    /**
     * @brief Legacy flagship, certification, experience, education, situation, and skills seeds must be removed.
     *
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testStripLegacySeededContentRemovesKnownDemoSeeds(): void
    {
        $payload = [
            FlagshipProjectsContract::KEY_ENTRIES_BY_LOCALE => [
                'fr' => [[
                    'id' => 'fallback-stuslider',
                    'title' => 'StuSlider',
                    'description' => 'Demo',
                    'tags' => ['TypeScript'],
                    'previewAlt' => 'Preview',
                    'previewImagePath' => 'images/cv/projects/stuslider-demo.webp',
                    'githubUrl' => 'https://github.com/Steform/stuslider',
                    'demoUrl' => 'https://steform.fr/demo/',
                    'isVisible' => true,
                ]],
            ],
            CertificationContract::KEY_ENTRIES => [[
                'id' => 'fallback-entry_funmooc_python',
                'sortOrder' => 0,
                'titleByLocale' => ['fr' => 'Python FUN MOOC'],
                'providerNameByLocale' => ['fr' => 'CKELPROCESS'],
            ]],
            ExperienceContract::KEY_ENTRIES_BY_LOCALE => [
                'fr' => [[
                    'id' => 'fallback-entry_experience',
                    'companyName' => 'CKELPROCESS',
                    'titleByLocale' => ['fr' => 'Technicien'],
                ]],
            ],
            EducationContract::KEY_ENTRIES_BY_LOCALE => [
                'fr' => [[
                    'id' => 'fallback-entry_education',
                    'institutionName' => 'CKELPROCESS',
                    'titleByLocale' => ['fr' => 'Formation'],
                ]],
            ],
            SituationContentContract::KEY_CONTENT_BY_LOCALE => [
                'fr' => [
                    'statusLabel' => 'Disponible',
                    'introLead' => 'Recherche un CDI en remote (idéalement 100 % remote), focus COBOL.',
                    'contractChip' => 'CDI',
                    'searchFocusChipsDsl' => 'COBOL:primary',
                ],
            ],
            SkillsTreeContract::KEY => [
                'categories' => [[
                    'id' => 'cat-it',
                    'labelsByLocale' => ['fr' => 'IT'],
                    'items' => [],
                    'subcategories' => [[
                        'id' => 'sub-web',
                        'labelsByLocale' => ['fr' => 'Web'],
                        'groups' => [],
                        'items' => [
                            ['labelsByLocale' => ['fr' => 'Symfony']],
                            ['labelsByLocale' => ['fr' => 'React']],
                            ['labelsByLocale' => ['fr' => 'Docker']],
                            ['labelsByLocale' => ['fr' => 'MySQL']],
                        ],
                    ]],
                ]],
            ],
        ];

        $sanitized = CvLegacySeedContentService::stripLegacySeededContent($payload);

        self::assertArrayNotHasKey(FlagshipProjectsContract::KEY_ENTRIES_BY_LOCALE, $sanitized);
        self::assertArrayNotHasKey(CertificationContract::KEY_ENTRIES, $sanitized);
        self::assertArrayNotHasKey(ExperienceContract::KEY_ENTRIES_BY_LOCALE, $sanitized);
        self::assertArrayNotHasKey(EducationContract::KEY_ENTRIES_BY_LOCALE, $sanitized);
        self::assertArrayNotHasKey(SituationContentContract::KEY_CONTENT_BY_LOCALE, $sanitized);
        self::assertArrayNotHasKey(SkillsTreeContract::KEY, $sanitized);
    }

    /**
     * @brief User-authored content that does not match legacy fingerprints must be preserved.
     *
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testStripLegacySeededContentPreservesUnrelatedUserContent(): void
    {
        $payload = [
            FlagshipProjectsContract::KEY_ENTRIES_BY_LOCALE => [
                'fr' => [[
                    'id' => '550e8400-e29b-41d4-a716-446655440000',
                    'title' => 'Portfolio',
                    'description' => 'Personal project',
                    'tags' => ['PHP'],
                    'previewAlt' => 'Preview',
                    'previewImagePath' => FlagshipProjectsContract::DEFAULT_PROJECT_PREVIEW_IMAGE_PATH,
                    'githubUrl' => 'https://github.com/example/portfolio',
                    'demoUrl' => 'https://example.com/',
                    'isVisible' => true,
                ]],
            ],
            SkillsTreeContract::KEY => [
                'categories' => [[
                    'id' => 'cat-custom',
                    'labelsByLocale' => ['fr' => 'Design'],
                    'items' => [
                        ['labelsByLocale' => ['fr' => 'Figma']],
                    ],
                    'subcategories' => [],
                ]],
            ],
        ];

        $sanitized = CvLegacySeedContentService::stripLegacySeededContent($payload);

        self::assertSame($payload, $sanitized);
    }

    /**
     * @brief Sanitizer must be idempotent on already cleaned payloads.
     *
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testStripLegacySeededContentIsIdempotent(): void
    {
        $payload = [
            'pageTitleByLocale' => ['fr' => 'Mon CV'],
        ];

        $once = CvLegacySeedContentService::stripLegacySeededContent($payload);
        $twice = CvLegacySeedContentService::stripLegacySeededContent($once);

        self::assertSame($once, $twice);
    }
}
