<?php

declare(strict_types=1);

namespace App\Service\Auth;

use Psr\Log\LoggerInterface;

/**
 * @brief Debug-only structured trace for the TOTP challenge and email delivery chain.
 */
final class TotpFlowDebugLogger
{
    /**
     * @brief Wire TOTP flow debug logger.
     *
     * @param LoggerInterface $logger Monolog channel `totp_flow`.
     * @param bool $enabled True when kernel.debug is enabled (APP_DEBUG=1).
     * @return void
     * @date 2026-06-22
     * @author Stephane H.
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly bool $enabled,
    ) {
    }

    /**
     * @brief Log one TOTP flow step when debug logging is enabled.
     *
     * @param string $action Short action slug (e.g. email_sent, challenge_create).
     * @param array<string, mixed> $context Structured context payload.
     * @return void
     * @date 2026-06-22
     * @author Stephane H.
     */
    public function log(string $action, array $context = []): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->logger->info('[totp_flow] '.$action, $context);
    }

    /**
     * @brief Redact credentials from a mailer DSN before logging.
     *
     * @param string $dsn Raw MAILER_DSN value.
     * @return string Redacted DSN safe for logs.
     * @date 2026-06-22
     * @author Stephane H.
     */
    public static function redactMailerDsn(string $dsn): string
    {
        $trimmed = trim($dsn);
        if ($trimmed === '') {
            return '';
        }

        $parts = parse_url($trimmed);
        if (!is_array($parts)) {
            return '[invalid-dsn]';
        }

        $scheme = is_string($parts['scheme'] ?? null) ? $parts['scheme'] : 'smtp';
        $host = is_string($parts['host'] ?? null) ? $parts['host'] : 'unknown';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $user = is_string($parts['user'] ?? null) ? rawurldecode($parts['user']) : '';
        $query = is_string($parts['query'] ?? null) && $parts['query'] !== '' ? '?'.$parts['query'] : '';

        if ($user === '') {
            return $scheme.'://'.$host.$port.$query;
        }

        return $scheme.'://'.$user.':***@'.$host.$port.$query;
    }
}
