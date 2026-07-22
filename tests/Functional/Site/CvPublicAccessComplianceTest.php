<?php

declare(strict_types=1);

namespace App\Tests\Functional\Site;

use PHPUnit\Framework\TestCase;

/**
 * @brief Static compliance checks for dashboard-controlled public CV access policy.
 */
final class CvPublicAccessComplianceTest extends TestCase
{
    /**
     * @brief Resolve project root directory.
     *
     * @return string
     * @date 2026-07-22
     * @author Stephane H.
     */
    private static function projectRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    /**
     * @brief Home customization entity must expose CV access policy persistence.
     *
     * @return void
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function testHomeCustomizationEntityHasCvAccessPolicyFields(): void
    {
        $entity = @file_get_contents(self::projectRoot().'/src/Entity/HomeCustomization.php') ?: '';
        self::assertStringContainsString('cvPublicAccessMode', $entity);
        self::assertStringContainsString('cvInvalidFormatPolicy', $entity);
        self::assertStringContainsString('getCvPublicAccessMode', $entity);
        self::assertStringContainsString('setCvInvalidFormatPolicy', $entity);
    }

    /**
     * @brief Site configuration dashboard must expose CV access controls and subscribers must enforce policy.
     *
     * @return void
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function testCvPublicAccessDashboardAndSubscribersAreWired(): void
    {
        $siteConfigTwig = @file_get_contents(self::projectRoot().'/templates/home/configuration_site.html.twig') ?: '';
        self::assertStringContainsString('cv_public_access_mode', $siteConfigTwig);
        self::assertStringContainsString('cv_invalid_format_policy', $siteConfigTwig);

        $deniedSubscriber = @file_get_contents(self::projectRoot().'/src/EventSubscriber/CvPublicAccessDeniedSubscriber.php') ?: '';
        self::assertStringContainsString('HTTP_FORBIDDEN', $deniedSubscriber);
        self::assertStringContainsString('cv/access_denied.html.twig', $deniedSubscriber);

        $gateSubscriber = @file_get_contents(self::projectRoot().'/src/EventSubscriber/CvAccessGateSubscriber.php') ?: '';
        self::assertStringContainsString('CvPublicAccessPolicyService', $gateSubscriber);

        $service = @file_get_contents(self::projectRoot().'/src/Service/Site/SiteConfigurationService.php') ?: '';
        self::assertStringContainsString('getCvPublicAccessMode', $service);
        self::assertStringContainsString('cv_public_access_mode', $service);
    }

    /**
     * @brief Backup export/import must include CV access policy state.
     *
     * @return void
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function testCvPublicAccessPolicyIsIncludedInBackupRoundTrip(): void
    {
        $export = @file_get_contents(self::projectRoot().'/src/Service/Customization/CustomizationBackupExportService.php') ?: '';
        self::assertStringContainsString('cvPublicAccessMode', $export);
        self::assertStringContainsString('cvInvalidFormatPolicy', $export);

        $import = @file_get_contents(self::projectRoot().'/src/Service/Customization/CustomizationBackupImportService.php') ?: '';
        self::assertStringContainsString('setCvPublicAccessMode', $import);
        self::assertStringContainsString('setCvInvalidFormatPolicy', $import);
    }

    /**
     * @brief Sitemap must omit CV routes when format is required.
     *
     * @return void
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function testSitemapServiceSkipsCvRoutesForFormatRequiredMode(): void
    {
        $sitemap = @file_get_contents(self::projectRoot().'/src/Service/Site/SiteSitemapService.php') ?: '';
        self::assertStringContainsString('shouldSkipSitemapEntry', $sitemap);
        self::assertStringContainsString('FormatRequired', $sitemap);
    }
}
