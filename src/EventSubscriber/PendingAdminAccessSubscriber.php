<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Class PendingAdminAccessSubscriber.
 */
class PendingAdminAccessSubscriber implements EventSubscriberInterface
{
    /**
     * @var list<string>
     */
    private array $allowedPathPrefixes = [
        '/setup',
        '/login',
        '/logout',
        '/locale',
        '/theme',
        '/_profiler',
        '/_wdt',
        '/css',
        '/js',
        '/images',
    ];

    /**
     * @brief Build pending admin guard subscriber.
     * @param Security $security Security helper.
     * @return void
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function __construct(private readonly Security $security)
    {
    }

    /**
     * @brief Block sensitive access for pending admin accounts.
     * @param RequestEvent $event Kernel request event.
     * @return void
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $pathInfo = $request->getPathInfo();
        foreach ($this->allowedPathPrefixes as $prefix) {
            if (str_starts_with($pathInfo, $prefix)) {
                return;
            }
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        if (in_array('ROLE_ADMIN', $user->getRoles(), true) && !$user->isSetupConfirmed()) {
            $request = $event->getRequest();
            if ($request->hasSession()) {
                $request->getSession()->getFlashBag()->add('info', 'setup.pending_confirmation');
            }
            $event->setResponse(new RedirectResponse('/setup'));
        }
    }

    /**
     * @brief Return subscribed events.
     * @param void No input parameter.
     * @return array<string, string>
     * @date 2026-04-23
     * @author Stephane H.
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }
}
