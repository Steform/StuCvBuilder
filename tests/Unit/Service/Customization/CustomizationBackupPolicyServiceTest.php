<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Customization;

use App\Service\Customization\CustomizationBackupPolicyService;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;

final class CustomizationBackupPolicyServiceTest extends TestCase
{
    /**
     * @brief Restore must be denied by IP when allowlist excludes the client.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function testRestoreDeniedByIp(): void
    {
        $service = $this->createService(
            createEnabled: true,
            restoreEnabled: true,
            resetEnabled: true,
            allowedIps: ['127.0.0.1'],
            cvEditBackupEnabled: true,
            isAdmin: true,
            isCvEdit: false,
        );

        $request = Request::create('/');
        $request->server->set('REMOTE_ADDR', '10.0.0.5');

        self::assertFalse($service->isRestoreAllowed($request));
        self::assertSame(
            CustomizationBackupPolicyService::DENIAL_IP_NOT_ALLOWED,
            $service->getRestoreDenialReason($request)
        );
    }

    /**
     * @brief Restore must be denied by configuration flag before IP checks matter.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function testRestoreDeniedByConfigFlag(): void
    {
        $service = $this->createService(
            createEnabled: true,
            restoreEnabled: false,
            resetEnabled: true,
            allowedIps: [],
            cvEditBackupEnabled: true,
            isAdmin: true,
            isCvEdit: false,
        );

        $request = Request::create('/');

        self::assertSame(
            CustomizationBackupPolicyService::DENIAL_DISABLED_BY_CONFIG,
            $service->getRestoreDenialReason($request)
        );
    }

    /**
     * @brief Empty allowlist must permit any client IP when restore is enabled.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function testRestoreAllowedWhenIpListEmpty(): void
    {
        $service = $this->createService(
            createEnabled: true,
            restoreEnabled: true,
            resetEnabled: true,
            allowedIps: [],
            cvEditBackupEnabled: true,
            isAdmin: true,
            isCvEdit: false,
        );

        $request = Request::create('/');
        $request->server->set('REMOTE_ADDR', '203.0.113.10');

        self::assertNull($service->getRestoreDenialReason($request));
        self::assertTrue($service->isRestoreAllowed($request));
    }

    /**
     * @brief ROLE_CV_EDIT must be denied backup actions when env flag is disabled.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-06-22
     * @author Stephane H.
     */
    public function testCvEditBackupDeniedWhenFeatureFlagDisabled(): void
    {
        $service = $this->createService(
            createEnabled: true,
            restoreEnabled: true,
            resetEnabled: true,
            allowedIps: [],
            cvEditBackupEnabled: false,
            isAdmin: false,
            isCvEdit: true,
        );

        $request = Request::create('/');

        self::assertTrue($service->isCvEditBackupRestrictedForCurrentUser());
        self::assertSame(
            CustomizationBackupPolicyService::DENIAL_CV_EDIT_BACKUP_DISABLED,
            $service->getExportDenialReason($request)
        );
        self::assertSame(
            CustomizationBackupPolicyService::DENIAL_CV_EDIT_BACKUP_DISABLED,
            $service->getRestoreDenialReason($request)
        );
        self::assertSame(
            CustomizationBackupPolicyService::DENIAL_CV_EDIT_BACKUP_DISABLED,
            $service->getResetDenialReason($request)
        );
    }

    /**
     * @brief ROLE_CV_EDIT may run backup actions when env flag is enabled.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-06-22
     * @author Stephane H.
     */
    public function testCvEditBackupAllowedWhenFeatureFlagEnabled(): void
    {
        $service = $this->createService(
            createEnabled: true,
            restoreEnabled: true,
            resetEnabled: true,
            allowedIps: [],
            cvEditBackupEnabled: true,
            isAdmin: false,
            isCvEdit: true,
        );

        $request = Request::create('/');

        self::assertFalse($service->isCvEditBackupRestrictedForCurrentUser());
        self::assertTrue($service->isExportAllowed($request));
        self::assertTrue($service->isRestoreAllowed($request));
        self::assertTrue($service->isResetAllowed($request));
    }

    /**
     * @brief Build policy service with mocked security roles.
     *
     * @param bool $createEnabled Whether export is enabled.
     * @param bool $restoreEnabled Whether restore is enabled.
     * @param bool $resetEnabled Whether reset is enabled.
     * @param list<string> $allowedIps Allowed client IPs.
     * @param bool $cvEditBackupEnabled Whether ROLE_CV_EDIT backup actions are enabled.
     * @param bool $isAdmin Whether current user is admin.
     * @param bool $isCvEdit Whether current user has ROLE_CV_EDIT.
     * @return CustomizationBackupPolicyService
     * @date 2026-06-22
     * @author Stephane H.
     */
    private function createService(
        bool $createEnabled,
        bool $restoreEnabled,
        bool $resetEnabled,
        array $allowedIps,
        bool $cvEditBackupEnabled,
        bool $isAdmin,
        bool $isCvEdit,
    ): CustomizationBackupPolicyService {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')->willReturnCallback(
            static fn (string $role): bool => match ($role) {
                'ROLE_ADMIN' => $isAdmin,
                'ROLE_CV_EDIT' => $isCvEdit,
                default => false,
            }
        );

        return new CustomizationBackupPolicyService(
            $createEnabled,
            $restoreEnabled,
            $resetEnabled,
            $allowedIps,
            $cvEditBackupEnabled,
            $security,
        );
    }
}
