<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cv;

use App\Cv\CompanyCvFlagshipProjectsOverrideScope;
use App\Service\Cv\FlagshipProjectsContract;
use PHPUnit\Framework\TestCase;

final class CompanyCvFlagshipProjectsOverrideScopeTest extends TestCase
{
    /**
     * @brief Merge applies flagship projects keys onto base payload.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testMergeIntoPayloadReplacesFlagshipProjectsKeys(): void
    {
        $projectId = '11111111-1111-4111-8111-111111111111';
        $base = [
            'title' => 'CV',
            FlagshipProjectsContract::KEY_SECTION_ENABLED => true,
            FlagshipProjectsContract::KEY_ENTRIES_BY_LOCALE => [
                'fr' => [[
                    'id' => $projectId,
                    'sortOrder' => 0,
                    'title' => 'Global project',
                    'description' => 'Global description',
                    'tags' => ['PHP'],
                    'previewAlt' => 'Alt',
                    'siteLinkLabel' => 'Site',
                    'previewImagePath' => FlagshipProjectsContract::DEFAULT_PROJECT_PREVIEW_IMAGE_PATH,
                    'githubUrl' => 'https://github.com/example/global',
                    'demoUrl' => 'https://example.com/global',
                    'isVisible' => true,
                ]],
            ],
        ];

        $override = [
            FlagshipProjectsContract::KEY_SECTION_ENABLED => false,
            FlagshipProjectsContract::KEY_ENTRIES_BY_LOCALE => [
                'fr' => [[
                    'id' => '22222222-2222-4222-8222-222222222222',
                    'sortOrder' => 0,
                    'title' => 'Company project',
                    'description' => 'Tailored description',
                    'tags' => ['Symfony'],
                    'previewAlt' => 'Company alt',
                    'siteLinkLabel' => 'Demo',
                    'previewImagePath' => FlagshipProjectsContract::DEFAULT_PROJECT_PREVIEW_IMAGE_PATH,
                    'githubUrl' => 'https://github.com/example/company',
                    'demoUrl' => 'https://example.com/company',
                    'isVisible' => true,
                ]],
            ],
        ];

        $merged = CompanyCvFlagshipProjectsOverrideScope::mergeIntoPayload($base, $override, 'fr');

        self::assertFalse($merged[FlagshipProjectsContract::KEY_SECTION_ENABLED]);
        self::assertSame('Company project', $merged[FlagshipProjectsContract::KEY_ENTRIES_BY_LOCALE]['fr'][0]['title']);
        self::assertSame('CV', $merged['title']);
    }
}
