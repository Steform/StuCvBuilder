<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Cv;

use App\Cv\SkillsTreeContract;
use App\Service\Cv\CertificationContract;
use App\Service\Cv\CvPublicNavVisibilityService;
use App\Service\Cv\EducationContract;
use App\Service\Cv\ExperienceContract;
use App\Service\Cv\FlagshipProjectsContract;
use App\Service\Cv\LanguagesContract;
use PHPUnit\Framework\TestCase;

/**
 * @brief Unit tests for {@see CvPublicNavVisibilityService}.
 * @date 2026-06-21
 * @author Stephane H.
 */
final class CvPublicNavVisibilityServiceTest extends TestCase
{
    private CvPublicNavVisibilityService $service;

    protected function setUp(): void
    {
        $this->service = new CvPublicNavVisibilityService();
    }

    /**
     * @brief Empty stored payload must keep only About and Contact in the sidebar.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testResolveHidesEmptySectionsForVirginProfile(): void
    {
        $visibility = $this->service->resolve([], []);

        self::assertTrue($visibility['about']);
        self::assertTrue($visibility['contact']);
        self::assertFalse($visibility['skills']);
        self::assertFalse($visibility['experience']);
        self::assertFalse($visibility['certification']);
    }

    /**
     * @brief Persisted section maps must expose matching sidebar links.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testResolveShowsLinksForPersistedSections(): void
    {
        $stored = [
            ExperienceContract::KEY_ENTRIES_BY_LOCALE => [
                'fr' => [[
                    'id' => '550e8400-e29b-41d4-a716-446655440000',
                    'startDate' => '2018-01',
                    'endDate' => '2022-12',
                    'isCurrent' => false,
                    'title' => 'Engineer',
                    'companyName' => 'Acme',
                ]],
            ],
            SkillsTreeContract::KEY => [
                'categories' => [[
                    'id' => 'cat-1',
                    'labelsByLocale' => ['fr' => 'IT'],
                    'items' => [],
                    'subcategories' => [],
                ]],
            ],
            LanguagesContract::KEY_ENTRIES => [[
                'id' => 'lang-1',
                'code' => 'fr',
                'labelByLocale' => ['fr' => 'Francais'],
            ]],
        ];
        $resolved = [
            'skillsTreePrimary' => ['categories' => [['id' => 'cat-1']]],
            'languageEntries' => $stored[LanguagesContract::KEY_ENTRIES],
        ];

        $visibility = $this->service->resolve($stored, $resolved);

        self::assertTrue($visibility['experience']);
        self::assertTrue($visibility['skills']);
        self::assertTrue($visibility['languages']);
        self::assertFalse($visibility['projects']);
    }

    /**
     * @brief Projects nav link requires persisted rows and a visible resolved list.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testResolveShowsProjectsWhenPersistedRowsExist(): void
    {
        $stored = [
            FlagshipProjectsContract::KEY_ENTRIES_BY_LOCALE => [
                'fr' => [['id' => 'project-1', 'title' => 'Portfolio', 'isVisible' => true]],
            ],
        ];
        $resolved = [
            FlagshipProjectsContract::KEY_SECTION_ENABLED => true,
            'flagshipProjects' => [['id' => 'project-1', 'title' => 'Portfolio']],
        ];

        self::assertTrue($this->service->resolve($stored, $resolved)['projects']);
    }
}
