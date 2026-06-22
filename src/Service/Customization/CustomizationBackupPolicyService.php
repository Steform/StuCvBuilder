<?php

declare(strict_types=1);

namespace App\Service\Customization;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;

/**
 * @brief Enforce APP_BACKUP_* policy flags, optional IP allowlist, and ROLE_CV_EDIT backup restrictions.
 */
final class CustomizationBackupPolicyService
{
    public const DENIAL_DISABLED_BY_CONFIG = 'disabled_by_config';

    public const DENIAL_IP_NOT_ALLOWED = 'ip_not_allowed';

    public const DENIAL_CV_EDIT_BACKUP_DISABLED = 'cv_edit_backup_disabled';

    /**
     * @param bool $createEnabled Whether export is allowed.
     * @param bool $restoreEnabled Whether restore is allowed.
     * @param bool $resetEnabled Whether CV reset wipe is allowed.
     * @param list<string> $allowedIps Client IPs allowed when list is non-empty.
     * @param bool $cvEditBackupEnabled Whether ROLE_CV_EDIT may run backup destructive actions.
     * @param Security $security Security helper for role checks.
     */
    public function __construct(
        private readonly bool $createEnabled,
        private readonly bool $restoreEnabled,
        private readonly bool $resetEnabled,
        private readonly array $allowedIps,
        private readonly bool $cvEditBackupEnabled,
        private readonly Security $security,
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
     * @return string|null Denial reason code or null when allowed.
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function getExportDenialReason(Request $request): ?string
    {
        $cvEditDenial = $this->getCvEditBackupDenialReason();
        if ($cvEditDenial !== null) {
            return $cvEditDenial;
        }

        return $this->resolveDenialReason($this->createEnabled, $request);
    }

    /**
     * @brief Resolve why restore is denied, if applicable.
     *
     * @param Request $request HTTP request.
     * @return string|null Denial reason code or null when allowed.
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function getRestoreDenialReason(Request $request): ?string
    {
        $cvEditDenial = $this->getCvEditBackupDenialReason();
        if ($cvEditDenial !== null) {
            return $cvEditDenial;
        }

        return $this->resolveDenialReason($this->restoreEnabled, $request);
    }

    /**
     * @brief Resolve why reset is denied, if applicable.
     *
     * @param Request $request HTTP request.
     * @return string|null Denial reason code or null when allowed.
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function getResetDenialReason(Request $request): ?string
    {
        $cvEditDenial = $this->getCvEditBackupDenialReason();
        if ($cvEditDenial !== null) {
            return $cvEditDenial;
        }

        return $this->resolveDenialReason($this->resetEnabled, $request);
    }

    /**
     * @brief Check whether the current principal is a CV editor without backup privileges.
     *
     * @param void No input parameter.
     * @return bool
     * @date 2026-06-22
     * @author Stephane H.
     */
    public function isCvEditBackupRestrictedForCurrentUser(): bool
    {
        return $this->getCvEditBackupDenialReason() !== null;
    }

    /**
     * @brief Resolve whether the current principal is a CV editor without backup privileges.
     *
     * @param void No input parameter.
     * @return string|null `cv_edit_backup_disabled` or null when backup actions are allowed for the role.
     * @date 2026-06-22
     * @author Stephane H.
     */
    private function getCvEditBackupDenialReason(): ?string
    {
        if ($this->cvEditBackupEnabled) {
            return null;
        }

        if ($this->security->isGranted('ROLE_ADMIN')) {
            return null;
        }

        if ($this->security->isGranted('ROLE_CV_EDIT')) {
            return self::DENIAL_CV_EDIT_BACKUP_DISABLED;
        }

        return null;
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
