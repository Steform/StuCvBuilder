<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Employment;

use App\Cv\CompanyCvCustomizationSectionKey;
use App\Entity\TrackedCompany;
use App\Repository\CompanyCvSectionOverrideRepository;
use App\Service\Employment\CompanyCvCustomizationShellService;
use PHPUnit\Framework\TestCase;

final class CompanyCvCustomizationShellServiceTest extends TestCase
{
    private CompanyCvCustomizationShellService $service;

    protected function setUp(): void
    {
        $overrideRepository = $this->createMock(CompanyCvSectionOverrideRepository::class);
        $overrideRepository->method('findSectionKeysForCompany')->willReturn([]);

        $this->service = new CompanyCvCustomizationShellService($overrideRepository);
    }

    /**
     * @brief Shell lists all sections as inherited in phase 1.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testBuildShellViewDataListsAllSectionsAsInherited(): void
    {
        $company = new TrackedCompany('Ab3xY9kLm2Qp', 'Acme');

        $shell = $this->service->buildShellViewData($company, null);

        self::assertSame(CompanyCvCustomizationSectionKey::defaultKey(), $shell['activeSection']);
        self::assertSame(count(CompanyCvCustomizationSectionKey::orderedKeys()), $shell['totalSections']);
        self::assertSame(0, $shell['customizedCount']);
        self::assertCount($shell['totalSections'], $shell['sections']);

        foreach ($shell['sections'] as $section) {
            self::assertFalse($section['customized']);
            self::assertNotSame('', $section['labelKey']);
        }
    }

    /**
     * @brief Invalid section query falls back to default section key.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testResolveActiveSectionRejectsUnknownKey(): void
    {
        self::assertSame(
            CompanyCvCustomizationSectionKey::defaultKey(),
            $this->service->resolveActiveSection('not_a_section'),
        );
    }

    /**
     * @brief Valid section query is preserved as active section.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testBuildShellViewDataHonorsValidSectionQuery(): void
    {
        $company = new TrackedCompany('Ab3xY9kLm2Qp', 'Acme');

        $shell = $this->service->buildShellViewData($company, CompanyCvCustomizationSectionKey::EXPERIENCE);

        self::assertSame(CompanyCvCustomizationSectionKey::EXPERIENCE, $shell['activeSection']);
    }
}
