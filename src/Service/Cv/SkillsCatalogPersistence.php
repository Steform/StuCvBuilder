<?php

declare(strict_types=1);

namespace App\Service\Cv;

/**
 * @brief Load and persist the CV skills catalog JSON slice (`skillsCatalog`).
 */
interface SkillsCatalogPersistence
{
    /**
     * @brief Load payload slice containing the skills catalog key when present.
     *
     * @return array<string, mixed>
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function loadPayloadSlice(): array;

    /**
     * @brief Persist a normalized skills catalog.
     *
     * @param array{categories: list<array<string, mixed>>} $catalog Normalized catalog.
     * @param list<string> $activeLocales Active locale codes.
     * @param string $defaultLocale Site default locale.
     * @return array{categories: list<array<string, mixed>>} Stored catalog.
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function saveCatalog(array $catalog, array $activeLocales, string $defaultLocale): array;
}
