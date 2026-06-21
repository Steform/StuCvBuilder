<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @brief Attach signed trusted-device cookies set during TOTP remember flow.
 */
final class TrustedDeviceCookieSubscriber implements EventSubscriberInterface
{
    public const REQUEST_ATTRIBUTE = '_trusted_device_cookie';

    /**
     * @brief Register trusted-device cookie on outgoing responses when requested.
     *
     * @param ResponseEvent $event Kernel response event.
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $cookieValue = $request->attributes->get(self::REQUEST_ATTRIBUTE);
        if (!is_string($cookieValue) || $cookieValue === '') {
            return;
        }

        $event->getResponse()->headers->setCookie(
            Cookie::create('stu_trusted_device')
                ->withValue($cookieValue)
                ->withExpires(time() + 15552000)
                ->withPath('/')
                ->withSecure($request->isSecure())
                ->withHttpOnly(true)
                ->withSameSite(Cookie::SAMESITE_LAX)
        );
    }

    /**
     * @brief Return subscribed kernel events.
     *
     * @return array<string, string>
     * @date 2026-06-21
     * @author Stephane H.
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }
}
