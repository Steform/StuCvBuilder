<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cv;

use App\Cv\AboutSectionPatternCustomizationContract;
use App\Cv\CvPencilDecorationContract;
use App\Cv\CvProfilePersistenceScope;
use App\Cv\SectionBackgroundContract;
use App\Cv\SectionTransitionContract;
use App\Cv\SkillsTreeContract;
use App\Service\Cv\CertificationContract;
use App\Service\Cv\FlagshipProjectsContract;
use App\Service\Cv\InterestsContract;
use App\Service\Cv\LanguagesContract;
use PHPUnit\Framework\TestCase;

/**
 * @brief Unit tests for {@see CvProfilePersistenceScope}.
 *
 * @date 2026-05-27
 * @author Stephane H.
 */
final class CvProfilePersistenceScopeTest extends TestCase
{
    /**
     * @brief Legacy About and transition keys must be stripped while pattern customization is kept.
     *
     * @return void
     * @date 2026-05-27
     * @author Stephane H.
     */
    public function testSanitizeForPersistenceStripsLegacyAboutAndKeepsPattern(): void
    {
        $payload = [
            'aboutSectionThemeColors' => ['light' => ['headerSurface' => '#111111']],
            'aboutSectionAtmosphereStyle' => 'style_3',
            'aboutSectionAtmosphereCssSanitized' => 'color: red;',
            'aboutDotsOpacity' => 0.5,
            'aboutBgDecorHexIntensity' => 0.8,
            AboutSectionPatternCustomizationContract::KEY => [
                'baseColor' => '#112233',
                'toneMixPercent' => [
                    'tone1' => 0,
                    'tone2' => 10,
                    'tone3' => 30,
                    'tone4' => 55,
                ],
                'surfaceMixPercent' => 60,
            ],
            SectionTransitionContract::KEY => ['situation' => 'fade_strong'],
            'noiseKey' => 'drop-me',
        ];

        $sanitized = CvProfilePersistenceScope::sanitizeForPersistence($payload);

        self::assertArrayNotHasKey('aboutSectionThemeColors', $sanitized);
        self::assertArrayNotHasKey('aboutSectionAtmosphereStyle', $sanitized);
        self::assertArrayNotHasKey('aboutDotsOpacity', $sanitized);
        self::assertArrayNotHasKey('aboutBgDecorHexIntensity', $sanitized);
        self::assertArrayNotHasKey(SectionTransitionContract::KEY, $sanitized);
        self::assertArrayNotHasKey('noiseKey', $sanitized);
        self::assertSame('#112233', $sanitized[AboutSectionPatternCustomizationContract::KEY]['baseColor']);
        self::assertArrayHasKey(SectionBackgroundContract::KEY, $sanitized);
    }

    /**
     * @brief Allowed persisted keys must round-trip through sanitization.
     *
     * @return void
     * @date 2026-05-27
     * @author Stephane H.
     */
    public function testSanitizeForPersistenceKeepsWhitelistedKeys(): void
    {
        $payload = [
            'pageTitleByLocale' => ['fr' => 'Mon CV'],
            'aboutProfilePhotoPath' => 'images/cv/about/custom/photo.webp',
            'experienceEntriesByLocale' => ['fr' => []],
            'situationContentByLocale' => ['fr' => []],
        ];

        $sanitized = CvProfilePersistenceScope::sanitizeForPersistence($payload);

        self::assertSame(['fr' => 'Mon CV'], $sanitized['pageTitleByLocale']);
        self::assertSame('images/cv/about/custom/photo.webp', $sanitized['aboutProfilePhotoPath']);
        self::assertArrayHasKey('experienceEntriesByLocale', $sanitized);
        self::assertArrayHasKey('situationContentByLocale', $sanitized);
    }

    /**
     * @brief Pencil decoration settings must normalize through persistence sanitization.
     *
     * @return void
     * @date 2026-06-08
     * @author Stephane H.
     */
    public function testSanitizeForPersistenceNormalizesPencilDecoration(): void
    {
        $payload = [
            CvPencilDecorationContract::KEY => [
                'enabled' => '1',
                'lightToneMixPercent' => '77',
                'darkToneMixPercent' => '180',
            ],
        ];

        $sanitized = CvProfilePersistenceScope::sanitizeForPersistence($payload);

        self::assertSame([
            'enabled' => true,
            'lightToneMixPercent' => 77,
            'darkToneMixPercent' => 100,
        ], $sanitized[CvPencilDecorationContract::KEY]);
    }

    /**
     * @brief skillsCatalog must be normalized and kept when structurally valid.
     *
     * @return void
     * @date 2026-05-29
     * @author Stephane H.
     */
    public function testSanitizeForPersistenceNormalizesSkillsCatalog(): void
    {
        $payload = [
            SkillsTreeContract::KEY => [
                'categories' => [
                    [
                        'id' => 'cat-it',
                        'sortOrder' => 0,
                        'visibleOnPrimary' => true,
                        'labelMode' => SkillsTreeContract::LABEL_MODE_LOCALIZED,
                        'canonicalLabel' => '',
                        'labelsByLocale' => ['fr' => 'IT'],
                        'items' => [],
                        'subcategories' => [
                            [
                                'id' => 'sub-web',
                                'sortOrder' => 0,
                                'visibleOnPrimary' => true,
                                'labelsByLocale' => ['fr' => 'Web'],
                                'groups' => [],
                                'items' => [
                                    [
                                        'id' => 'item-a',
                                        'sortOrder' => 0,
                                        'visibleOnPrimary' => true,
                                        'iconType' => SkillsTreeContract::ICON_TYPE_BOOTSTRAP,
                                        'icon' => 'bi-star',
                                        'iconPath' => null,
                                        'labelMode' => SkillsTreeContract::LABEL_MODE_LOCALIZED,
                                        'canonicalLabel' => '',
                                        'labelsByLocale' => ['fr' => 'Skill A'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $sanitized = CvProfilePersistenceScope::sanitizeForPersistence($payload);

        self::assertArrayHasKey(SkillsTreeContract::KEY, $sanitized);
        self::assertSame('cat-it', $sanitized[SkillsTreeContract::KEY]['categories'][0]['id']);
    }

    /**
     * @brief Flagship project rows must persist and normalize through the whitelist.
     *
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function testSanitizeForPersistenceNormalizesFlagshipProjects(): void
    {
        $projectId = FlagshipProjectsContract::generateUuidV4();
        $payload = [
            FlagshipProjectsContract::KEY_ENTRIES_BY_LOCALE => [
                'fr' => [[
                    'id' => $projectId,
                    'title' => 'StuSlider',
                    'description' => 'Demo',
                    'tags' => ['TypeScript'],
                    'previewAlt' => 'Preview',
                    'previewImagePath' => FlagshipProjectsContract::DEFAULT_PROJECT_PREVIEW_IMAGE_PATH,
                    'githubUrl' => 'https://github.com/example/repo',
                    'demoUrl' => 'https://example.com/demo/',
                    'isVisible' => true,
                ]],
            ],
        ];

        $sanitized = CvProfilePersistenceScope::sanitizeForPersistence($payload);

        self::assertArrayHasKey(FlagshipProjectsContract::KEY_ENTRIES_BY_LOCALE, $sanitized);
        self::assertSame('StuSlider', $sanitized[FlagshipProjectsContract::KEY_ENTRIES_BY_LOCALE]['fr'][0]['title']);
    }

    /**
     * @brief Certification rows must persist and normalize through the whitelist.
     *
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function testSanitizeForPersistenceNormalizesCertificationEntries(): void
    {
        $payload = [
            CertificationContract::KEY_ENTRIES => [[
                'id' => CertificationContract::generateUuidV4(),
                'sortOrder' => 0,
                'startDate' => '2023-09',
                'endDate' => '2023-12',
                'isCurrent' => false,
                'titleByLocale' => ['fr' => 'Python MOOC'],
                'providerNameByLocale' => ['fr' => 'FUN MOOC'],
                'locationByLocale' => [],
                'providerWebsiteUrl' => null,
                'proofPdfPath' => CertificationContract::CERTIFICATION_PROOF_PDF_PATH_PREFIX.'certification-proof-test.pdf',
                'highlightsByLocale' => ['fr' => ['Advanced concepts']],
                'isPrimary' => true,
            ]],
        ];

        $sanitized = CvProfilePersistenceScope::sanitizeForPersistence($payload);

        self::assertArrayHasKey(CertificationContract::KEY_ENTRIES, $sanitized);
        self::assertSame('Python MOOC', $sanitized[CertificationContract::KEY_ENTRIES][0]['titleByLocale']['fr']);
        self::assertSame(
            CertificationContract::CERTIFICATION_PROOF_PDF_PATH_PREFIX.'certification-proof-test.pdf',
            $sanitized[CertificationContract::KEY_ENTRIES][0]['proofPdfPath'] ?? null
        );
        self::assertArrayNotHasKey(CertificationContract::KEY_ENTRIES_BY_LOCALE, $sanitized);
        self::assertArrayNotHasKey('certificationHasSecondaryVisible', $sanitized);
    }

    /**
     * @brief Legacy certification map must migrate to canonical entries on sanitize.
     *
     * @return void
     * @date 2026-06-11
     * @author Stephane H.
     */
    public function testSanitizeForPersistenceMigratesLegacyCertificationMap(): void
    {
        $payload = [
            CertificationContract::KEY_ENTRIES_BY_LOCALE => [
                'fr' => [[
                    'id' => CertificationContract::generateUuidV4(),
                    'sortOrder' => 0,
                    'startDate' => '2023-09',
                    'endDate' => '2023-12',
                    'isCurrent' => false,
                    'title' => 'Legacy MOOC',
                    'providerName' => 'FUN MOOC',
                    'highlights' => ['Legacy point'],
                    'isPrimary' => true,
                ]],
            ],
        ];

        $sanitized = CvProfilePersistenceScope::sanitizeForPersistence($payload);

        self::assertArrayHasKey(CertificationContract::KEY_ENTRIES, $sanitized);
        self::assertSame('Legacy MOOC', $sanitized[CertificationContract::KEY_ENTRIES][0]['titleByLocale']['fr']);
        self::assertArrayNotHasKey(CertificationContract::KEY_ENTRIES_BY_LOCALE, $sanitized);
    }

    /**
     * @brief Language entries must persist and must not be stripped as obsolete runtime keys.
     *
     * @return void
     * @date 2026-06-09
     * @author Stephane H.
     */
    public function testSanitizeForPersistenceKeepsLanguageEntries(): void
    {
        $payload = [
            LanguagesContract::KEY_ENTRIES => [
                [
                    'id' => '550e8400-e29b-41d4-a716-446655440000',
                    'sortOrder' => 0,
                    'labelByLocale' => ['fr' => 'Français'],
                    'levelCode' => 'native',
                    'notes' => 'Mother tongue',
                ],
            ],
        ];

        $sanitized = CvProfilePersistenceScope::sanitizeForPersistence($payload);

        self::assertArrayHasKey(LanguagesContract::KEY_ENTRIES, $sanitized);
        self::assertCount(1, $sanitized[LanguagesContract::KEY_ENTRIES]);
        self::assertSame('Français', $sanitized[LanguagesContract::KEY_ENTRIES][0]['labelByLocale']['fr']);
    }

    /**
     * @brief Canonical interest entries must survive persistence sanitization.
     *
     * @return void
     * @date 2026-06-11
     * @author Stephane H.
     */
    public function testSanitizeForPersistenceKeepsCanonicalInterestEntries(): void
    {
        $payload = [
            InterestsContract::KEY_ENTRIES => [
                [
                    'id' => '550e8400-e29b-41d4-a716-446655440000',
                    'sortOrder' => 0,
                    'iconType' => 'bootstrap',
                    'icon' => 'bi-heart',
                    'iconPath' => null,
                    'labelByLocale' => [
                        'fr' => 'Photographie',
                        'en' => 'Photography',
                    ],
                ],
            ],
        ];

        $sanitized = CvProfilePersistenceScope::sanitizeForPersistence($payload);

        self::assertArrayHasKey(InterestsContract::KEY_ENTRIES, $sanitized);
        self::assertCount(1, $sanitized[InterestsContract::KEY_ENTRIES]);
        self::assertSame('Photographie', $sanitized[InterestsContract::KEY_ENTRIES][0]['labelByLocale']['fr']);
    }

    /**
     * @brief Interest entries must persist under canonical key and migrate legacy per-locale storage.
     *
     * @return void
     * @date 2026-06-09
     * @author Stephane H.
     */
    public function testSanitizeForPersistenceKeepsInterestEntriesAndMigratesLegacy(): void
    {
        $payload = [
            InterestsContract::LEGACY_KEY_ENTRIES_BY_LOCALE => [
                'fr' => [
                    [
                        'id' => '550e8400-e29b-41d4-a716-446655440000',
                        'label' => 'Randonnée',
                        'icon' => 'bi-tree',
                    ],
                ],
                'en' => [
                    [
                        'id' => '550e8400-e29b-41d4-a716-446655440000',
                        'label' => 'Hiking',
                        'icon' => 'bi-tree',
                    ],
                ],
            ],
        ];

        $sanitized = CvProfilePersistenceScope::sanitizeForPersistence($payload);

        self::assertArrayHasKey(InterestsContract::KEY_ENTRIES, $sanitized);
        self::assertArrayNotHasKey(InterestsContract::LEGACY_KEY_ENTRIES_BY_LOCALE, $sanitized);
        self::assertCount(1, $sanitized[InterestsContract::KEY_ENTRIES]);
        self::assertSame('Randonnée', $sanitized[InterestsContract::KEY_ENTRIES][0]['labelByLocale']['fr']);
        self::assertSame('Hiking', $sanitized[InterestsContract::KEY_ENTRIES][0]['labelByLocale']['en']);
    }
}
