<?php

declare(strict_types=1);

namespace App\Tests\Functional\Site;

use PHPUnit\Framework\TestCase;

/**
 * @brief Static compliance checks for dashboard-controlled maintenance mode.
 */
final class MaintenanceModeComplianceTest extends TestCase
{
    /**
     * @brief Resolve project root directory.
     *
     * @return string
     * @date 2026-06-08
     * @author Stephane H.
     */
    private static function projectRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    /**
     * @brief Home customization entity must expose maintenance mode persistence.
     *
     * @return void
     * @date 2026-06-08
     * @author Stephane H.
     */
    public function testHomeCustomizationEntityHasMaintenanceModeField(): void
    {
        $entity = @file_get_contents(self::projectRoot().'/src/Entity/HomeCustomization.php') ?: '';
        self::assertStringContainsString('maintenanceModeEnabled', $entity);
        self::assertStringContainsString('isMaintenanceModeEnabled', $entity);
        self::assertStringContainsString('setMaintenanceModeEnabled', $entity);
    }

    /**
     * @brief Site configuration dashboard must expose maintenance toggle and subscriber must guard public routes.
     *
     * @return void
     * @date 2026-06-08
     * @author Stephane H.
     */
    public function testMaintenanceModeDashboardAndSubscriberAreWired(): void
    {
        $siteConfigTwig = @file_get_contents(self::projectRoot().'/templates/home/configuration_site.html.twig') ?: '';
        self::assertStringContainsString('maintenance_mode_enabled', $siteConfigTwig);
        self::assertStringContainsString('form-check form-switch', $siteConfigTwig);

        $dashboardTwig = @file_get_contents(self::projectRoot().'/templates/home/dashboard.html.twig') ?: '';
        self::assertStringContainsString('maintenanceModeEnabled', $dashboardTwig);

        $subscriber = @file_get_contents(self::projectRoot().'/src/EventSubscriber/MaintenanceModeSubscriber.php') ?: '';
        self::assertStringContainsString("pathInfo === '/'", $subscriber);
        self::assertStringContainsString("str_starts_with(\$pathInfo, '/cv/')", $subscriber);
        self::assertStringContainsString('ROLE_ADMIN', $subscriber);

        $service = @file_get_contents(self::projectRoot().'/src/Service/Site/SiteConfigurationService.php') ?: '';
        self::assertStringContainsString('isMaintenanceModeEnabled', $service);
        self::assertStringContainsString('getBoolean(\'maintenance_mode_enabled\')', $service);
    }

    /**
     * @brief Backup export/import must include maintenance mode state.
     *
     * @return void
     * @date 2026-06-08
     * @author Stephane H.
     */
    public function testMaintenanceModeIsIncludedInBackupRoundTrip(): void
    {
        $export = @file_get_contents(self::projectRoot().'/src/Service/Customization/CustomizationBackupExportService.php') ?: '';
        self::assertStringContainsString('maintenanceModeEnabled', $export);

        $import = @file_get_contents(self::projectRoot().'/src/Service/Customization/CustomizationBackupImportService.php') ?: '';
        self::assertStringContainsString('setMaintenanceModeEnabled', $import);
    }
}
