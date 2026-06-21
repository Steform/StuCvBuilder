<?php

declare(strict_types=1);

namespace App\Service\Cv;

use Twig\Environment;

/**
 * @brief Renders the CV skills admin tree Twig partial for AJAX catalog updates.
 *
 * @date 2026-06-11
 * @author Stephane H.
 */
final class CvSkillsAdminTreeRenderer
{
    public function __construct(
        private readonly Environment $twig,
    ) {
    }

    /**
     * @brief Render the admin skills tree HTML from a normalized catalog.
     *
     * @param array{categories: list<array<string, mixed>>} $catalog Normalized skills catalog.
     * @param list<string> $activeLocales Active locale codes.
     * @param string $defaultLocale Site default locale.
     * @param string $currentLocale Admin UI locale.
     * @return string Rendered HTML fragment.
     * @date 2026-06-11
     * @author Stephane H.
     */
    public function render(
        array $catalog,
        array $activeLocales,
        string $defaultLocale,
        string $currentLocale,
    ): string {
        return $this->twig->render('components/cv/admin/_skills_admin_tree.html.twig', [
            'skillsCatalog' => $catalog,
            'activeLocales' => $activeLocales,
            'defaultLocale' => $defaultLocale,
            'currentLocale' => $currentLocale,
        ]);
    }
}
