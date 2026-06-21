<?php

declare(strict_types=1);

namespace App\Service\Employment;

/**
 * Validates and normalizes 12-character company format codes.
 */
class CompanyCodeNormalizer
{
    public const CODE_LENGTH = 12;

    private const PATTERN = '/^[A-Za-z0-9]{12}$/';

    /**
     * @brief Normalize raw format input to a valid code or empty string.
     *
     * @param string $raw Raw query or session value.
     * @return string Valid code or empty when invalid.
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function normalize(string $raw): string
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return '';
        }

        if (strlen($trimmed) !== self::CODE_LENGTH) {
            return '';
        }

        if (!preg_match(self::PATTERN, $trimmed)) {
            return '';
        }

        return $trimmed;
    }

    /**
     * @brief Check whether raw input is syntactically valid before DB lookup.
     *
     * @param string $raw Raw format value.
     * @return bool
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function isSyntacticallyValid(string $raw): bool
    {
        return $this->normalize($raw) !== '';
    }
}
