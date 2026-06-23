<?php

namespace App\Tests\Functional\Setup;

use App\EventSubscriber\SetupBootstrapSubscriber;
use App\Service\Setup\SetupStateService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class SetupBootstrapSubscriberTest extends TestCase
{
    /**
     * @brief Ensure subscriber redirects when no admin exists.
     * @return void
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function testRedirectsToSetupWhenNoAdmin(): void
    {
        $setupStateService = $this->getMockBuilder(SetupStateService::class)->disableOriginalConstructor()->getMock();
        $setupStateService->method('getSetupStatus')->willReturn(SetupStateService::STATUS_NO_ADMIN);
        $subscriber = new SetupBootstrapSubscriber($setupStateService);
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, Request::create('/dashboard', 'GET'), HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        self::assertNotNull($event->getResponse());
        self::assertSame(302, $event->getResponse()?->getStatusCode());
        self::assertSame('/setup', $event->getResponse()?->headers->get('Location'));
    }

    /**
     * @brief Ensure subscriber does not redirect setup route.
     * @return void
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function testAllowsSetupRouteWhenNoAdmin(): void
    {
        $setupStateService = $this->getMockBuilder(SetupStateService::class)->disableOriginalConstructor()->getMock();
        $setupStateService->method('getSetupStatus')->willReturn(SetupStateService::STATUS_NO_ADMIN);
        $subscriber = new SetupBootstrapSubscriber($setupStateService);
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, Request::create('/setup', 'GET'), HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    /**
     * @brief Ensure subscriber allows requests once admin is confirmed.
     * @return void
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function testAllowsRequestWhenConfirmedAdminExists(): void
    {
        $setupStateService = $this->getMockBuilder(SetupStateService::class)->disableOriginalConstructor()->getMock();
        $setupStateService->method('getSetupStatus')->willReturn(SetupStateService::STATUS_CONFIRMED_ADMIN);
        $subscriber = new SetupBootstrapSubscriber($setupStateService);
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, Request::create('/dashboard', 'GET'), HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    /**
     * @brief Ensure subscriber redirects to setup when setup status lookup fails.
     * @return void
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function testRedirectsToSetupWhenSetupStatusThrows(): void
    {
        $setupStateService = $this->getMockBuilder(SetupStateService::class)->disableOriginalConstructor()->getMock();
        $setupStateService->method('getSetupStatus')->willThrowException(new \RuntimeException('db_error'));
        $subscriber = new SetupBootstrapSubscriber($setupStateService);
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, Request::create('/dashboard', 'GET'), HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        self::assertNotNull($event->getResponse());
        self::assertSame(302, $event->getResponse()?->getStatusCode());
        self::assertSame('/setup', $event->getResponse()?->headers->get('Location'));
    }
}
