<?php

declare(strict_types=1);

namespace App\Service\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;

/**
 * @brief Type-safe flash message helpers for controllers and subscribers.
 */
final class FlashMessageHelper
{
    /**
     * @brief Queue a flash message on the current request session.
     *
     * @param Request $request Current HTTP request.
     * @param string $type Flash type (success, error, warning, info, …).
     * @param mixed $message Translation key, string, or structured payload.
     * @return void
     * @date 2026-06-22
     * @author Stephane H.
     */
    public static function add(Request $request, string $type, mixed $message): void
    {
        $session = $request->getSession();
        if (!$session instanceof FlashBagAwareSessionInterface) {
            return;
        }

        $session->getFlashBag()->add($type, $message);
    }

    /**
     * @brief Read queued flash messages without removing them from the bag.
     *
     * @param Request $request Current HTTP request.
     * @param string $type Flash type to peek.
     * @return list<mixed> Queued messages for the given type.
     * @date 2026-06-22
     * @author Stephane H.
     */
    public static function peek(Request $request, string $type): array
    {
        $session = $request->getSession();
        if (!$session instanceof FlashBagAwareSessionInterface) {
            return [];
        }

        return $session->getFlashBag()->peek($type);
    }
}
