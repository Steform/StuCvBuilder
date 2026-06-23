<?php

namespace App\Tests\Functional\UI;

use PHPUnit\Framework\TestCase;

class AdminDashboardMenuIntegrationTest extends TestCase
{
    /**
     * @brief Ensure base layout no longer renders global navbar menu.
     * @return void
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function testBaseLayoutDoesNotContainGlobalNavbar(): void
    {
        $root = dirname(__DIR__, 3);
        $baseTemplate = file_get_contents($root.'/templates/base.html.twig') ?: '';

        self::assertStringNotContainsString('<nav class="navbar', $baseTemplate);
    }

    /**
     * @brief Ensure dashboard explicitly includes admin dashboard menu component.
     * @return void
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function testDashboardIncludesAdminMenuComponent(): void
    {
        $root = dirname(__DIR__, 3);
        $dashboardTemplate = file_get_contents($root.'/templates/home/dashboard.html.twig') ?: '';

        self::assertStringContainsString("components/_admin_dashboard_menu.html.twig", $dashboardTemplate);
    }

    /**
     * @brief Ensure dashboard action is restricted to ROLE_CV_EDIT.
     * @return void
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function testDashboardControllerUsesRoleCvEditGuard(): void
    {
        $root = dirname(__DIR__, 3);
        $controllerSource = file_get_contents($root.'/src/Controller/HomeController.php') ?: '';

        self::assertStringContainsString("#[IsGranted('ROLE_CV_EDIT')]", $controllerSource);
        self::assertStringContainsString("name: 'app_dashboard'", $controllerSource);
    }

    /**
     * @brief Ensure admin menu no longer references backup, rollback, or audit routes.
     * @return void
     * @date 2026-05-05
     * @author Stephane H.
     */
    /**
     * @brief Admin dashboard menu must render the shared flash toast component once per page.
     *
     * @return void
     * @date 2026-05-23
     * @author Stephane H.
     */
    public function testAdminMenuIncludesSharedFlashMessagesComponent(): void
    {
        $root = dirname(__DIR__, 3);
        $menuTemplate = file_get_contents($root.'/templates/components/_admin_dashboard_menu.html.twig') ?: '';
        $cvAdminTemplate = file_get_contents($root.'/templates/admin/cv/index.html.twig') ?: '';

        self::assertStringContainsString("components/_flash_messages.html.twig", $menuTemplate);
        self::assertStringContainsString('{% block flash_messages %}{% endblock %}', $cvAdminTemplate);
    }

    /**
     * @brief Employment menu links companies and connections admin routes.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testEmploymentMenuLinksCompaniesAndConnections(): void
    {
        $menuTemplate = file_get_contents(dirname(__DIR__, 3).'/templates/components/_admin_dashboard_menu.html.twig') ?: '';

        self::assertStringContainsString("path('admin_employment_companies_index')", $menuTemplate);
        self::assertStringContainsString("path('admin_employment_connections_index')", $menuTemplate);
        self::assertStringContainsString("path('admin_employment_cv_documents_index')", $menuTemplate);
        self::assertStringContainsString("path('admin_employment_lm_documents_index')", $menuTemplate);
        self::assertStringNotContainsString('employment_companies\'|trans({}, \'messages\', currentLocale) }}</a></li>
                            <li><a class="dropdown-item disabled', $menuTemplate);
    }

    public function testAdminMenuDoesNotReferenceRemovedOperationsRoutes(): void
    {
        $menuTemplate = file_get_contents(dirname(__DIR__, 3).'/templates/components/_admin_dashboard_menu.html.twig') ?: '';

        self::assertStringNotContainsString("'admin_backup_ui'", $menuTemplate);
        self::assertStringNotContainsString("'admin_restore_ui'", $menuTemplate);
        self::assertStringNotContainsString("'admin_rollback_ui'", $menuTemplate);
        self::assertStringNotContainsString("'admin_audit_immutable'", $menuTemplate);
        self::assertStringContainsString("'admin_users_invite'", $menuTemplate);
    }
}
