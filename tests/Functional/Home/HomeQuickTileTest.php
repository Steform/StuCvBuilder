<?php

declare(strict_types=1);

namespace App\Tests\Functional\Home;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;

/**
 * @brief Functional contract tests for custom home quick tiles.
 */
final class HomeQuickTileTest extends WebTestCase
{
    private static function projectRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    public function testQuickTilesRouteIsRegistered(): void
    {
        self::bootKernel();
        /** @var RouterInterface $router */
        $router = static::getContainer()->get(RouterInterface::class);

        self::assertNotSame('', $router->generate('app_dashboard_customization_quick_tiles'));
    }

    /**
     * @brief Legacy quick-tiles admin route must redirect into home customization panel.
     *
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function testQuickTilesControllerRedirectsToHomeCustomizationPanel(): void
    {
        $controller = @file_get_contents(self::projectRoot().'/src/Controller/HomeQuickTileController.php') ?: '';
        self::assertStringContainsString("redirectToRoute('app_dashboard_customization_home'", $controller);
        self::assertStringContainsString("'panel' => 'custom_quick_tiles'", $controller);
    }

    public function testSecurityYamlAllowsRoleTuileOnQuickTilesPath(): void
    {
        $yaml = @file_get_contents(self::projectRoot().'/config/packages/security.yaml') ?: '';
        self::assertStringContainsString('^/dashboard/customization/quick-tiles', $yaml);
        self::assertStringContainsString('ROLE_TUILE', $yaml);
    }

    public function testHomeIndexTemplateReferencesCustomTiles(): void
    {
        $twig = @file_get_contents(self::projectRoot().'/templates/home/index.html.twig') ?: '';
        self::assertStringContainsString('homeQuickTiles', $twig);
        self::assertStringContainsString('components/home/_quick_tile.html.twig', $twig);
        self::assertStringContainsString('ROLE_TUILE', $twig);
        $partial = @file_get_contents(self::projectRoot().'/templates/components/home/_quick_tile.html.twig') ?: '';
        self::assertStringContainsString('home-quick-tile__action--edit', $partial);
        self::assertStringContainsString('_quick_tile_delete_modal.html.twig', $twig);
    }

    public function testHomeQuickTileEntityHasNoUserForeignKey(): void
    {
        $entity = @file_get_contents(self::projectRoot().'/src/Entity/HomeQuickTile.php') ?: '';
        self::assertStringNotContainsString('User', $entity);
        self::assertStringNotContainsString('app_user', $entity);
    }
}
