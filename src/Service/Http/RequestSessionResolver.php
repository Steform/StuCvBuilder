<?php

declare(strict_types=1);

namespace App\Service\Http;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Safely resolves the HTTP session from the current main request.
 */
class RequestSessionResolver
{
    /**
     * @brief Build request session resolver.
     *
     * @param RequestStack $requestStack Symfony request stack.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @brief Return session when the main request supports and exposes it.
     *
     * @param void No input parameter.
     * @return SessionInterface|null Session or null when unavailable.
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function resolve(): ?SessionInterface
    {
        $request = $this->requestStack->getMainRequest();
        if ($request === null || !$request->hasSession()) {
            return null;
        }

        return $request->getSession();
    }
}
