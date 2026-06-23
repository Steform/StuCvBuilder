<?php

declare(strict_types=1);

namespace App\Service\Customization;

use App\Cv\SkillsTreeContract;
use App\Repository\CvProfileRepository;
use App\Service\Cv\AboutPresentationContract;
use App\Service\Cv\CertificationContract;
use App\Service\Cv\EducationContract;
use App\Service\Cv\ExperienceContract;
use App\Service\Cv\FlagshipProjectsContract;
use App\Service\Cv\InterestsContract;
use App\Service\Cv\LanguagesContract;
use App\Service\Cv\ReferencesContract;
use App\Service\Cv\SituationContentContract;
use App\Service\Cv\WebProfilesContract;

/**
 * @brief Detect whether the public CV and admin UI should render placeholder content.
 *
 * @date 2026-05-17
 * @author Stephane H.
 */
final class CustomizationPlaceholderStateService
{
    public function __construct(
        private readonly CvProfileRepository $cvProfileRepository,
    ) {
    }

    /**
     * @brief Return true when no CV profile row exists (post-reset empty state).
     *
     * @return bool
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function isActive(): bool
    {
        return $this->cvProfileRepository->count([]) === 0;
    }

    /**
     * @brief Return true when the CV should behave as a virgin template (no profile row or no saved section).
     *
     * @param array<string, mixed> $payload Decoded CvProfile JSON payload.
     * @return bool
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function shouldUsePlaceholderMode(array $payload): bool
    {
        if ($this->isActive()) {
            return true;
        }

        return !self::hasAnyPersistedCvSection($payload);
    }

    /**
     * @brief Whether decoded CvProfile JSON contains at least one explicitly saved CV section.
     *
     * @param array<string, mixed> $payload Decoded CvProfile JSON payload.
     * @return bool
     * @date 2026-06-21
     * @author Stephane H.
     */
    public static function hasAnyPersistedCvSection(array $payload): bool
    {
        if (AboutPresentationContract::hasPersistedPresentation($payload)) {
            return true;
        }

        if (ExperienceContract::hasPersistedExperienceMap($payload)) {
            return true;
        }

        if (EducationContract::hasPersistedEducationMap($payload)) {
            return true;
        }

        if (CertificationContract::hasPersistedEntries($payload)) {
            return true;
        }

        if (SituationContentContract::hasPersistedContentMap($payload)) {
            return true;
        }

        if (FlagshipProjectsContract::hasPersistedProjectsMap($payload)) {
            return true;
        }

        if (array_key_exists(SkillsTreeContract::KEY, $payload)) {
            return true;
        }

        if (LanguagesContract::hasPersistedEntries($payload)) {
            return true;
        }

        if (InterestsContract::hasPersistedEntries($payload)) {
            return true;
        }

        if (WebProfilesContract::hasPersistedEntries($payload)) {
            return true;
        }

        return ReferencesContract::hasPersistedMap($payload);
    }
}
