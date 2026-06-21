<?php

declare(strict_types=1);

namespace App\Service\Cv;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @brief Resolves CV Situation editorial content from persisted JSON with generic placeholder per locale.
 *
 * @date 2026-05-20
 * @author Stephane H.
 */
class CvSituationContentSettingsService
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly CvPublicIdentityPlaceholderService $cvPublicIdentityPlaceholderService,
    ) {
    }

    /**
     * @brief Resolve situation content for admin forms and public CV rendering.
     *
     * @param string $contentJson CvProfile JSON payload.
     * @param list<string> $activeLocales Site active locales.
     * @param string $defaultLocale Site default locale.
     * @param string $displayLocale Viewer or request locale.
     * @return array{
     *     contentByLocale: array<string, array<string, mixed>>,
     *     content: array<string, mixed>,
     *     hasPersistedMap: bool
     * }
     * @date 2026-05-20
     * @author Stephane H.
     */
    public function resolveFromContentJson(
        string $contentJson,
        array $activeLocales,
        string $defaultLocale,
        string $displayLocale
    ): array {
        $payload = $this->decodeJsonPayload($contentJson);
        $hasPersistedMap = SituationContentContract::hasPersistedContentMap($payload);
        $stored = SituationContentContract::contentByLocaleFromStoredPayload($payload);

        $contentByLocale = [];
        foreach ($activeLocales as $locale) {
            $localeContent = $stored[$locale] ?? [];
            if ($localeContent === [] || !$hasPersistedMap) {
                $localeContent = $this->buildPlaceholderContentForLocale($locale);
            } elseif (SituationContentContract::isLocaleContentEmpty($localeContent)) {
                $localeContent = $this->buildPlaceholderContentForLocale($locale);
            }

            $contentByLocale[$locale] = SituationContentContract::attachAdminDslFields($localeContent);
        }

        $displayLocaleKey = $this->resolveDisplayLocaleKey($contentByLocale, $displayLocale, $defaultLocale, $activeLocales);
        $content = $contentByLocale[$displayLocaleKey] ?? SituationContentContract::attachAdminDslFields(
            $this->buildPlaceholderContentForLocale($displayLocale)
        );

        return [
            'contentByLocale' => $contentByLocale,
            'content' => $content,
            'hasPersistedMap' => $hasPersistedMap,
        ];
    }

    /**
     * @brief Build Situation page preview payload per locale for the admin About accordion.
     *
     * @param array<string, array<string, mixed>> $contentByLocale Resolved situation content per locale.
     * @param list<string> $activeLocales Active locale codes.
     * @param array<string, mixed> $profilePayload Decoded CvProfile JSON payload.
     * @param array<string, list<array<string, mixed>>> $experienceEntriesByLocale Experience rows per locale.
     * @param string $defaultLocale Default locale code.
     * @return array<string, array{situationContent: array<string, mixed>, aboutHeaderLocationLine: string, situationLastExperienceEntry: array<string, mixed>|null}>
     * @date 2026-06-08
     * @author Stephane H.
     */
    public function buildAdminPreviewPayloadByLocale(
        array $contentByLocale,
        array $activeLocales,
        array $profilePayload,
        array $experienceEntriesByLocale,
        string $defaultLocale,
    ): array {
        $previewByLocale = [];
        foreach ($activeLocales as $locale) {
            if (!is_string($locale) || $locale === '') {
                continue;
            }

            $previewByLocale[$locale] = [
                'situationContent' => is_array($contentByLocale[$locale] ?? null)
                    ? $contentByLocale[$locale]
                    : [],
                'aboutHeaderLocationLine' => $this->cvPublicIdentityPlaceholderService->resolveAboutHeaderLocationLine(
                    $profilePayload,
                    $locale,
                    $activeLocales,
                    $defaultLocale,
                ),
                'situationLastExperienceEntry' => $this->resolveFirstPrimaryExperienceEntry(
                    $experienceEntriesByLocale[$locale] ?? []
                ),
            ];
        }

        return $previewByLocale;
    }

    /**
     * @brief Pick the first primary experience row for the Situation footnote preview.
     *
     * @param list<array<string, mixed>> $entries Experience entries for one locale.
     * @return array<string, mixed>|null
     * @date 2026-06-08
     * @author Stephane H.
     */
    private function resolveFirstPrimaryExperienceEntry(array $entries): ?array
    {
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            if (($entry['isPrimary'] ?? true) === true) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * @brief Build generic placeholder situation content for one locale when nothing is persisted.
     *
     * @param string $locale Locale code.
     * @return array<string, mixed>
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function buildPlaceholderContentForLocale(string $locale): array
    {
        $content = [
            'statusLabel' => '',
            'introLead' => $this->trans('cv.placeholder.situation', $locale),
            'contractChip' => '',
            'searchWhereChips' => [],
            'searchModeChips' => [],
            'searchFocusChips' => [],
        ];

        return SituationContentContract::attachAdminDslFields($content);
    }

    /**
     * @param string $id Translation id.
     * @param string $locale Locale code.
     * @return string
     */
    private function trans(string $id, string $locale): string
    {
        $value = $this->translator->trans($id, [], 'messages', $locale);

        if (!is_string($value) || $value === '' || $value === $id) {
            return '';
        }

        return $value;
    }

    /**
     * @param string $json JSON payload.
     * @return array<string, mixed>
     */
    private function decodeJsonPayload(string $json): array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, array<string, mixed>> $contentByLocale Map by locale.
     * @param string $displayLocale Preferred locale.
     * @param string $defaultLocale Default locale.
     * @param list<string> $activeLocales Active locales order.
     * @return string
     */
    private function resolveDisplayLocaleKey(
        array $contentByLocale,
        string $displayLocale,
        string $defaultLocale,
        array $activeLocales
    ): string {
        if (($contentByLocale[$displayLocale] ?? []) !== []) {
            return $displayLocale;
        }

        if (($contentByLocale[$defaultLocale] ?? []) !== []) {
            return $defaultLocale;
        }

        foreach ($activeLocales as $loc) {
            if (($contentByLocale[$loc] ?? []) !== []) {
                return $loc;
            }
        }

        return $displayLocale;
    }
}
