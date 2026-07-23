<?php

declare(strict_types=1);

namespace App\Tests\Functional\UI;

use PHPUnit\Framework\TestCase;

/**
 * @brief Static checks for company CV customization shell (synced vs custom modes).
 */
final class EmploymentCompanyCvCustomizationShellTest extends TestCase
{
    /**
     * @brief Controller exposes GET route for customization shell.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testControllerDeclaresCvCustomizationRoute(): void
    {
        $root = dirname(__DIR__, 3);
        $source = file_get_contents($root.'/src/Controller/Admin/EmploymentCompanyAdminController.php') ?: '';

        self::assertStringContainsString('admin_employment_companies_cv_customization', $source);
        self::assertStringContainsString('function cvCustomization', $source);
        self::assertStringContainsString('company_cv_mode_switch_custom', $source);
        self::assertStringContainsString('company_cv_mode_switch_synced', $source);
    }

    /**
     * @brief Edit modal links to customization via trigger data attribute.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testEditModalTriggerLinksToCustomization(): void
    {
        $root = dirname(__DIR__, 3);
        $trigger = file_get_contents($root.'/templates/admin/employment/companies/_edit_modal_trigger.html.twig') ?: '';
        $modal = file_get_contents($root.'/templates/admin/employment/companies/_edit_modal.html.twig') ?: '';

        self::assertStringContainsString('data-cv-customization-url', $trigger);
        self::assertStringContainsString('admin_employment_companies_cv_customization', $trigger);
        self::assertStringContainsString('data-employment-company-cv-customization-link', $modal);
        self::assertStringContainsString('employment.companies.actions.customize_cv_web', $modal);
    }

    /**
     * @brief Customization template renders master/detail navigation and mode switch banner.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testCustomizationTemplateContainsSectionNav(): void
    {
        $root = dirname(__DIR__, 3);
        $template = file_get_contents($root.'/templates/admin/employment/companies/cv_customization.html.twig') ?: '';

        self::assertStringContainsString('employment-company-cv-customization__section-nav', $template);
        self::assertStringContainsString('cvCustomizationSections', $template);
        self::assertStringContainsString('_cv_customization_about_panel.html.twig', $template);
        self::assertStringContainsString('company_cv_mode_switch_custom', $template);
        self::assertStringContainsString('company_cv_mode_switch_synced', $template);
    }

    /**
     * @brief Create modal offers synced vs custom CV content mode.
     *
     * @return void
     * @date 2026-07-23
     * @author Stephane H.
     */
    public function testCreateModalOffersCvContentModeChoice(): void
    {
        $root = dirname(__DIR__, 3);
        $template = file_get_contents($root.'/templates/admin/employment/companies/_create_modal.html.twig') ?: '';

        self::assertStringContainsString('name="cv_content_mode"', $template);
        self::assertStringContainsString('value="synced"', $template);
        self::assertStringContainsString('value="custom"', $template);
    }

    /**
     * @brief About panel still embeds the shared About editor when customized.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testAboutPanelTemplateEmbedsEditor(): void
    {
        $root = dirname(__DIR__, 3);
        $template = file_get_contents($root.'/templates/admin/employment/companies/_cv_customization_about_panel.html.twig') ?: '';

        self::assertStringNotContainsString('company_cv_about_enable', $template);
        self::assertStringContainsString('_about_customization.html.twig', $template);
    }

    /**
     * @brief Situation panel embeds the shared Situation editor when customized.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testSituationPanelTemplateEmbedsEditor(): void
    {
        $root = dirname(__DIR__, 3);
        $template = file_get_contents($root.'/templates/admin/employment/companies/_cv_customization_situation_panel.html.twig') ?: '';
        $page = file_get_contents($root.'/templates/admin/employment/companies/cv_customization.html.twig') ?: '';

        self::assertStringNotContainsString('company_cv_situation_enable', $template);
        self::assertStringContainsString('_situation_customization.html.twig', $template);
        self::assertStringContainsString('_cv_customization_situation_panel.html.twig', $page);
    }

    /**
     * @brief Experience panel embeds the shared Experience editor when customized.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testExperiencePanelTemplateEmbedsEditor(): void
    {
        $root = dirname(__DIR__, 3);
        $template = file_get_contents($root.'/templates/admin/employment/companies/_cv_customization_experience_panel.html.twig') ?: '';
        $page = file_get_contents($root.'/templates/admin/employment/companies/cv_customization.html.twig') ?: '';
        $controller = file_get_contents($root.'/src/Controller/Admin/EmploymentCompanyAdminController.php') ?: '';

        self::assertStringNotContainsString('company_cv_experience_enable', $template);
        self::assertStringContainsString('_experience_customization.html.twig', $template);
        self::assertStringContainsString('_cv_customization_experience_panel.html.twig', $page);
        self::assertStringContainsString('company_cv_experience_save', $controller);
        self::assertStringContainsString('loadExperienceEditorAssets', $page);
        self::assertStringContainsString('vendor/ckeditor5/41.4.2-cv/ckeditor.js', $page);
        self::assertStringContainsString('ckeditor-init.js', $page);
        self::assertStringContainsString('loadCkeditorAssets', $page);
    }

    /**
     * @brief Skills panel embeds the shared Skills editor when customized.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testSkillsPanelTemplateEmbedsEditor(): void
    {
        $root = dirname(__DIR__, 3);
        $template = file_get_contents($root.'/templates/admin/employment/companies/_cv_customization_skills_panel.html.twig') ?: '';
        $page = file_get_contents($root.'/templates/admin/employment/companies/cv_customization.html.twig') ?: '';
        $controller = file_get_contents($root.'/src/Controller/Admin/EmploymentCompanyCvSkillsCatalogAdminController.php') ?: '';

        self::assertStringNotContainsString('company_cv_skills_enable', $template);
        self::assertStringContainsString('_skills_customization.html.twig', $template);
        self::assertStringContainsString('_cv_customization_skills_panel.html.twig', $page);
        self::assertStringContainsString('admin_employment_companies_cv_skills_catalog_category_save', $controller);
    }
}
