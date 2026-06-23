<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cv;

use App\Cv\CompanyCvSkillsOverrideScope;
use App\Cv\SkillsTreeContract;
use PHPUnit\Framework\TestCase;

final class CompanyCvSkillsOverrideScopeTest extends TestCase
{
    /**
     * @brief Merge applies skills catalog onto base payload.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testMergeIntoPayloadReplacesSkillsCatalog(): void
    {
        $base = [
            'title' => 'CV',
            SkillsTreeContract::KEY => [
                'categories' => [[
                    'id' => 'cat-global',
                    'sortOrder' => 0,
                    'visibleOnPrimary' => true,
                    'labelMode' => SkillsTreeContract::LABEL_MODE_CANONICAL,
                    'canonicalLabel' => 'Global',
                    'labelsByLocale' => [],
                    'subcategories' => [],
                    'items' => [[
                        'id' => 'skill-global',
                        'sortOrder' => 0,
                        'visibleOnPrimary' => true,
                        'labelMode' => SkillsTreeContract::LABEL_MODE_CANONICAL,
                        'canonicalLabel' => 'PHP',
                        'labelsByLocale' => [],
                        'iconType' => SkillsTreeContract::ICON_TYPE_BOOTSTRAP,
                        'icon' => 'bi-circle',
                        'iconPath' => null,
                    ]],
                ]],
            ],
        ];

        $override = [
            SkillsTreeContract::KEY => [
                'categories' => [[
                    'id' => 'cat-company',
                    'sortOrder' => 0,
                    'visibleOnPrimary' => true,
                    'labelMode' => SkillsTreeContract::LABEL_MODE_CANONICAL,
                    'canonicalLabel' => 'Company',
                    'labelsByLocale' => [],
                    'subcategories' => [],
                    'items' => [[
                        'id' => 'skill-company',
                        'sortOrder' => 0,
                        'visibleOnPrimary' => true,
                        'labelMode' => SkillsTreeContract::LABEL_MODE_CANONICAL,
                        'canonicalLabel' => 'Symfony',
                        'labelsByLocale' => [],
                        'iconType' => SkillsTreeContract::ICON_TYPE_BOOTSTRAP,
                        'icon' => 'bi-circle',
                        'iconPath' => null,
                    ]],
                ]],
            ],
        ];

        $merged = CompanyCvSkillsOverrideScope::mergeIntoPayload($base, $override, ['fr'], 'fr');

        self::assertSame('Company', $merged[SkillsTreeContract::KEY]['categories'][0]['canonicalLabel']);
        self::assertSame('CV', $merged['title']);
    }
}
