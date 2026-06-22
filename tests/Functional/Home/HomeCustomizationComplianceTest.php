<?php

declare(strict_types=1);

namespace App\Tests\Functional\Home;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;

/**
 * @brief Compliance and contract checks for home customization (global scope, routes, rollback hook).
 * @date 2026-05-08
 * @author Stephane H.
 */
final class HomeCustomizationComplianceTest extends KernelTestCase
{
    private static function projectRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    /**
     * @brief Global customization entities must remain detached from user ownership records (hard-delete N/A for users).
     * @return void
     * @date 2026-05-08
     * @author Stephane H.
     */
    public function testHomeCustomizationSchemaHasNoUserForeignKey(): void
    {
        $entity = @file_get_contents(self::projectRoot().'/src/Entity/HomeCustomization.php') ?: '';
        self::assertStringNotContainsString('User', $entity);
        self::assertStringNotContainsString('app_user', $entity);
    }

    /**
     * @brief Ensure customization stylesheet route remains publicly reachable for anonymous visitors.
     * @return void
     * @date 2026-05-08
     * @author Stephane H.
     */
    public function testCssControllerRouteIsDefinedWithoutIsGranted(): void
    {
        $controller = @file_get_contents(self::projectRoot().'/src/Controller/HomeCustomizationCssController.php') ?: '';
        self::assertStringContainsString("name: 'app_home_customization_css'", $controller);
        self::assertStringNotContainsString('IsGranted', $controller);
    }

    /**
     * @brief Register customization CSS and admin dashboard routes for smoke coverage without HTTP kernel hits.
     * @return void
     * @date 2026-05-08
     * @author Stephane H.
     */
    public function testCustomizationRoutesAreRegistered(): void
    {
        self::bootKernel();
        /** @var RouterInterface $router */
        $router = static::getContainer()->get(RouterInterface::class);

        self::assertNotSame('', $router->generate('app_home_customization_css'));
        self::assertNotSame('', $router->generate('app_dashboard_customization_home'));
    }

    /**
     * @brief Landing template must load merged customization stylesheet via route helper (no inline CSS).
     * @return void
     * @date 2026-05-08
     * @author Stephane H.
     */
    public function testLandingTemplateReferencesCustomizationStylesheetRoute(): void
    {
        $base = @file_get_contents(self::projectRoot().'/templates/base.html.twig') ?: '';
        self::assertStringContainsString('cv_site_title_prefix(currentLocale)', $base);
        self::assertStringContainsString('site_favicon_href()', $base);
        self::assertStringContainsString('site_favicon_type()', $base);
        self::assertStringNotContainsString('Stephane HIRT', $base);

        $twig = @file_get_contents(self::projectRoot().'/templates/home/index.html.twig') ?: '';
        self::assertStringContainsString("path('app_home_customization_css'", $twig);
        self::assertStringContainsString('home-custom-intro', $twig);
        self::assertStringContainsString('home-quick-tile', $twig);
        self::assertStringContainsString('dashboardTileIconRelativePath', $twig);
    }

    /**
     * @brief Admin home customization must expose six accordions, locale tabs, and eleven tile style radios.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function testCustomizationAdminAccordionAndTilePresets(): void
    {
        $twig = @file_get_contents(self::projectRoot().'/templates/home/customization_home.html.twig') ?: '';
        self::assertStringContainsString('id="homeCustomizationAccordion"', $twig);
        self::assertGreaterThanOrEqual(6, substr_count($twig, 'accordion-item'));
        self::assertStringContainsString('data-customization-panel="custom_quick_tiles"', $twig);
        self::assertStringContainsString('_custom_quick_tiles_admin_panel.html.twig', $twig);
        self::assertStringNotContainsString('site_favicon_upload', $twig);

        $siteConfigTwig = @file_get_contents(self::projectRoot().'/templates/home/configuration_site.html.twig') ?: '';
        $siteColorsPartial = @file_get_contents(self::projectRoot().'/templates/components/site/_site_colors_accent_field.html.twig') ?: '';
        self::assertStringContainsString('site_favicon_upload', $siteConfigTwig);
        self::assertStringContainsString('_site_colors_accent_field.html.twig', $siteConfigTwig);
        self::assertStringContainsString('site_colors[accent]', $siteColorsPartial);
        $siteCvMenuPartial = @file_get_contents(self::projectRoot().'/templates/components/site/_site_colors_cv_menu_field.html.twig') ?: '';
        self::assertStringContainsString('_site_colors_cv_menu_field.html.twig', $siteConfigTwig);
        self::assertStringContainsString('site_colors[cv_menu_background]', $siteCvMenuPartial);
        self::assertStringContainsString('cv_antibot_threshold', $siteConfigTwig);
        self::assertStringContainsString('maintenance_mode_enabled', $siteConfigTwig);
        self::assertStringContainsString('site_seo_meta_description', $siteConfigTwig);
        self::assertStringContainsString('headingSiteConfigSeo', $siteConfigTwig);
        self::assertStringContainsString('site-configuration-mail.js', $siteConfigTwig);
        self::assertStringContainsString('_site_mail_templates_panel.html.twig', $siteConfigTwig);
        self::assertStringContainsString('ckeditor-cv-rich', @file_get_contents(self::projectRoot().'/templates/components/site/_site_mail_templates_panel.html.twig') ?: '');
        self::assertFileExists(self::projectRoot().'/public/vendor/ckeditor5/41.4.2-cv/ckeditor.js');
        self::assertStringContainsString('nav-tabs', $twig);
        foreach (\App\Service\Home\HomeQuickTilePresetRegistry::PRESET_STYLES as $presetStyle) {
            self::assertStringContainsString($presetStyle, $twig);
        }
        self::assertStringContainsString("'custom'", $twig);
        self::assertStringContainsString('name="quick_tile_style"', $twig);
        self::assertStringContainsString('js-quick-tile-style', $twig);
        self::assertStringContainsString('home-customization-admin.js', $twig);
        self::assertStringContainsString('id="quick-tile-custom-css-wrapper"', $twig);
    }

    /**
     * @brief Persist operation flushes customization to the database.
     * @return void
     * @date 2026-05-16
     * @author Stephane H.
     */
    public function testSavePersistsCustomization(): void
    {
        $service = @file_get_contents(self::projectRoot().'/src/Service/Home/HomeCustomizationService.php') ?: '';
        self::assertStringContainsString('$this->entityManager->flush()', $service);
        self::assertStringContainsString('storeTileIconUpload', $service);
        self::assertStringContainsString('setBackgroundCssSanitized', $service);
        self::assertStringContainsString('setSignatureCssSanitized', $service);
    }

    /**
     * @brief Backup export must serialize unified quick tile fields for round-trip.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function testBackupExportSerializesQuickTileFields(): void
    {
        $export = @file_get_contents(self::projectRoot().'/src/Service/Customization/CustomizationBackupExportService.php') ?: '';
        self::assertStringContainsString("'quickTileStyle'", $export);
        self::assertStringContainsString("'quickTileCssSanitized'", $export);
        self::assertStringContainsString("'dashboardTileIconRelativePath'", $export);
        self::assertStringContainsString("'siteFaviconRelativePath'", $export);
        self::assertStringContainsString("'siteColorsJson'", $export);
        self::assertStringNotContainsString("'dashboardTileCssSanitized'", $export);
    }

    /**
     * @brief Dashboard customization view wires CKEditor assets and textarea convention.
     * @return void
     * @date 2026-05-14
     * @author Stephane H.
     */
    /**
     * @brief Home customization POST must redirect with preserved panel and locale query params.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function testHomeControllerRedirectsWithUiStateParams(): void
    {
        $controller = @file_get_contents(self::projectRoot().'/src/Controller/HomeController.php') ?: '';
        self::assertStringContainsString('buildHomeRedirectParams', $controller);
        self::assertStringContainsString('homeCustomizationActivePanel', $controller);
        self::assertStringContainsString('customization-ui-state.js', @file_get_contents(self::projectRoot().'/templates/home/customization_home.html.twig') ?: '');
    }

    /**
     * @brief Home template must render server-side open panel from active panel variable.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function testHomeTemplateRendersActivePanelCollapse(): void
    {
        $twig = @file_get_contents(self::projectRoot().'/templates/home/customization_home.html.twig') ?: '';
        self::assertStringContainsString('data-customization-panel="tiles"', $twig);
        self::assertStringContainsString("homePanel == 'tiles' %} show{% endif %}", $twig);
        self::assertStringContainsString('data-customization-locale-tab="{{ localeCode }}"', $twig);
        self::assertStringContainsString('name="customization_panel"', $twig);
    }

    public function testCustomizationTemplateReferencesCkeditorPipeline(): void
    {
        $twig = @file_get_contents(self::projectRoot().'/templates/home/customization_home.html.twig') ?: '';
        self::assertStringContainsString("vendor/ckeditor5/41.4.2-cv/ckeditor.js", $twig);
        self::assertStringContainsString('ckeditor-cv-rich', $twig);
        self::assertStringContainsString('data-editor-scope="home"', $twig);
        self::assertStringContainsString('ckeditor-init.js', $twig);
    }

    /**
     * @brief Central init script targets ClassicEditor and scoped textareas.
     * @return void
     * @date 2026-05-14
     * @author Stephane H.
     */
    public function testCkeditorInitScriptContainsClassicEditorBootstrap(): void
    {
        $js = @file_get_contents(self::projectRoot().'/public/js/ckeditor-init.js') ?: '';
        self::assertStringContainsString('ClassicEditor.create', $js);
        self::assertStringContainsString('textarea.ckeditor-cv-rich[data-editor-scope="cv"], textarea.ckeditor-cv-rich[data-editor-scope="mail"], textarea.ckeditor-cv-rich[data-editor-scope="home"]', $js);
        self::assertStringContainsString('fontColor', $js);
        self::assertStringContainsString('fontBackgroundColor', $js);
    }

    /**
     * @brief Public landing renders sanitized intro with raw filter only on resolved branch.
     * @return void
     * @date 2026-05-08
     * @author Stephane H.
     */
    public function testLandingTemplateUsesRawOnlyForResolvedIntro(): void
    {
        $twig = @file_get_contents(self::projectRoot().'/templates/home/index.html.twig') ?: '';
        self::assertStringContainsString('homeIntroResolved|raw', $twig);
    }
}
