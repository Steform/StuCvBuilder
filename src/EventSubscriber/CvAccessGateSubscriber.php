<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\Cv\CvAccessSessionService;
use App\Service\Employment\CompanyCvVisitService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Redirects unauthenticated CV visitors to the access gate when a recruiter format context is active.
 */
class CvAccessGateSubscriber implements EventSubscriberInterface
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
     * @brief Build CV access gate subscriber.
     *
     * @param CvAccessSessionService $cvAccessSessionService Session access helper.
     * @param CompanyCvVisitService $companyCvVisitService Company visit tracking service.
     * @param UrlGeneratorInterface $urlGenerator Route URL generator.
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function __construct(
        private readonly CvAccessSessionService $cvAccessSessionService,
        private readonly CompanyCvVisitService $companyCvVisitService,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @brief Subscribe to kernel request event.
     *
     * @return array<string, array{0: string, 1: int}|int>
     * @date 2026-05-19
     * @author Stephane H.
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 6],
        ];
    }

    /**
     * @brief Enforce CV access gate on /cv/* routes when recruiter format context is active.
     *
     * @param RequestEvent $event Kernel request event.
     * @return void
     * @date 2026-06-10
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

        if (!str_starts_with($pathInfo, '/cv')) {
            return;
        }

        if ($pathInfo === '/cv' || str_starts_with($pathInfo, '/cv/')) {
            // continue
        } else {
            return;
        }

        foreach (self::EXEMPT_PATHS as $exempt) {
            if ($pathInfo === $exempt || str_starts_with($pathInfo, $exempt.'/')) {
                return;
            }
        }

        if (!$this->cvAccessSessionService->requiresAccessGate($request)) {
            return;
        }

        if ($this->cvAccessSessionService->isBypassGranted() || $this->cvAccessSessionService->isAccessGranted()) {
            return;
        }

        $this->companyCvVisitService->recordGateNotPassedAttempt($request);

        $target = $pathInfo;
        $queryParams = $request->query->all();
        unset($queryParams['score']);
        if ($queryParams !== []) {
            $target .= '?'.http_build_query($queryParams);
        }

        $gateParams = ['target' => $target];
        $activeFormatCode = $this->cvAccessSessionService->getActiveFormatCode();
        if ($activeFormatCode !== '') {
            $gateParams['format'] = $activeFormatCode;
        }

        $gateUrl = $this->urlGenerator->generate('cv_access', $gateParams);
        $event->setResponse(new RedirectResponse($gateUrl));
    }
}
