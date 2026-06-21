<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cv;

use App\Cv\BootstrapIconsManifest;
use App\Cv\SkillsTreeContract;
use PHPUnit\Framework\TestCase;

/**
 * @brief Unit tests for Bootstrap icon manifest metadata and pattern-based validation.
 *
 * @date 2026-06-10
 * @author Stephane H.
 */
final class BootstrapIconsManifestTest extends TestCase
{
    /**
     * @brief Any valid Bootstrap icon class matching the pattern must normalize successfully.
     *
     * @return void
     * @date 2026-06-10
     * @author Stephane H.
     */
    public function testNormalizeCatalogAcceptsAnyValidBootstrapIconClass(): void
    {
        $catalog = [
            'categories' => [
                [
                    'id' => 'cat-it',
                    'sortOrder' => 0,
                    'visibleOnPrimary' => true,
                    'labelMode' => SkillsTreeContract::LABEL_MODE_LOCALIZED,
                    'canonicalLabel' => '',
                    'labelsByLocale' => ['fr' => 'IT', 'en' => 'IT'],
                    'items' => [
                        [
                            'id' => 'item-a',
                            'sortOrder' => 0,
                            'visibleOnPrimary' => true,
                            'iconType' => SkillsTreeContract::ICON_TYPE_BOOTSTRAP,
                            'icon' => 'bi-brilliance',
                            'iconPath' => null,
                            'labelMode' => SkillsTreeContract::LABEL_MODE_LOCALIZED,
                            'canonicalLabel' => '',
                            'labelsByLocale' => ['fr' => 'Alpha', 'en' => 'Alpha'],
                        ],
                    ],
                    'subcategories' => [],
                ],
            ],
        ];

        self::assertIsArray(SkillsTreeContract::normalizeCatalog($catalog, ['fr', 'en'], 'fr'));
    }

    /**
     * @brief Manifest metadata must stay aligned with the Bootstrap Icons CDN version.
     *
     * @return void
     * @date 2026-06-10
     * @author Stephane H.
     */
    public function testManifestPointsToBootstrapIconsCdn(): void
    {
        self::assertSame('bi-circle', BootstrapIconsManifest::DEFAULT_ICON);
        self::assertStringContainsString('bootstrap-icons@1.11.3', BootstrapIconsManifest::MANIFEST_CDN_URL);
    }
}
