<?php

declare(strict_types=1);

namespace App\Exception\Employment;

use RuntimeException;

/**
 * Raised when employment PDF QR stamping fails.
 */
final class EmploymentDocumentPdfStampException extends RuntimeException
{
    /**
     * @brief Build stamp exception with translation key.
     *
     * @param string $translationKey Symfony messages translation key.
     * @param \Throwable|null $previous Underlying error.
     * @return void
     * @date 2026-06-12
     * @author Stephane H.
     */
    public function __construct(
        private readonly string $translationKey,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($translationKey, 0, $previous);
    }

    /**
     * @brief Return user-facing translation key.
     *
     * @return string
     * @date 2026-06-12
     * @author Stephane H.
     */
    public function getTranslationKey(): string
    {
        return $this->translationKey;
    }
}
