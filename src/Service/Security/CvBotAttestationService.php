<?php

namespace App\Service\Security;

use App\Service\Http\RequestSessionResolver;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Signed session attestation after bot scoring or captcha success.
 */
class CvBotAttestationService
{
    public const METHOD_SIGNALS = 'signals';

    public const METHOD_CAPTCHA = 'captcha';

    private const SESSION_KEY = 'cv_bot_attestation';

    /**
     * @brief Build attestation service.
     *
     * @param RequestSessionResolver $requestSessionResolver Safe session accessor.
     * @param string $secret HMAC signing secret (kernel secret).
     * @param int $ttlSeconds Attestation lifetime in seconds.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function __construct(
        private readonly RequestSessionResolver $requestSessionResolver,
        private readonly string $secret,
        private readonly int $ttlSeconds = 2700,
    ) {
    }

    /**
     * @brief Issue attestation after successful behavioural scoring.
     *
     * @param int $technicalScore Server-computed score (0-100).
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function issueFromSignals(int $technicalScore): void
    {
        $this->issue($technicalScore, self::METHOD_SIGNALS);
    }

    /**
     * @brief Issue attestation after successful captcha verification.
     *
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function issueFromCaptcha(): void
    {
        $this->issue(0, self::METHOD_CAPTCHA);
    }

    /**
     * @brief Check whether a valid attestation exists in session.
     *
     * @return bool True when attestation is present, signed, and not expired.
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function isValid(): bool
    {
        return $this->getPayload() !== null;
    }

    /**
     * @brief Return stored technical score from attestation.
     *
     * @return int Score 0-100, or 0 when no attestation.
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function getScore(): int
    {
        $payload = $this->getPayload();

        return $payload !== null ? (int) ($payload['score'] ?? 0) : 0;
    }

    /**
     * @brief Return attestation method (signals or captcha).
     *
     * @return string|null Method name or null.
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function getMethod(): ?string
    {
        $payload = $this->getPayload();

        return $payload !== null ? (string) ($payload['method'] ?? '') : null;
    }

    /**
     * @brief Whether visit should count for recruiter KPI (signals + score above threshold only).
     *
     * @param int $threshold Configured minimum score.
     * @return bool True when visit is eligible for counting.
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function isCountableForKpi(int $threshold): bool
    {
        return $this->hasValidGateAttestation($threshold);
    }

    /**
     * @brief Whether gate attestation is valid (behavioural score or captcha).
     *
     * @param int|null $threshold Optional score threshold; when null only checks attestation presence.
     * @return bool True when recruiter passed the access gate.
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function hasValidGateAttestation(?int $threshold = null): bool
    {
        $payload = $this->getPayload();
        if ($payload === null) {
            return false;
        }

        $method = (string) ($payload['method'] ?? '');
        if ($method === self::METHOD_CAPTCHA) {
            return true;
        }

        if ($method !== self::METHOD_SIGNALS) {
            return false;
        }

        if ($threshold === null) {
            return true;
        }

        return (int) ($payload['score'] ?? 0) >= $threshold;
    }

    /**
     * @brief Revoke attestation from session.
     *
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function revoke(): void
    {
        $session = $this->resolveSession();
        if ($session === null) {
            return;
        }

        $session->remove(self::SESSION_KEY);
    }

    /**
     * @brief Persist signed attestation payload in session.
     *
     * @param int $score Technical score to store.
     * @param string $method Attestation method constant.
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    private function issue(int $score, string $method): void
    {
        $session = $this->resolveSession();
        if ($session === null) {
            return;
        }

        $issuedAt = time();
        $payload = [
            'score' => max(0, min(100, $score)),
            'method' => $method,
            'issuedAt' => $issuedAt,
            'expiresAt' => $issuedAt + $this->ttlSeconds,
        ];
        $payload['signature'] = $this->sign($payload);
        $session->set(self::SESSION_KEY, $payload);
    }

    /**
     * @brief Read and validate attestation payload from session.
     *
     * @return array<string, mixed>|null Valid payload or null.
     * @date 2026-05-19
     * @author Stephane H.
     */
    private function getPayload(): ?array
    {
        $session = $this->resolveSession();
        if ($session === null) {
            return null;
        }

        $stored = $session->get(self::SESSION_KEY);
        if (!is_array($stored)) {
            return null;
        }

        $signature = (string) ($stored['signature'] ?? '');
        $payload = $stored;
        unset($payload['signature']);

        if ($signature === '' || !hash_equals($this->sign($payload), $signature)) {
            $this->revoke();

            return null;
        }

        if ((int) ($payload['expiresAt'] ?? 0) < time()) {
            $this->revoke();

            return null;
        }

        return $stored;
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

    /**
     * @brief Compute HMAC signature for attestation payload.
     *
     * @param array<string, mixed> $payload Payload without signature key.
     * @return string Hex HMAC digest.
     * @date 2026-05-19
     * @author Stephane H.
     */
    private function sign(array $payload): string
    {
        return hash_hmac('sha256', json_encode($payload, \JSON_THROW_ON_ERROR), $this->secret);
    }
}
