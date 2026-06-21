<?php

declare(strict_types=1);

namespace App\Cv;

/**
 * @brief Contract for `sectionTransitions` map keys in CV profile JSON.
 *
 * @date 2026-05-20
 * @author Stephane H.
 */
final class SectionTransitionContract
{
    public const KEY = 'sectionTransitions';

    /** @var list<string> */
    public const ELIGIBLE_SECTION_KEYS = [
        'situation',
        'experience',
        'skills',
        'languages',
        'education',
        'certification',
        'interests',
        'web_profiles',
        'references',
        'profile',
        'contact',
    ];

    /**
     * @brief Default transition applied when a section key is missing or invalid.
     */
    public const DEFAULT = SectionTransition::FadeVertical;

    /**
     * @brief Normalize a raw stored map to eligible keys with validated transition slugs.
     *
     * @param mixed $raw Raw `sectionTransitions` value from content JSON.
     * @return array<string, string> Map section slug => transition slug.
     * @date 2026-05-20
     * @author Stephane H.
     */
    public static function normalizeMap(mixed $raw): array
    {
        $stored = is_array($raw) ? $raw : [];
        $normalized = [];
        foreach (self::ELIGIBLE_SECTION_KEYS as $sectionKey) {
            $normalized[$sectionKey] = SectionTransition::fromStored($stored[$sectionKey] ?? null)->value;
        }

        return $normalized;
    }

    /**
     * @brief Merge submitted admin `section_transitions` into a profile payload and normalize.
     *
     * @param array<string, mixed> $payload Profile content JSON array.
     * @param mixed $submitted Request `section_transitions` array or null.
     * @return array<string, mixed> Payload with normalized `sectionTransitions` key.
     * @date 2026-05-20
     * @author Stephane H.
     */
    public static function mergeSubmittedIntoPayload(array $payload, mixed $submitted): array
    {
        $existing = is_array($payload[self::KEY] ?? null) ? $payload[self::KEY] : [];
        if (!is_array($existing)) {
            $existing = [];
        }

        if (is_array($submitted)) {
            foreach (self::ELIGIBLE_SECTION_KEYS as $sectionKey) {
                if (array_key_exists($sectionKey, $submitted)) {
                    $existing[$sectionKey] = SectionTransition::fromStored($submitted[$sectionKey])->value;
                }
            }
        }

        $payload[self::KEY] = self::normalizeMap($existing);

        return $payload;
    }
}
