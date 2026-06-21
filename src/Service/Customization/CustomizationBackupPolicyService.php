<?php

declare(strict_types=1);

namespace App\Service\Customization;

use Symfony\Component\HttpFoundation\Request;

/**
 * @brief Enforce APP_BACKUP_* policy flags and optional IP allowlist for customization backup actions.
 */
final class CustomizationBackupPolicyService
{
    public const DENIAL_DISABLED_BY_CONFIG = 'disabled_by_config';

    public const DENIAL_IP_NOT_ALLOWED = 'ip_not_allowed';

    /**
     * @param bool $createEnabled Whether export is allowed.
     * @param bool $restoreEnabled Whether restore is allowed.
     * @param bool $resetEnabled Whether CV reset wipe is allowed.
     * @param list<string> $allowedIps Client IPs allowed when list is non-empty.
     */
    public function __construct(
        private readonly bool $createEnabled,
        private readonly bool $restoreEnabled,
        private readonly bool $resetEnabled,
        private readonly array $allowedIps,
    ) {
    }

    /**
     * @brief Check whether export is permitted for the request client.
     *
     * @param Request $request HTTP request.
     * @return bool
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function isExportAllowed(Request $request): bool
    {
        return $this->getExportDenialReason($request) === null;
    }

    /**
     * @brief Check whether restore is permitted for the request client.
     *
     * @param Request $request HTTP request.
     * @return bool
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function isRestoreAllowed(Request $request): bool
    {
        return $this->getRestoreDenialReason($request) === null;
    }

    /**
     * @brief Check whether customization reset (CV wipe) is permitted for the request client.
     *
     * @param Request $request HTTP request.
     * @return bool
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function isResetAllowed(Request $request): bool
    {
        return $this->getResetDenialReason($request) === null;
    }

    /**
     * @brief Resolve why export is denied, if applicable.
     *
     * @param Request $request HTTP request.
     * @return string|null `disabled_by_config`, `ip_not_allowed`, or null when allowed.
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function getExportDenialReason(Request $request): ?string
    {
        return $this->resolveDenialReason($this->createEnabled, $request);
    }

    /**
     * @brief Resolve why restore is denied, if applicable.
     *
     * @param Request $request HTTP request.
     * @return string|null `disabled_by_config`, `ip_not_allowed`, or null when allowed.
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function getRestoreDenialReason(Request $request): ?string
    {
        return $this->resolveDenialReason($this->restoreEnabled, $request);
    }

    /**
     * @brief Resolve why reset is denied, if applicable.
     *
     * @param Request $request HTTP request.
     * @return string|null `disabled_by_config`, `ip_not_allowed`, or null when allowed.
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function getResetDenialReason(Request $request): ?string
    {
        return $this->resolveDenialReason($this->resetEnabled, $request);
    }

    /**
     * @brief Map feature flag and IP allowlist to a denial reason code.
     *
     * @param bool $featureEnabled Whether the action is enabled in configuration.
     * @param Request $request HTTP request.
     * @return string|null Denial reason code or null when allowed.
     * @date 2026-05-19
     * @author Stephane H.
     */
    private function resolveDenialReason(bool $featureEnabled, Request $request): ?string
    {
        if (!$featureEnabled) {
            return self::DENIAL_DISABLED_BY_CONFIG;
        }

        if (!$this->isClientIpAllowed($request)) {
            return self::DENIAL_IP_NOT_ALLOWED;
        }

        return null;
    }

    /**
     * @brief Validate client IP against configured allowlist when non-empty.
     *
     * @param Request $request HTTP request.
     * @return bool
     * @date 2026-05-16
     * @author Stephane H.
     */
    private function isClientIpAllowed(Request $request): bool
    {
        if ($this->allowedIps === []) {
            return true;
        }

        $clientIp = (string) ($request->getClientIp() ?? '');

        return in_array($clientIp, $this->allowedIps, true);
    }
}
