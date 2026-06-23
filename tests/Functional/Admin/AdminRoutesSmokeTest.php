<?php

namespace App\Tests\Functional\Admin;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;

/**
 * Smoke test for StuCvBuilder admin routes registration.
 */
class AdminRoutesSmokeTest extends KernelTestCase
{
    /**
     * @brief Ensure configured admin routes exist.
     * @return void
     * @date 2026-04-28
     * @author Stephane H.
     */
    public function testAdminRoutesAreRegistered(): void
    {
        self::bootKernel();

        /** @var RouterInterface $router */
        $router = static::getContainer()->get(RouterInterface::class);

        $routes = [
            'admin_users_invite',
            'admin_user_invite',
            'admin_cv_index',
            'admin_employment_companies_index',
            'app_dashboard_customization_backup',
        ];

        foreach ($routes as $name) {
            self::assertNotSame('', $router->generate($name));
        }
    }
}
