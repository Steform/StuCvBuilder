<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Cv;

use App\Cv\CvFormatContext;
use App\Cv\CvInvalidFormatPolicy;
use App\Cv\CvPublicAccessMode;
use App\Entity\HomeCustomization;
use App\Service\Cv\CvAccessSessionService;
use App\Service\Cv\CvPublicAccessPolicyService;
use App\Service\Home\HomeCustomizationService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class CvPublicAccessPolicyServiceTest extends TestCase
{
    /**
     * @brief Build policy service with configured access settings and format context.
     *
     * @param CvPublicAccessMode $accessMode Public access mode.
     * @param CvInvalidFormatPolicy $invalidFormatPolicy Invalid format policy.
     * @param CvFormatContext $formatContext Resolved format context.
     * @return CvPublicAccessPolicyService
     * @date 2026-07-22
     * @author Stephane H.
     */
    private function createService(
        CvPublicAccessMode $accessMode,
        CvInvalidFormatPolicy $invalidFormatPolicy,
        CvFormatContext $formatContext,
    ): CvPublicAccessPolicyService {
        $homeCustomization = new HomeCustomization();
        $homeCustomization->setCvPublicAccessMode($accessMode);
        $homeCustomization->setCvInvalidFormatPolicy($invalidFormatPolicy);

        $homeCustomizationService = $this->createMock(HomeCustomizationService::class);
        $homeCustomizationService->method('getOrCreateSingleton')->willReturn($homeCustomization);

        $cvAccessSessionService = $this->createMock(CvAccessSessionService::class);
        $cvAccessSessionService
            ->method('resolveFormatContext')
            ->willReturn($formatContext);

        return new CvPublicAccessPolicyService($homeCustomizationService, $cvAccessSessionService);
    }

    /**
     * @brief Gated mode without format allows access and skips gate.
     *
     * @return void
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function testGatedModeWithoutFormatAllowsAccessWithoutGate(): void
    {
        $service = $this->createService(CvPublicAccessMode::Gated, CvInvalidFormatPolicy::Allow, CvFormatContext::None);
        $request = Request::create('/cv/');

        self::assertTrue($service->isAccessAllowed($request));
        self::assertFalse($service->requiresAccessGate($request));
    }

    /**
     * @brief Gated mode with valid format requires gate.
     *
     * @return void
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function testGatedModeWithValidFormatRequiresGate(): void
    {
        $service = $this->createService(CvPublicAccessMode::Gated, CvInvalidFormatPolicy::Allow, CvFormatContext::Valid);
        $request = Request::create('/cv/?format=Ab3xY9kLm2Qp');

        self::assertTrue($service->isAccessAllowed($request));
        self::assertTrue($service->requiresAccessGate($request));
    }

    /**
     * @brief Open mode with valid format skips gate.
     *
     * @return void
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function testOpenModeWithValidFormatSkipsGate(): void
    {
        $service = $this->createService(CvPublicAccessMode::Open, CvInvalidFormatPolicy::Allow, CvFormatContext::Valid);
        $request = Request::create('/cv/?format=Ab3xY9kLm2Qp');

        self::assertTrue($service->isAccessAllowed($request));
        self::assertFalse($service->requiresAccessGate($request));
    }

    /**
     * @brief Format required mode denies access without valid format.
     *
     * @return void
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function testFormatRequiredModeDeniesWithoutValidFormat(): void
    {
        $service = $this->createService(CvPublicAccessMode::FormatRequired, CvInvalidFormatPolicy::Allow, CvFormatContext::None);
        $request = Request::create('/cv/');

        self::assertFalse($service->isAccessAllowed($request));
        self::assertFalse($service->requiresAccessGate($request));
    }

    /**
     * @brief Format required mode with valid format requires gate.
     *
     * @return void
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function testFormatRequiredModeWithValidFormatRequiresGate(): void
    {
        $service = $this->createService(CvPublicAccessMode::FormatRequired, CvInvalidFormatPolicy::Allow, CvFormatContext::Valid);
        $request = Request::create('/cv/?format=Ab3xY9kLm2Qp');

        self::assertTrue($service->isAccessAllowed($request));
        self::assertTrue($service->requiresAccessGate($request));
    }

    /**
     * @brief Invalid format deny policy blocks access outside format required mode.
     *
     * @return void
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function testInvalidFormatDenyPolicyBlocksAccess(): void
    {
        $service = $this->createService(CvPublicAccessMode::Gated, CvInvalidFormatPolicy::Deny, CvFormatContext::Invalid);
        $request = Request::create('/cv/?format=bad');

        self::assertFalse($service->isAccessAllowed($request));
        self::assertFalse($service->requiresAccessGate($request));
    }

    /**
     * @brief Format required mode denies invalid format even when allow policy is configured.
     *
     * @return void
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function testFormatRequiredModeDeniesInvalidFormat(): void
    {
        $service = $this->createService(CvPublicAccessMode::FormatRequired, CvInvalidFormatPolicy::Allow, CvFormatContext::Invalid);
        $request = Request::create('/cv/?format=bad');

        self::assertFalse($service->isAccessAllowed($request));
    }
}
