<?php

declare(strict_types=1);

namespace App\Service\Cv;

/**
 * @brief Loads and serves the public CV pencil decoration SVG markup.
 *
 * @date 2026-06-08
 * @author Stephane H.
 */
final class CvPencilDecorationService
{
    private const SVG_RELATIVE_PATH = '/public/images/cv/decor/crayon.svg';

    /**
     * @brief Build service with project root path.
     *
     * @param string $projectDir Symfony project directory.
     * @return void
     * @date 2026-06-08
     * @author Stephane H.
     */
    public function __construct(
        private readonly string $projectDir,
    ) {
    }

    /**
     * @brief Return inline SVG markup for the pencil decoration.
     *
     * @param void No input parameter.
     * @return string Sanitized inline SVG markup or empty string when missing.
     * @date 2026-06-08
     * @author Stephane H.
     */
    public function renderSvgMarkup(): string
    {
        $absolutePath = rtrim($this->projectDir, '/').self::SVG_RELATIVE_PATH;
        if (!is_readable($absolutePath)) {
            return '';
        }

        $svg = file_get_contents($absolutePath);
        if (!is_string($svg) || trim($svg) === '') {
            return '';
        }

        return trim($svg);
    }
}
