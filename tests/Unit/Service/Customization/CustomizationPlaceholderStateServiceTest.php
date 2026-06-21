<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Customization;

use App\Repository\CvProfileRepository;
use App\Service\Customization\CustomizationPlaceholderStateService;
use App\Service\Cv\ExperienceContract;
use PHPUnit\Framework\TestCase;

/**
 * @brief Unit tests for {@see CustomizationPlaceholderStateService}.
 * @date 2026-05-17
 * @author Stephane H.
 */
final class CustomizationPlaceholderStateServiceTest extends TestCase
{
    /**
     * @brief Placeholder mode is active when no CV profile rows exist.
     * @return void
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function testIsActiveWhenNoProfiles(): void
    {
        $repository = $this->createMock(CvProfileRepository::class);
        $repository->method('count')->with([])->willReturn(0);

        $service = new CustomizationPlaceholderStateService($repository);
        self::assertTrue($service->isActive());
        self::assertTrue($service->shouldUsePlaceholderMode([]));
    }

    /**
     * @brief Virgin profile JSON without saved sections still uses placeholder mode.
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testShouldUsePlaceholderModeWhenProfileExistsButSectionsAreUnset(): void
    {
        $repository = $this->createMock(CvProfileRepository::class);
        $repository->method('count')->with([])->willReturn(1);

        $service = new CustomizationPlaceholderStateService($repository);
        self::assertFalse($service->isActive());
        self::assertTrue($service->shouldUsePlaceholderMode([
            'pageTitleByLocale' => ['fr' => 'Mon CV'],
        ]));
    }

    /**
     * @brief Saved section keys disable global placeholder mode even when other sections remain empty.
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testShouldNotUsePlaceholderModeWhenAnySectionIsPersisted(): void
    {
        $repository = $this->createMock(CvProfileRepository::class);
        $repository->method('count')->with([])->willReturn(1);

        $service = new CustomizationPlaceholderStateService($repository);
        self::assertFalse($service->shouldUsePlaceholderMode([
            ExperienceContract::KEY_ENTRIES_BY_LOCALE => ['fr' => []],
        ]));
    }
}
