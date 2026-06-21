<?php

namespace App\Service\Security;

use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * CV public access policy: bypass roles, attestation, and KPI eligibility.
 */
class CvBotAccessService
{
    /**
     * @brief Build CV bot access policy service.
     *
     * @param AuthorizationCheckerInterface $authorizationChecker Symfony authorization checker.
     * @param CvBotAttestationService $attestation Session attestation service.
     * @param AntiBotScoringService $antiBotScoringService Threshold configuration holder.
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function __construct(
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly CvBotAttestationService $attestation,
        private readonly AntiBotScoringService $antiBotScoringService,
    ) {
    }

    /**
     * @brief Whether admin or CV consult role may skip bot checks.
     *
     * @param void No input parameter.
     * @return bool True when scoring gate and captcha are bypassed.
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function isBypassAllowed(): bool
    {
        try {
            return $this->authorizationChecker->isGranted('ROLE_ADMIN')
                || $this->authorizationChecker->isGranted('ROLE_CV_CONSULT');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @brief Whether the visitor may view CV content without gate or attestation flow.
     *
     * @param void No input parameter.
     * @return bool True when CV page body may be rendered.
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function canViewCv(): bool
    {
        return $this->isBypassAllowed() || $this->attestation->isValid();
    }

    /**
     * @brief Whether the current visit should increment recruiter KPI counters.
     *
     * @param void No input parameter.
     * @return bool True when visit tracking should record an event.
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function isVisitCountable(): bool
    {
        return $this->isEligibleForCompanyVisit();
    }

    /**
     * @brief Whether visit may count as official company visit (gate passed, not admin bypass).
     *
     * @param void No input parameter.
     * @return bool
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function isEligibleForCompanyVisit(): bool
    {
        if ($this->isBypassAllowed()) {
            return false;
        }

        return $this->attestation->hasValidGateAttestation($this->antiBotScoringService->getThreshold());
    }

    /**
     * @brief Whether current user is admin bypass for tracking (not counted).
     *
     * @param void No input parameter.
     * @return bool
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function isAdminBypassForTracking(): bool
    {
        return $this->isBypassAllowed();
    }

    /**
     * @brief Technical score for UI display (bubble) on CV pages.
     *
     * @param void No input parameter.
     * @return int Score 0-100.
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function getTechnicalScoreForDisplay(): int
    {
        if ($this->isBypassAllowed()) {
            return 100;
        }

        return $this->attestation->getScore();
    }
}
