<?php

declare(strict_types=1);

namespace App\Service\Cv;

use App\Cv\SkillsTreeContract;

/**
 * @brief Resolve which CV public sidebar links should render for persisted section content.
 */
final class CvPublicNavVisibilityService
{
    /**
     * @brief Build nav visibility flags from stored and resolved CV payload slices.
     *
     * @param array<string, mixed> $storedPayload Raw persisted CvProfile JSON before runtime merges.
     * @param array<string, mixed> $resolvedPayload Fully resolved public CV payload.
     * @return array{
     *     about: bool,
     *     skills: bool,
     *     projects: bool,
     *     experience: bool,
     *     education: bool,
     *     certification: bool,
     *     languages: bool,
     *     interests: bool,
     *     web_profiles: bool,
     *     references: bool,
     *     contact: bool
     * }
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function resolve(array $storedPayload, array $resolvedPayload): array
    {
        return [
            'about' => true,
            'skills' => self::hasVisibleSkillsSection($storedPayload, $resolvedPayload),
            'projects' => self::hasVisibleProjectsSection($storedPayload, $resolvedPayload),
            'experience' => self::hasVisibleExperienceSection($storedPayload),
            'education' => self::hasVisibleEducationSection($storedPayload),
            'certification' => self::hasVisibleCertificationSection($storedPayload),
            'languages' => self::hasVisibleLanguagesSection($storedPayload, $resolvedPayload),
            'interests' => self::hasVisibleInterestsSection($storedPayload, $resolvedPayload),
            'web_profiles' => self::hasVisibleWebProfilesSection($storedPayload, $resolvedPayload),
            'references' => self::hasVisibleReferencesSection($storedPayload, $resolvedPayload),
            'contact' => true,
        ];
    }

    /**
     * @param array<string, mixed> $storedPayload Stored CvProfile JSON.
     * @param array<string, mixed> $resolvedPayload Resolved public payload.
     */
    private static function hasVisibleSkillsSection(array $storedPayload, array $resolvedPayload): bool
    {
        if (!array_key_exists(SkillsTreeContract::KEY, $storedPayload)) {
            return false;
        }

        $categories = $resolvedPayload['skillsTreePrimary']['categories'] ?? null;
        if (is_array($categories) && $categories !== []) {
            return true;
        }

        $catalog = $storedPayload[SkillsTreeContract::KEY] ?? null;

        return is_array($catalog)
            && is_array($catalog['categories'] ?? null)
            && $catalog['categories'] !== [];
    }

    /**
     * @param array<string, mixed> $storedPayload Stored CvProfile JSON.
     * @param array<string, mixed> $resolvedPayload Resolved public payload.
     */
    private static function hasVisibleProjectsSection(array $storedPayload, array $resolvedPayload): bool
    {
        if (!FlagshipProjectsContract::isSectionEnabledFromPayload($resolvedPayload)) {
            return false;
        }

        if (!FlagshipProjectsContract::hasPersistedProjectsMap($storedPayload)) {
            return false;
        }

        $projects = $resolvedPayload['flagshipProjects'] ?? [];
        if (is_array($projects) && $projects !== []) {
            return true;
        }

        foreach (FlagshipProjectsContract::entriesByLocaleFromStoredPayload($storedPayload) as $rows) {
            if ($rows !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $storedPayload Stored CvProfile JSON.
     */
    private static function hasVisibleExperienceSection(array $storedPayload): bool
    {
        if (!ExperienceContract::hasPersistedExperienceMap($storedPayload)) {
            return false;
        }

        foreach (ExperienceContract::entriesByLocaleFromStoredPayload($storedPayload) as $rows) {
            if ($rows !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $storedPayload Stored CvProfile JSON.
     */
    private static function hasVisibleEducationSection(array $storedPayload): bool
    {
        if (!EducationContract::hasPersistedEducationMap($storedPayload)) {
            return false;
        }

        foreach (EducationContract::entriesByLocaleFromStoredPayload($storedPayload) as $rows) {
            if ($rows !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $storedPayload Stored CvProfile JSON.
     */
    private static function hasVisibleCertificationSection(array $storedPayload): bool
    {
        if (!CertificationContract::hasPersistedEntries($storedPayload)) {
            return false;
        }

        return CertificationContract::entriesFromStoredPayload($storedPayload) !== [];
    }

    /**
     * @param array<string, mixed> $storedPayload Stored CvProfile JSON.
     * @param array<string, mixed> $resolvedPayload Resolved public payload.
     */
    private static function hasVisibleLanguagesSection(array $storedPayload, array $resolvedPayload): bool
    {
        if (!LanguagesContract::hasPersistedEntries($storedPayload)) {
            return false;
        }

        $entries = $resolvedPayload['languageEntries'] ?? [];

        return is_array($entries) && $entries !== [];
    }

    /**
     * @param array<string, mixed> $storedPayload Stored CvProfile JSON.
     * @param array<string, mixed> $resolvedPayload Resolved public payload.
     */
    private static function hasVisibleInterestsSection(array $storedPayload, array $resolvedPayload): bool
    {
        if (!InterestsContract::hasPersistedEntries($storedPayload)) {
            return false;
        }

        $entries = $resolvedPayload['interestEntries'] ?? [];

        return is_array($entries) && $entries !== [];
    }

    /**
     * @param array<string, mixed> $storedPayload Stored CvProfile JSON.
     * @param array<string, mixed> $resolvedPayload Resolved public payload.
     */
    private static function hasVisibleWebProfilesSection(array $storedPayload, array $resolvedPayload): bool
    {
        if (!WebProfilesContract::hasPersistedEntries($storedPayload)) {
            return false;
        }

        $entries = $resolvedPayload['webProfileEntries'] ?? [];

        return is_array($entries) && $entries !== [];
    }

    /**
     * @param array<string, mixed> $storedPayload Stored CvProfile JSON.
     * @param array<string, mixed> $resolvedPayload Resolved public payload.
     */
    private static function hasVisibleReferencesSection(array $storedPayload, array $resolvedPayload): bool
    {
        if (!ReferencesContract::hasPersistedMap($storedPayload)) {
            return false;
        }

        if (!(ReferencesContract::isSectionEnabledFromPayload($resolvedPayload))) {
            return false;
        }

        $entries = $resolvedPayload['referenceEntries'] ?? [];

        return is_array($entries) && $entries !== [];
    }
}
