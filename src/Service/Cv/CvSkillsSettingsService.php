<?php

declare(strict_types=1);

namespace App\Service\Cv;

use App\Cv\SkillsTreeContract;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @brief Resolves CV skills catalog views for public templates and admin forms.
 *
 * @date 2026-05-29
 * @author Stephane H.
 */
final class CvSkillsSettingsService
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @brief Resolve skills trees for a locale from stored profile JSON.
     *
     * @param string $contentJson Raw CvProfile content JSON.
     * @param list<string> $activeLocales Active locale codes.
     * @param string $defaultLocale Site default locale.
     * @param string $displayLocale Viewer locale.
     * @return array{
     *     catalog: array{categories: list<array<string, mixed>>},
     *     treePrimary: array{categories: list<array<string, mixed>>},
     *     treeFull: array{categories: list<array<string, mixed>>},
     *     hasSecondaryVisible: bool
     * }
     * @date 2026-05-29
     * @author Stephane H.
     */
    public function resolveFromContentJson(
        string $contentJson,
        array $activeLocales,
        string $defaultLocale,
        string $displayLocale,
    ): array {
        $payload = json_decode($contentJson, true);
        $payload = is_array($payload) ? $payload : [];

        return $this->resolveFromPayload($payload, $activeLocales, $defaultLocale, $displayLocale);
    }

    /**
     * @brief Resolve skills trees for a locale from decoded profile payload.
     *
     * @param array<string, mixed> $payload Decoded profile payload.
     * @param list<string> $activeLocales Active locale codes.
     * @param string $defaultLocale Site default locale.
     * @param string $displayLocale Viewer locale.
     * @return array{
     *     catalog: array{categories: list<array<string, mixed>>},
     *     treePrimary: array{categories: list<array<string, mixed>>},
     *     treeFull: array{categories: list<array<string, mixed>>},
     *     hasSecondaryVisible: bool
     * }
     * @date 2026-05-29
     * @author Stephane H.
     */
    public function resolveFromPayload(
        array $payload,
        array $activeLocales,
        string $defaultLocale,
        string $displayLocale,
    ): array {
        $locale = in_array($displayLocale, $activeLocales, true) ? $displayLocale : $defaultLocale;
        $catalog = SkillsTreeContract::resolveCatalogFromPayload(
            $payload,
            $activeLocales,
            $defaultLocale,
            $this->translator
        );

        return [
            'catalog' => $catalog,
            'treePrimary' => SkillsTreeContract::filterForPrimary($catalog, $locale, $defaultLocale),
            'treeFull' => SkillsTreeContract::filterForFull($catalog, $locale, $defaultLocale),
            'hasSecondaryVisible' => SkillsTreeContract::hasSecondaryVisible($catalog),
        ];
    }
}
