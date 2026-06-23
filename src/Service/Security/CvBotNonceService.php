<?php

namespace App\Service\Security;

use App\Service\Http\RequestSessionResolver;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * One-time nonces for CV bot-check POST requests.
 */
class CvBotNonceService
{
    private const SESSION_KEY = 'cv_bot_nonce';

    /**
     * @brief Build bot nonce service.
     *
     * @param RequestSessionResolver $requestSessionResolver Safe session accessor.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function __construct(
        private readonly RequestSessionResolver $requestSessionResolver,
    ) {
    }

    /**
     * @brief Create and store a fresh nonce for the current session.
     *
     * @return string Hex nonce value exposed to the client.
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function createNonce(): string
    {
        $nonce = bin2hex(random_bytes(16));
        $session = $this->resolveSession();
        if ($session !== null) {
            $session->set(self::SESSION_KEY, $nonce);
        }

        return $nonce;
    }

    /**
     * @brief Validate nonce and remove it from session (single use).
     *
     * @param string $nonce Nonce submitted by the client.
     * @return bool True when nonce matches stored value.
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function validateAndConsume(string $nonce): bool
    {
        $session = $this->resolveSession();
        if ($session === null) {
            return false;
        }

        $expected = (string) $session->get(self::SESSION_KEY, '');
        $session->remove(self::SESSION_KEY);

        if ($expected === '' || $nonce === '') {
            return false;
        }

        return hash_equals($expected, $nonce);
    }

    /**
     * @brief Resolve HTTP session when available.
     *
     * @return SessionInterface|null
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function resolveSession(): ?SessionInterface
    {
        return $this->requestSessionResolver->resolve();
    }
}
