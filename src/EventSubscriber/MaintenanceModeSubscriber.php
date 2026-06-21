<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\Site\SiteConfigurationService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Environment;

/**
 * @brief Replace public home and CV routes with a maintenance page when enabled from the dashboard.
 */
final class MaintenanceModeSubscriber implements EventSubscriberInterface
{
    /**
     * @brief Build maintenance mode subscriber.
     *
     * @param SiteConfigurationService $siteConfigurationService Site configuration service.
     * @param AuthorizationCheckerInterface $authorizationChecker Security authorization helper.
     * @param Environment $twig Twig environment.
     * @return void
     * @date 2026-06-08
     * @author Stephane H.
     */
    public function __construct(
        private readonly SiteConfigurationService $siteConfigurationService,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly Environment $twig,
    ) {
    }

    /**
     * @brief Subscribe to kernel request event.
     *
     * @param void No input parameter.
     * @return array<string, array<int, int>|int>
     * @date 2026-06-08
     * @author Stephane H.
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 8],
        ];
    }

    /**
     * @brief Serve maintenance page on public home and CV routes when maintenance mode is active.
     *
     * @param RequestEvent $event Kernel request event.
     * @return void
     * @date 2026-06-08
     * @author Stephane H.
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (!$this->siteConfigurationService->isMaintenanceModeEnabled()) {
            return;
        }

        if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            return;
        }

        $pathInfo = $event->getRequest()->getPathInfo();
        if (!$this->isMaintenanceProtectedPath($pathInfo)) {
            return;
        }

        $response = new Response(
            $this->twig->render('maintenance/index.html.twig'),
            Response::HTTP_SERVICE_UNAVAILABLE,
            [
                'Retry-After' => '3600',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ],
        );

        $event->setResponse($response);
    }

    /**
     * @brief Return true when the request path should be replaced by the maintenance page.
     *
     * @param string $pathInfo Request path info.
     * @return bool
     * @date 2026-06-08
     * @author Stephane H.
     */
    private function isMaintenanceProtectedPath(string $pathInfo): bool
    {
        if ($pathInfo === '/') {
            return true;
        }

        return $pathInfo === '/cv' || str_starts_with($pathInfo, '/cv/');
    }
}
