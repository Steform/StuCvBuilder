<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\Auth\AuthenticatedLandingResolver;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Class PasswordResetRequiredSubscriber.
 */
class PasswordResetRequiredSubscriber implements EventSubscriberInterface
{
    /**
     * @var list<string>
     */
    private array $allowedPathPrefixes = [
        AuthenticatedLandingResolver::FORCED_PASSWORD_CHANGE_PATH,
        '/login/totp',
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
     * @brief Build password reset required guard subscriber.
     * @param Security $security Security helper.
     * @return void
     * @date 2026-07-27
     * @author Stephane H.
     */
    public function __construct(private readonly Security $security)
    {
    }

    /**
     * @brief Restrict access while a forced password change is pending.
     * @param RequestEvent $event Kernel request event.
     * @return void
     * @date 2026-07-27
     * @author Stephane H.
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if ($request->hasSession() && (int) $request->getSession()->get('auth.totp_pending_user_id', 0) > 0) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User || !$user->isPasswordResetRequired()) {
            return;
        }

        $pathInfo = $request->getPathInfo();
        foreach ($this->allowedPathPrefixes as $prefix) {
            if (str_starts_with($pathInfo, $prefix)) {
                return;
            }
        }

        $event->setResponse(new RedirectResponse(AuthenticatedLandingResolver::FORCED_PASSWORD_CHANGE_PATH));
    }

    /**
     * @brief Return subscribed events.
     * @return array<string, string>
     * @date 2026-07-27
     * @author Stephane H.
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }
}
