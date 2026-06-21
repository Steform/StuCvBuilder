<?php

declare(strict_types=1);

namespace App\Service\Customization;

/**
 * @brief Resolved CV customization admin UI state (main tab + optional panel and locale).
 */
final readonly class CvUiState
{
    public function __construct(
        public string $tab,
        public ?string $panel,
        public ?string $locale,
        public ?string $entry = null,
    ) {
    }
}
