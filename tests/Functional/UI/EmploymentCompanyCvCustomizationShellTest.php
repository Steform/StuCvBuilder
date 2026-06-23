<?php

declare(strict_types=1);

namespace App\Tests\Functional\UI;

use PHPUnit\Framework\TestCase;

/**
 * @brief Static checks for company CV customization shell (phase 1).
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
     * @brief Customization template renders master/detail navigation.
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
    }

    /**
     * @brief About panel template supports inherited and customized modes.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testAboutPanelTemplateDefinesInheritanceActions(): void
    {
        $root = dirname(__DIR__, 3);
        $template = file_get_contents($root.'/templates/admin/employment/companies/_cv_customization_about_panel.html.twig') ?: '';

        self::assertStringContainsString('company_cv_about_enable', $template);
        self::assertStringContainsString('company_cv_about_reset', $template);
        self::assertStringContainsString('_about_customization.html.twig', $template);
    }

    /**
     * @brief Situation panel template supports inherited and customized modes.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testSituationPanelTemplateDefinesInheritanceActions(): void
    {
        $root = dirname(__DIR__, 3);
        $template = file_get_contents($root.'/templates/admin/employment/companies/_cv_customization_situation_panel.html.twig') ?: '';
        $page = file_get_contents($root.'/templates/admin/employment/companies/cv_customization.html.twig') ?: '';

        self::assertStringContainsString('company_cv_situation_enable', $template);
        self::assertStringContainsString('company_cv_situation_reset', $template);
        self::assertStringContainsString('_situation_customization.html.twig', $template);
        self::assertStringContainsString('_cv_customization_situation_panel.html.twig', $page);
    }

    /**
     * @brief Experience panel template supports inherited and customized modes.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testExperiencePanelTemplateDefinesInheritanceActions(): void
    {
        $root = dirname(__DIR__, 3);
        $template = file_get_contents($root.'/templates/admin/employment/companies/_cv_customization_experience_panel.html.twig') ?: '';
        $page = file_get_contents($root.'/templates/admin/employment/companies/cv_customization.html.twig') ?: '';
        $controller = file_get_contents($root.'/src/Controller/Admin/EmploymentCompanyAdminController.php') ?: '';

        self::assertStringContainsString('company_cv_experience_enable', $template);
        self::assertStringContainsString('company_cv_experience_reset', $template);
        self::assertStringContainsString('_experience_customization.html.twig', $template);
        self::assertStringContainsString('_cv_customization_experience_panel.html.twig', $page);
        self::assertStringContainsString('company_cv_experience_save', $controller);
    }

    /**
     * @brief Skills panel template supports inherited and customized modes.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testSkillsPanelTemplateDefinesInheritanceActions(): void
    {
        $root = dirname(__DIR__, 3);
        $template = file_get_contents($root.'/templates/admin/employment/companies/_cv_customization_skills_panel.html.twig') ?: '';
        $page = file_get_contents($root.'/templates/admin/employment/companies/cv_customization.html.twig') ?: '';
        $controller = file_get_contents($root.'/src/Controller/Admin/EmploymentCompanyCvSkillsCatalogAdminController.php') ?: '';

        self::assertStringContainsString('company_cv_skills_enable', $template);
        self::assertStringContainsString('company_cv_skills_reset', $template);
        self::assertStringContainsString('_skills_customization.html.twig', $template);
        self::assertStringContainsString('_cv_customization_skills_panel.html.twig', $page);
        self::assertStringContainsString('admin_employment_companies_cv_skills_catalog_category_save', $controller);
    }
}
