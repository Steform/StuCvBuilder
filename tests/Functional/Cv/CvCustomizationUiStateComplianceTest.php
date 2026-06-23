<?php

declare(strict_types=1);

namespace App\Tests\Functional\Cv;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * @brief Compliance checks for CV customization UI state preservation (tab, panel, locale).
 * @date 2026-05-17
 * @author Stephane H.
 */
final class CvCustomizationUiStateComplianceTest extends KernelTestCase
{
    private static function projectRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    /**
     * @brief CV controller must redirect with tab, panel, and locale via resolver.
     *
     * @return void
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function testCvControllerUsesUiStateRedirectHelper(): void
    {
        $controller = @file_get_contents(self::projectRoot().'/src/Controller/Admin/CvProfileController.php') ?: '';
        self::assertStringContainsString('redirectToCvCustomizationIndexFromRequest', $controller);
        self::assertStringContainsString('buildCvRedirectParams', $controller);
        self::assertStringContainsString('cvCustomizationActivePanel', $controller);
        self::assertStringContainsString('cvCustomizationActiveLocale', $controller);
        self::assertStringContainsString('cvCustomizationActiveEntry', $controller);
    }

    /**
     * @brief About template must expose panel slugs and locale tabs for PRG restore.
     *
     * @return void
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function testAboutTemplateRendersActivePanelAndLocale(): void
    {
        $about = @file_get_contents(self::projectRoot().'/templates/components/cv/admin/_about_customization.html.twig') ?: '';

        self::assertStringContainsString('data-customization-panel="photo"', $about);
        self::assertStringContainsString("aboutSubPanel == 'photo' %} show{% endif %}", $about);
        self::assertStringContainsString('cvAboutTabRootAccordion', $about);
        self::assertStringContainsString('data-customization-locale-tab="{{ locale }}"', $about);
        self::assertStringContainsString('name="customization_tab" value="about"', $about);
        self::assertStringContainsString('data-customization-panel="situation_content"', $about);
    }

    /**
     * @brief Experience template must preserve locale tab state and tab hidden field.
     *
     * @return void
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function testExperienceTemplateRendersActiveLocaleTab(): void
    {
        $twig = @file_get_contents(self::projectRoot().'/templates/components/cv/admin/_experience_customization.html.twig') ?: '';
        self::assertStringContainsString('data-cv-experience-preview-locale-tab="{{ locale }}"', $twig);
        self::assertStringContainsString('locale == experienceLocale', $twig);
        self::assertStringContainsString('name="customization_tab" value="experience"', $twig);
    }

    /**
     * @brief Situation template must preserve locale tab state for content accordion.
     *
     * @return void
     * @date 2026-05-20
     * @author Stephane H.
     */
    public function testSituationTemplateRendersActiveLocaleTab(): void
    {
        $twig = @file_get_contents(self::projectRoot().'/templates/components/cv/admin/_situation_customization.html.twig') ?: '';
        $about = @file_get_contents(self::projectRoot().'/templates/components/cv/admin/_about_customization.html.twig') ?: '';
        self::assertStringContainsString('data-customization-locale-tab="{{ locale }}"', $twig);
        self::assertStringContainsString('locale == situationLocale', $twig);
        self::assertStringContainsString('name="customization_tab" value="about"', $twig);
        self::assertStringContainsString('data-customization-panel="situation_content"', $about);
    }

    /**
     * @brief Certification template must preserve locale tab state and tab hidden field.
     *
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function testCertificationTemplateRendersActiveLocaleTab(): void
    {
        $twig = @file_get_contents(self::projectRoot().'/templates/components/cv/admin/_certification_customization.html.twig') ?: '';
        self::assertStringContainsString('data-cv-certification-preview-locale="{{ locale }}"', $twig);
        self::assertStringContainsString('name="customization_tab" value="certification"', $twig);
        self::assertStringContainsString('data-customization-panel="certification_entries"', $twig);
    }

    /**
     * @brief CV index must load unified customization UI state script.
     *
     * @return void
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function testCvIndexLoadsCustomizationUiStateScript(): void
    {
        $index = @file_get_contents(self::projectRoot().'/templates/admin/cv/index.html.twig') ?: '';
        self::assertStringContainsString('customization-ui-state.js', $index);
        self::assertStringNotContainsString('cv-customization-tabs.js', $index);
    }
}
