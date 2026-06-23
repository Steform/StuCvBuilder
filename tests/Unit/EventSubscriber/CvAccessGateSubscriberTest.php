<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\EventSubscriber\CvAccessGateSubscriber;
use App\Service\Cv\CvAccessSessionService;
use App\Service\Employment\CompanyCvVisitService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class CvAccessGateSubscriberTest extends TestCase
{
    /**
     * @brief Subscriber must register on kernel request.
     *
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function testSubscribedEvents(): void
    {
        self::assertArrayHasKey(KernelEvents::REQUEST, CvAccessGateSubscriber::getSubscribedEvents());
    }

    /**
     * @brief Public CV without format context must not be redirected to gate.
     *
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testDoesNotRedirectWithoutFormatContext(): void
    {
        $request = Request::create('/cv/');

        $cvAccess = $this->createMock(CvAccessSessionService::class);
        $cvAccess->method('requiresAccessGate')->with($request)->willReturn(false);
        $cvAccess->expects(self::never())->method('isBypassGranted');
        $cvAccess->expects(self::never())->method('isAccessGranted');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects(self::never())->method('generate');

        $subscriber = new CvAccessGateSubscriber(
            $cvAccess,
            $this->createMock(CompanyCvVisitService::class),
            $urlGenerator,
        );
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        self::assertFalse($event->hasResponse());
    }

    /**
     * @brief Recruiter format context redirects unauthenticated visitor to gate.
     *
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testRedirectsWhenRecruiterFormatContextActive(): void
    {
        $request = Request::create('/cv/?format=Ab3xY9kLm2Qp');

        $cvAccess = $this->createMock(CvAccessSessionService::class);
        $cvAccess->method('requiresAccessGate')->with($request)->willReturn(true);
        $cvAccess->method('isBypassGranted')->willReturn(false);
        $cvAccess->method('isAccessGranted')->willReturn(false);
        $cvAccess->method('getActiveFormatCode')->willReturn('Ab3xY9kLm2Qp');

        $companyCvVisitService = $this->createMock(CompanyCvVisitService::class);
        $companyCvVisitService
            ->expects(self::once())
            ->method('recordGateNotPassedAttempt');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->expects(self::once())
            ->method('generate')
            ->with(
                'cv_access',
                [
                    'target' => '/cv/?format=Ab3xY9kLm2Qp',
                    'format' => 'Ab3xY9kLm2Qp',
                ],
            )
            ->willReturn('/cv/access');

        $subscriber = new CvAccessGateSubscriber($cvAccess, $companyCvVisitService, $urlGenerator);
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        self::assertTrue($event->hasResponse());
        self::assertSame(302, $event->getResponse()?->getStatusCode());
    }

    /**
     * @brief Sticky session format keeps gate active even when query param is removed.
     *
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testRedirectsWhenStickyFormatContextWithoutQueryParam(): void
    {
        $request = Request::create('/cv/');

        $cvAccess = $this->createMock(CvAccessSessionService::class);
        $cvAccess->method('requiresAccessGate')->with($request)->willReturn(true);
        $cvAccess->method('isBypassGranted')->willReturn(false);
        $cvAccess->method('isAccessGranted')->willReturn(false);
        $cvAccess->method('getActiveFormatCode')->willReturn('Ab3xY9kLm2Qp');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->expects(self::once())
            ->method('generate')
            ->with(
                'cv_access',
                [
                    'target' => '/cv/',
                    'format' => 'Ab3xY9kLm2Qp',
                ],
            )
            ->willReturn('/cv/access');

        $subscriber = new CvAccessGateSubscriber(
            $cvAccess,
            $this->createMock(CompanyCvVisitService::class),
            $urlGenerator,
        );
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        self::assertTrue($event->hasResponse());
        self::assertSame(302, $event->getResponse()?->getStatusCode());
    }

    /**
     * @brief Existing responses from higher priority subscribers must not be overwritten.
     *
     * @return void
     * @date 2026-06-10
     * @author Stephane H.
     */
    public function testExistingResponseIsPreserved(): void
    {
        $request = Request::create('/cv/');

        $cvAccess = $this->createMock(CvAccessSessionService::class);
        $cvAccess->expects(self::never())->method('requiresAccessGate');
        $cvAccess->expects(self::never())->method('isBypassGranted');
        $cvAccess->expects(self::never())->method('isAccessGranted');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects(self::never())->method('generate');

        $subscriber = new CvAccessGateSubscriber(
            $cvAccess,
            $this->createMock(CompanyCvVisitService::class),
            $urlGenerator,
        );
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $maintenanceResponse = new Response('maintenance', Response::HTTP_SERVICE_UNAVAILABLE);
        $event->setResponse($maintenanceResponse);

        $subscriber->onKernelRequest($event);

        self::assertSame($maintenanceResponse, $event->getResponse());
        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $event->getResponse()?->getStatusCode());
    }

    /**
     * @brief /cv/captcha must not be intercepted.
     *
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function testCaptchaRouteIsExempt(): void
    {
        $request = Request::create('/cv/captcha');
        $cvAccess = $this->createMock(CvAccessSessionService::class);
        $cvAccess->method('isAccessGranted')->willReturn(false);

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $subscriber = new CvAccessGateSubscriber(
            $cvAccess,
            $this->createMock(CompanyCvVisitService::class),
            $urlGenerator,
        );
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        self::assertFalse($event->hasResponse());
    }
}
