<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Home\HomeQuickTileService;
use App\Service\Locale\LocaleConfigurationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @brief Manage global custom home quick tiles (ROLE_TUILE).
 */
class HomeQuickTileController extends AbstractController
{
    /**
     * @brief Handle quick tile POST actions; GET redirects to home customization panel.
     *
     * @param Request $request Current HTTP request.
     * @param HomeQuickTileService $quickTileService Quick tile service.
     * @param LocaleConfigurationService $localeConfigurationService Locale configuration.
     * @return Response Redirect or POST handling result.
     * @date 2026-05-19
     * @author Stephane H.
     */
    #[IsGranted('ROLE_TUILE')]
    #[Route('/dashboard/customization/quick-tiles', name: 'app_dashboard_customization_quick_tiles', methods: ['GET', 'POST'])]
    public function manage(
        Request $request,
        HomeQuickTileService $quickTileService,
        LocaleConfigurationService $localeConfigurationService,
    ): Response {
        $localeConfiguration = $localeConfigurationService->getConfiguration();
        $activeLocales = is_array($localeConfiguration['activeLocales'] ?? null)
            ? $localeConfiguration['activeLocales']
            : [];
        if ($activeLocales === []) {
            $activeLocales = $localeConfigurationService->getSupportedLocales();
        }
        $defaultLocale = is_string($localeConfiguration['defaultLocale'] ?? null)
            ? $localeConfiguration['defaultLocale']
            : ($activeLocales[0] ?? 'fr');

        if ($request->isMethod('POST')) {
            return $this->handlePost($request, $quickTileService, $activeLocales, $defaultLocale);
        }

        $params = ['panel' => 'custom_quick_tiles'];
        $editId = (int) $request->query->get('edit', 0);
        if ($editId > 0) {
            $params['edit'] = $editId;
        }

        return $this->redirectToRoute('app_dashboard_customization_home', $params);
    }

    /**
     * @brief Handle POST mutations for quick tiles.
     * @param Request $request HTTP request.
     * @param HomeQuickTileService $quickTileService Quick tile service.
     * @param array<int, string> $activeLocales Active locale codes.
     * @param string $defaultLocale Default locale code.
     * @return Response
     * @date 2026-05-18
     * @author Stephane H.
     */
    private function handlePost(
        Request $request,
        HomeQuickTileService $quickTileService,
        array $activeLocales,
        string $defaultLocale,
    ): Response {
        $csrfToken = (string) $request->request->get('_csrf_token', '');
        if (!$this->isCsrfTokenValid('dashboard_customization_quick_tiles', $csrfToken)) {
            $this->addFlash('warning', 'dashboard.customization_quick_tiles.flash.invalid_csrf');

            return $this->redirectAfterAction($request);
        }

        $action = (string) $request->request->get('action', 'create');
        $tileId = (int) $request->request->get('tile_id', 0);
        $redirectHome = $this->isTruthyRequestFlag($request, 'redirect_home');

        try {
            match ($action) {
                'create' => $this->handleCreate($request, $quickTileService, $activeLocales),
                'update' => $this->handleUpdate($request, $quickTileService, $activeLocales, $tileId),
                'delete' => $this->handleDelete($quickTileService, $tileId),
                'move_up' => $this->handleMove($quickTileService, $tileId, true),
                'move_down' => $this->handleMove($quickTileService, $tileId, false),
                default => throw new \InvalidArgumentException('dashboard.customization_quick_tiles.flash.invalid_action'),
            };

            $this->addFlash('success', 'dashboard.customization_quick_tiles.flash.saved');
        } catch (\InvalidArgumentException $exception) {
            $this->addFlash('warning', $exception->getMessage());
        }

        if ($redirectHome) {
            return $this->redirectToRoute('app_home');
        }

        return $this->redirectAfterAction($request, $tileId, $action);
    }

    /**
     * @brief Create tile from POST payload.
     * @param Request $request HTTP request.
     * @param HomeQuickTileService $quickTileService Quick tile service.
     * @param array<int, string> $activeLocales Active locales.
     * @return void
     * @date 2026-05-18
     * @author Stephane H.
     */
    private function handleCreate(Request $request, HomeQuickTileService $quickTileService, array $activeLocales): void
    {
        $quickTileService->createFromRequest($request, $activeLocales);
    }

    /**
     * @brief Update tile from POST payload.
     * @param Request $request HTTP request.
     * @param HomeQuickTileService $quickTileService Quick tile service.
     * @param array<int, string> $activeLocales Active locales.
     * @param int $tileId Tile id.
     * @return void
     * @date 2026-05-18
     * @author Stephane H.
     */
    private function handleUpdate(
        Request $request,
        HomeQuickTileService $quickTileService,
        array $activeLocales,
        int $tileId,
    ): void {
        $tile = $quickTileService->findTileForAdmin($tileId);
        if ($tile === null) {
            throw new \InvalidArgumentException('dashboard.customization_quick_tiles.flash.not_found');
        }

        $quickTileService->updateFromRequest($tile, $request, $activeLocales);
    }

    /**
     * @brief Delete tile by id.
     * @param HomeQuickTileService $quickTileService Quick tile service.
     * @param int $tileId Tile id.
     * @return void
     * @date 2026-05-18
     * @author Stephane H.
     */
    private function handleDelete(HomeQuickTileService $quickTileService, int $tileId): void
    {
        $tile = $quickTileService->findTileForAdmin($tileId);
        if ($tile === null) {
            throw new \InvalidArgumentException('dashboard.customization_quick_tiles.flash.not_found');
        }

        $quickTileService->delete($tile);
    }

    /**
     * @brief Reorder tile up or down.
     * @param HomeQuickTileService $quickTileService Quick tile service.
     * @param int $tileId Tile id.
     * @param bool $moveUp True to move up.
     * @return void
     * @date 2026-05-18
     * @author Stephane H.
     */
    private function handleMove(HomeQuickTileService $quickTileService, int $tileId, bool $moveUp): void
    {
        $tile = $quickTileService->findTileForAdmin($tileId);
        if ($tile === null) {
            throw new \InvalidArgumentException('dashboard.customization_quick_tiles.flash.not_found');
        }

        if ($moveUp) {
            $quickTileService->moveUp($tile);
        } else {
            $quickTileService->moveDown($tile);
        }
    }

    /**
     * @brief Redirect after POST to the home customization custom tiles panel.
     *
     * @param Request $request HTTP request.
     * @param int $tileId Last tile id.
     * @param string $action Last action name.
     * @return Response Redirect to home customization with panel open.
     * @date 2026-05-19
     * @author Stephane H.
     */
    private function redirectAfterAction(Request $request, int $tileId = 0, string $action = ''): Response
    {
        $params = ['panel' => 'custom_quick_tiles'];
        if ($action === 'update' && $tileId > 0) {
            $params['edit'] = $tileId;
        }

        return $this->redirectToRoute('app_dashboard_customization_home', $params);
    }

    /**
     * @brief Interpret checkbox-style request flags.
     * @param Request $request HTTP request.
     * @param string $fieldName Field name.
     * @return bool
     * @date 2026-05-18
     * @author Stephane H.
     */
    private function isTruthyRequestFlag(Request $request, string $fieldName): bool
    {
        $value = $request->request->get($fieldName);

        return in_array($value, [1, '1', true, 'true', 'on', 'yes'], true);
    }
}
