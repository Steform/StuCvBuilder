<?php

declare(strict_types=1);

namespace App\Service\Customization;

/**
 * @brief Resolved home customization admin UI state (accordion panel + optional locale tab).
 */
final readonly class HomeUiState
{
    public function __construct(
        public string $panel,
        public string $locale,
    ) {
    }
}
