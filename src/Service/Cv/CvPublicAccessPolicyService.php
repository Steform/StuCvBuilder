<?php

declare(strict_types=1);

namespace App\Service\Cv;

use App\Cv\CvFormatContext;
use App\Cv\CvInvalidFormatPolicy;
use App\Cv\CvPublicAccessMode;
use App\Service\Home\HomeCustomizationService;
use Symfony\Component\HttpFoundation\Request;

/**
 * @brief Central policy for public CV access denial and gate enforcement.
 */
class CvPublicAccessPolicyService
{
    /**
     * @brief Build CV public access policy service.
     *
     * @param HomeCustomizationService $homeCustomizationService Site configuration reader.
     * @param CvAccessSessionService $cvAccessSessionService Format session helper.
     * @return void
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function __construct(
        private readonly HomeCustomizationService $homeCustomizationService,
        private readonly CvAccessSessionService $cvAccessSessionService,
    ) {
    }

    /**
     * @brief Resolve configured public CV access mode.
     *
     * @return CvPublicAccessMode Active access mode.
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function getPublicAccessMode(): CvPublicAccessMode
    {
        return $this->homeCustomizationService->getOrCreateSingleton()->getCvPublicAccessMode();
    }

    /**
     * @brief Resolve configured invalid recruiter format policy.
     *
     * @return CvInvalidFormatPolicy Active invalid-format policy.
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function getInvalidFormatPolicy(): CvInvalidFormatPolicy
    {
        return $this->homeCustomizationService->getOrCreateSingleton()->getCvInvalidFormatPolicy();
    }

    /**
     * @brief Whether the visitor may view the requested CV route.
     *
     * @param Request $request Incoming HTTP request.
     * @return bool True when CV content may be rendered.
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function isAccessAllowed(Request $request): bool
    {
        $context = $this->cvAccessSessionService->resolveFormatContext($request);
        $accessMode = $this->getPublicAccessMode();
        $invalidFormatPolicy = $this->getInvalidFormatPolicy();

        if ($accessMode === CvPublicAccessMode::FormatRequired && $context !== CvFormatContext::Valid) {
            return false;
        }

        if ($invalidFormatPolicy === CvInvalidFormatPolicy::Deny && $context === CvFormatContext::Invalid) {
            return false;
        }

        return true;
    }

    /**
     * @brief Whether the CV antibot gate must run for the current request.
     *
     * @param Request $request Incoming HTTP request.
     * @return bool True when gate enforcement is required.
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function requiresAccessGate(Request $request): bool
    {
        $accessMode = $this->getPublicAccessMode();
        if ($accessMode === CvPublicAccessMode::Open) {
            return false;
        }

        $context = $this->cvAccessSessionService->resolveFormatContext($request);
        if ($context !== CvFormatContext::Valid) {
            return false;
        }

        return $accessMode === CvPublicAccessMode::Gated
            || $accessMode === CvPublicAccessMode::FormatRequired;
    }
}
