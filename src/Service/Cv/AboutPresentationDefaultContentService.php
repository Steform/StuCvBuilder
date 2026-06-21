<?php

declare(strict_types=1);

namespace App\Service\Cv;

use App\Service\RichText\RichHtmlSanitizer;

/**
 * @brief Builds the default About presentation HTML skeleton for empty or placeholder CV states.
 */
final class AboutPresentationDefaultContentService
{
    /** @brief Blank paragraph inserted between default presentation blocks (CKEditor-friendly spacing). */
    private const PARAGRAPH_SPACER = '<p><br></p>';

    public function __construct(
        private readonly RichHtmlSanitizer $richHtmlSanitizer,
    ) {
    }

    /**
     * @brief Return sanitized default presentation HTML (identity tokens and action buttons only).
     *
     * @param string $locale Active locale code (reserved for future localized defaults; tokens are locale-agnostic).
     * @return string Sanitized HTML safe for CKEditor seed and public About rendering.
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function buildSanitizedHtmlForLocale(string $locale): string
    {
        unset($locale);

        $html = '<p>[[cv.display_name]]</p>'
            .self::PARAGRAPH_SPACER
            .'<p>[[cv.sought_position]]</p>'
            .self::PARAGRAPH_SPACER
            .'<p>[[cv.city]], [[cv.region]], [[cv.country]] | [[cv.status]]</p>'
            .self::PARAGRAPH_SPACER
            .'<p>[[cv.pdf]] [[cv.learn_more]]</p>';

        return $this->richHtmlSanitizer->sanitize($html);
    }
}
