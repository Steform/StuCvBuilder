<?php

declare(strict_types=1);

namespace App\Service\Home;

use App\Entity\HomeQuickTile;

/**
 * @brief Normalize and resolve localized quick tile labels.
 */
final class HomeQuickTileLabelFormatter
{
    /**
     * @brief Trim and capitalize the first Unicode letter for storage or display.
     * @param string $rawLabel Raw user input.
     * @return string Normalized label or empty string when input is blank.
     * @date 2026-05-18
     * @author Stephane H.
     */
    public function formatForStorage(string $rawLabel): string
    {
        $trimmed = trim($rawLabel);
        if ($trimmed === '') {
            return '';
        }

        return $this->capitalizeFirstLetterUtf8($trimmed);
    }

    /**
     * @brief Resolve visible label: request locale, then default, then any translation.
     * @param HomeQuickTile $tile Source tile entity.
     * @param string $locale Active request locale code.
     * @param string $defaultLocale Site default locale code.
     * @return string Display label or empty string when no translation exists.
     * @date 2026-05-18
     * @author Stephane H.
     */
    public function resolveForDisplay(HomeQuickTile $tile, string $locale, string $defaultLocale): string
    {
        $candidates = [
            $tile->getLabelForLocale($locale),
            $tile->getLabelForLocale($defaultLocale),
        ];

        foreach ($tile->getTranslations() as $translation) {
            $candidates[] = $translation->getLabel();
        }

        foreach ($candidates as $candidate) {
            $formatted = $this->formatForStorage($candidate);
            if ($formatted !== '') {
                return mb_substr($formatted, 0, 128);
            }
        }

        return '';
    }

    /**
     * @brief Uppercase the first Unicode letter, preserving leading whitespace.
     * @param string $text Raw text segment.
     * @return string Text with first letter uppercased when present.
     * @date 2026-05-18
     * @author Stephane H.
     */
    private function capitalizeFirstLetterUtf8(string $text): string
    {
        if (preg_match('/^(\s*)(\X)(.*)$/u', $text, $matches) !== 1) {
            return $text;
        }

        return $matches[1].mb_strtoupper($matches[2], 'UTF-8').$matches[3];
    }
}
