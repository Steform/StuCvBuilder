<?php

namespace App\Tests\Functional\Security;

use App\Controller\HomeController;
use App\Service\Setup\SiteSetupOnboardingService;
use App\Service\Site\SiteConfigurationService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class HomeDashboardControllerTest extends TestCase
{
    /**
     * @brief Ensure dashboard template can be rendered by controller.
     * @return void
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function testDashboardRender(): void
    {
        $twig = new Environment(new ArrayLoader([
            'home/dashboard.html.twig' => 'dashboard',
        ]));

        $controller = new HomeController();
        $siteConfigurationService = $this->createMock(SiteConfigurationService::class);
        $siteConfigurationService->method('isMaintenanceModeEnabled')->willReturn(false);
        $siteSetupOnboardingService = $this->createMock(SiteSetupOnboardingService::class);
        $siteSetupOnboardingService->method('resolveChecklist')->willReturn([]);
        $request = Request::create('/dashboard');
        $response = $controller->dashboard($twig, $siteConfigurationService, $siteSetupOnboardingService, $request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('dashboard', (string) $response->getContent());
    }
}
