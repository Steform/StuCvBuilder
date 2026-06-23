<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Employment\ConnectionKind;
use App\Service\Cv\CvAccessSessionService;
use App\Service\Employment\CompanyCvVisitService;
use App\Service\Employment\ConnectionLogContext;
use App\Service\Employment\CvConnectionLoggingService;
use App\Service\Employment\VisitorCountryResolver;
use App\Service\Security\CvBotAccessService;
use App\Service\Security\CvBotAttestationService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Records CV journey steps and random connection logs on public CV routes.
 */
class CvConnectionTrackingSubscriber implements EventSubscriberInterface
{
    /**
     * @var list<string>
     */
    private const EXEMPT_PATHS = [
        '/cv/access',
        '/cv/captcha',
        '/cv/attestation',
        '/cv/bot-check',
    ];

    /**
     * @brief Build CV connection tracking subscriber.
     *
     * @param CompanyCvVisitService $companyCvVisitService Visit service.
     * @param CvConnectionLoggingService $cvConnectionLoggingService Connection logger.
     * @param CvAccessSessionService $cvAccessSessionService Format session.
     * @param CvBotAccessService $cvBotAccessService Bot access policy.
     * @param CvBotAttestationService $cvBotAttestationService Attestation helper.
     * @param VisitorCountryResolver $visitorCountryResolver Country resolver.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function __construct(
        private readonly CompanyCvVisitService $companyCvVisitService,
        private readonly CvConnectionLoggingService $cvConnectionLoggingService,
        private readonly CvAccessSessionService $cvAccessSessionService,
        private readonly CvBotAccessService $cvBotAccessService,
        private readonly CvBotAttestationService $cvBotAttestationService,
        private readonly VisitorCountryResolver $visitorCountryResolver,
    ) {
    }

    /**
     * @brief Subscribe to kernel request.
     *
     * @return array<string, array{0: string, 1: int}|int>
     * @date 2026-06-01
     * @author Stephane H.
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', -5],
        ];
    }

    /**
     * @brief Track journey and log random connections after gate.
     *
     * @param RequestEvent $event Kernel request event.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!$request->hasSession()) {
            return;
        }
        $pathInfo = $request->getPathInfo();
        if (!str_starts_with($pathInfo, '/cv')) {
            return;
        }

        foreach (self::EXEMPT_PATHS as $exempt) {
            if ($pathInfo === $exempt || str_starts_with($pathInfo, $exempt.'/')) {
                return;
            }
        }

        if (!$this->cvAccessSessionService->isBypassGranted() && !$this->cvAccessSessionService->isAccessGranted()) {
            return;
        }

        $route = (string) $request->attributes->get('_route', '');
        if ($route === 'cv_show') {
            return;
        }

        $company = $this->companyCvVisitService->resolveActiveCompanyFromSession();
        if ($company !== null && $this->cvBotAccessService->isEligibleForCompanyVisit()) {
            $this->companyCvVisitService->appendJourneyForRequest($request);

            return;
        }

        if ($company === null && $this->cvAccessSessionService->getActiveFormatCode() === '') {
            $this->logRandomConnection($request);
        }
    }

    /**
     * @brief Log random connection without company format.
     *
     * @param Request $request HTTP request.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function logRandomConnection(Request $request): void
    {
        if ($this->cvBotAccessService->isAdminBypassForTracking()) {
            return;
        }

        $this->cvConnectionLoggingService->log(new ConnectionLogContext(
            connectionKind: ConnectionKind::RANDOM,
            formatRaw: trim((string) $request->query->get('format', '')) ?: null,
            ipAddress: (string) ($request->getClientIp() ?? ''),
            countryCode: $this->visitorCountryResolver->resolve($request),
            userAgent: (string) $request->headers->get('User-Agent', ''),
            gatePassed: $this->cvBotAttestationService->hasValidGateAttestation(),
            attestationMethod: $this->cvBotAttestationService->getMethod(),
            technicalScore: $this->cvBotAttestationService->getScore(),
            requestPath: $request->getPathInfo(),
            requestRoute: (string) $request->attributes->get('_route', ''),
        ));
    }
}
