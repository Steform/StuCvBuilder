<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\Cv\CvAccessSessionService;
use App\Service\Cv\CvPublicAccessPolicyService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

/**
 * @brief Deny public CV routes with HTTP 403 when site access policy blocks the visitor.
 */
class CvPublicAccessDeniedSubscriber implements EventSubscriberInterface
{
    /**
     * @var list<string>
     */
    private const EXEMPT_PATHS = [
        '/cv/access',
        '/cv/captcha',
        '/cv/attestation',
    ];

    /**
     * @brief Build CV public access denied subscriber.
     *
     * @param CvPublicAccessPolicyService $cvPublicAccessPolicyService Access policy helper.
     * @param CvAccessSessionService $cvAccessSessionService Session bypass helper.
     * @param Environment $twig Twig environment.
     * @return void
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function __construct(
        private readonly CvPublicAccessPolicyService $cvPublicAccessPolicyService,
        private readonly CvAccessSessionService $cvAccessSessionService,
        private readonly Environment $twig,
    ) {
    }

    /**
     * @brief Subscribe to kernel request event.
     *
     * @return array<string, array{0: string, 1: int}|int>
     * @date 2026-07-22
     * @author Stephane H.
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 7],
        ];
    }

    /**
     * @brief Block public CV routes when access policy denies the current visitor.
     *
     * @param RequestEvent $event Kernel request event.
     * @return void
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if ($event->hasResponse()) {
            return;
        }

        $request = $event->getRequest();
        $pathInfo = $request->getPathInfo();

        if (!$this->isProtectedCvPath($pathInfo)) {
            return;
        }

        foreach (self::EXEMPT_PATHS as $exempt) {
            if ($pathInfo === $exempt || str_starts_with($pathInfo, $exempt.'/')) {
                return;
            }
        }

        if ($this->cvAccessSessionService->isBypassGranted()) {
            return;
        }

        if ($this->cvPublicAccessPolicyService->isAccessAllowed($request)) {
            return;
        }

        $response = new Response(
            $this->twig->render('cv/access_denied.html.twig', [
                'currentLocale' => $request->getLocale(),
            ]),
            Response::HTTP_FORBIDDEN,
            [
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ],
        );

        $event->setResponse($response);
    }

    /**
     * @brief Return true when the request targets a public CV HTML route.
     *
     * @param string $pathInfo Request path info.
     * @return bool
     * @date 2026-07-22
     * @author Stephane H.
     */
    private function isProtectedCvPath(string $pathInfo): bool
    {
        if (!str_starts_with($pathInfo, '/cv')) {
            return false;
        }

        return $pathInfo === '/cv' || str_starts_with($pathInfo, '/cv/');
    }
}
