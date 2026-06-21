<?php

namespace App\Tests\Functional\Setup;

use App\Entity\User;
use App\EventSubscriber\PendingAdminAccessSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class PendingAdminAccessSubscriberTest extends TestCase
{
    /**
     * @brief Ensure pending admin is redirected to setup.
     * @param void No input parameter.
     * @return void
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function testRedirectsPendingAdminToSetup(): void
    {
        $pendingAdmin = (new User())
            ->setEmail('admin@example.com')
            ->setRoles(['ROLE_ADMIN'])
            ->setSetupConfirmed(false);
        $security = $this->getMockBuilder(Security::class)->disableOriginalConstructor()->getMock();
        $security->method('getUser')->willReturn($pendingAdmin);

        $subscriber = new PendingAdminAccessSubscriber($security);
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, Request::create('/dashboard', 'GET'), HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        self::assertNotNull($event->getResponse());
        self::assertSame(302, $event->getResponse()?->getStatusCode());
        self::assertSame('/setup', (string) $event->getResponse()?->headers->get('Location'));
    }

    /**
     * @brief Ensure confirmed admin can access dashboard.
     * @param void No input parameter.
     * @return void
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function testAllowsConfirmedAdmin(): void
    {
        $confirmedAdmin = (new User())
            ->setEmail('admin@example.com')
            ->setRoles(['ROLE_ADMIN'])
            ->setSetupConfirmed(true);
        $security = $this->getMockBuilder(Security::class)->disableOriginalConstructor()->getMock();
        $security->method('getUser')->willReturn($confirmedAdmin);

        $subscriber = new PendingAdminAccessSubscriber($security);
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, Request::create('/dashboard', 'GET'), HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }
}
