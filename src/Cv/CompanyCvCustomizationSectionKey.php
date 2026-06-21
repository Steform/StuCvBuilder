<?php

declare(strict_types=1);

namespace App\Cv;

/**
 * Stable keys for per-company CV web section customization (aligned with global admin CV tabs).
 */
final class CompanyCvCustomizationSectionKey
{
    public const CV_DATA = 'cv_data';

    public const ABOUT = 'about';

    public const SITUATION = 'situation';

    public const SKILLS = 'skills';

    public const FLAGSHIP_PROJECTS = 'flagship_projects';

    public const EXPERIENCE = 'experience';

    public const EDUCATION = 'education';

    public const CERTIFICATION = 'certification';

    public const LANGUAGES = 'languages';

    public const INTERESTS = 'interests';

    public const WEB_PROFILES = 'web_profiles';

    public const REFERENCES = 'references';

    /**
     * @brief Return section keys in public CV / admin tab order.
     *
     * @param void No input parameter.
     * @return list<string>
     * @date 2026-06-01
     * @author Stephane H.
     */
    public static function orderedKeys(): array
    {
        return [
            self::CV_DATA,
            self::ABOUT,
            self::SITUATION,
            self::SKILLS,
            self::FLAGSHIP_PROJECTS,
            self::EXPERIENCE,
            self::EDUCATION,
            self::CERTIFICATION,
            self::LANGUAGES,
            self::INTERESTS,
            self::WEB_PROFILES,
            self::REFERENCES,
        ];
    }

    /**
     * @brief Translation key for the global admin tab label of a section.
     *
     * @param string $key Section key from {@see orderedKeys()}.
     * @return string Symfony messages key.
     * @date 2026-06-01
     * @author Stephane H.
     */
    public static function adminTabTranslationKey(string $key): string
    {
        return match ($key) {
            self::CV_DATA => 'dashboard.customization_cv.tab.cv_data',
            self::ABOUT => 'dashboard.customization_cv.tab.about',
            self::SITUATION => 'dashboard.customization_cv.tab.situation',
            self::SKILLS => 'dashboard.customization_cv.tab.skills',
            self::FLAGSHIP_PROJECTS => 'dashboard.customization_cv.tab.flagship_projects',
            self::EXPERIENCE => 'dashboard.customization_cv.tab.experience',
            self::EDUCATION => 'dashboard.customization_cv.tab.education',
            self::CERTIFICATION => 'dashboard.customization_cv.tab.certification',
            self::LANGUAGES => 'dashboard.customization_cv.tab.languages',
            self::INTERESTS => 'dashboard.customization_cv.tab.interests',
            self::WEB_PROFILES => 'dashboard.customization_cv.tab.web_profiles',
            self::REFERENCES => 'dashboard.customization_cv.tab.references',
            default => 'dashboard.customization_cv.title',
        };
    }

    /**
     * @brief Check whether a section key is supported.
     *
     * @param string $key Candidate section key.
     * @return bool True when the key is listed in {@see orderedKeys()}.
     * @date 2026-06-01
     * @author Stephane H.
     */
    public static function isValid(string $key): bool
    {
        return in_array($key, self::orderedKeys(), true);
    }

    /**
     * @brief Default section when query param is missing or invalid.
     *
     * @param void No input parameter.
     * @return string First section key.
     * @date 2026-06-01
     * @author Stephane H.
     */
    public static function defaultKey(): string
    {
        return self::CV_DATA;
    }
}
