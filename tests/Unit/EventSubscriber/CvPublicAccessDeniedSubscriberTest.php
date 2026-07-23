<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\EventSubscriber\CvPublicAccessDeniedSubscriber;
use App\Service\Cv\CvAccessSessionService;
use App\Service\Cv\CvPublicAccessPolicyService;
use App\Service\Employment\EmploymentCountryList;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

class CvPublicAccessDeniedSubscriberTest extends TestCase
{
    /**
     * @brief Subscriber must register on kernel request.
     *
     * @return void
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function testSubscribedEvents(): void
    {
        self::assertArrayHasKey(KernelEvents::REQUEST, CvPublicAccessDeniedSubscriber::getSubscribedEvents());
    }

    /**
     * @brief Allowed access must not set a response.
     *
     * @return void
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function testDoesNotRespondWhenAccessAllowed(): void
    {
        $request = Request::create('/cv/');

        $policy = $this->createMock(CvPublicAccessPolicyService::class);
        $policy->method('isAccessAllowed')->with($request)->willReturn(true);

        $cvAccess = $this->createMock(CvAccessSessionService::class);
        $cvAccess->method('isBypassGranted')->willReturn(false);

        $twig = $this->createMock(Environment::class);
        $twig->expects(self::never())->method('render');

        $subscriber = $this->createSubscriber($policy, $cvAccess, $twig);
        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        self::assertFalse($event->hasResponse());
    }

    /**
     * @brief Denied access must return HTTP 403 with branded template.
     *
     * @return void
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function testReturnsForbiddenWhenAccessDenied(): void
    {
        $request = Request::create('/cv/?format=bad');

        $policy = $this->createMock(CvPublicAccessPolicyService::class);
        $policy->method('isAccessAllowed')->with($request)->willReturn(false);

        $cvAccess = $this->createMock(CvAccessSessionService::class);
        $cvAccess->method('isBypassGranted')->willReturn(false);

        $countries = $this->createMock(EmploymentCountryList::class);
        $countries->method('getCountries')->willReturn([]);

        $twig = $this->createMock(Environment::class);
        $twig
            ->expects(self::once())
            ->method('render')
            ->with('cv/access_denied.html.twig', [
                'currentLocale' => $request->getLocale(),
                'employmentCountries' => [],
            ])
            ->willReturn('<html>denied</html>');

        $subscriber = new CvPublicAccessDeniedSubscriber($policy, $cvAccess, $countries, $twig);
        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        self::assertTrue($event->hasResponse());
        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());

        $cacheControl = (string) $response->headers->get('Cache-Control');
        self::assertStringContainsString('no-store', $cacheControl);
        self::assertStringContainsString('no-cache', $cacheControl);
        self::assertStringContainsString('must-revalidate', $cacheControl);
    }

    /**
     * @brief Admin bypass must skip access denial.
     *
     * @return void
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function testBypassSkipsDeniedResponse(): void
    {
        $request = Request::create('/cv/');

        $policy = $this->createMock(CvPublicAccessPolicyService::class);
        $policy->expects(self::never())->method('isAccessAllowed');

        $cvAccess = $this->createMock(CvAccessSessionService::class);
        $cvAccess->method('isBypassGranted')->willReturn(true);

        $subscriber = $this->createSubscriber(
            $policy,
            $cvAccess,
            $this->createMock(Environment::class),
        );
        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        self::assertFalse($event->hasResponse());
    }

    /**
     * @brief /cv/access must remain exempt from denial.
     *
     * @return void
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function testAccessRouteIsExempt(): void
    {
        $request = Request::create('/cv/access');

        $policy = $this->createMock(CvPublicAccessPolicyService::class);
        $policy->expects(self::never())->method('isAccessAllowed');

        $subscriber = $this->createSubscriber(
            $policy,
            $this->createMock(CvAccessSessionService::class),
            $this->createMock(Environment::class),
        );
        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        self::assertFalse($event->hasResponse());
    }

    /**
     * @brief /cv/access-request must remain exempt from denial.
     *
     * @return void
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function testAccessRequestRouteIsExempt(): void
    {
        $request = Request::create('/cv/access-request', 'POST');

        $policy = $this->createMock(CvPublicAccessPolicyService::class);
        $policy->expects(self::never())->method('isAccessAllowed');

        $subscriber = $this->createSubscriber(
            $policy,
            $this->createMock(CvAccessSessionService::class),
            $this->createMock(Environment::class),
        );
        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        self::assertFalse($event->hasResponse());
    }

    /**
     * @brief Build subscriber with country list stub.
     *
     * @param CvPublicAccessPolicyService $policy Access policy mock.
     * @param CvAccessSessionService $cvAccess Session helper mock.
     * @param Environment $twig Twig mock.
     * @return CvPublicAccessDeniedSubscriber
     * @date 2026-07-22
     * @author Stephane H.
     */
    private function createSubscriber(
        CvPublicAccessPolicyService $policy,
        CvAccessSessionService $cvAccess,
        Environment $twig,
    ): CvPublicAccessDeniedSubscriber {
        $countries = $this->createMock(EmploymentCountryList::class);
        $countries->method('getCountries')->willReturn([]);

        return new CvPublicAccessDeniedSubscriber($policy, $cvAccess, $countries, $twig);
    }
}
