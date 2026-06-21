<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Customization;

use App\Service\Customization\CustomizationBackupPolicyService;
use PHPUnit\Framework\TestCase;
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
        $service = new CustomizationBackupPolicyService(
            createEnabled: true,
            restoreEnabled: true,
            resetEnabled: true,
            allowedIps: ['127.0.0.1'],
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
        $service = new CustomizationBackupPolicyService(
            createEnabled: true,
            restoreEnabled: false,
            resetEnabled: true,
            allowedIps: [],
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
        $service = new CustomizationBackupPolicyService(
            createEnabled: true,
            restoreEnabled: true,
            resetEnabled: true,
            allowedIps: [],
        );

        $request = Request::create('/');
        $request->server->set('REMOTE_ADDR', '203.0.113.10');

        self::assertNull($service->getRestoreDenialReason($request));
        self::assertTrue($service->isRestoreAllowed($request));
    }
}
