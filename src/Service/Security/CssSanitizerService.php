<?php

namespace App\Service\Security;

/**
 * Strict CSS declaration sanitizer for admin-supplied style fragments.
 */
class CssSanitizerService
{
    /**
     * @brief Allowed CSS property names (lowercase keys include vendor variants explicitly listed).
     * @var array<string, true>
     */
    private const ALLOWED_PROPERTIES = [
        'align-items' => true,
        'backdrop-filter' => true,
        '-webkit-backdrop-filter' => true,
        'background' => true,
        'background-color' => true,
        'background-image' => true,
        'background-position' => true,
        'background-repeat' => true,
        'background-size' => true,
        'border' => true,
        'border-radius' => true,
        'border-bottom' => true,
        'border-top' => true,
        'border-left' => true,
        'border-right' => true,
        'border-color' => true,
        'border-width' => true,
        'border-style' => true,
        'bottom' => true,
        'box-shadow' => true,
        'color' => true,
        'display' => true,
        'filter' => true,
        'flex' => true,
        'flex-direction' => true,
        'flex-grow' => true,
        'flex-shrink' => true,
        'font' => true,
        'font-family' => true,
        'font-size' => true,
        'font-weight' => true,
        'gap' => true,
        'height' => true,
        'justify-content' => true,
        'letter-spacing' => true,
        'line-height' => true,
        'margin' => true,
        'margin-bottom' => true,
        'margin-left' => true,
        'margin-right' => true,
        'margin-top' => true,
        'max-height' => true,
        'max-width' => true,
        'min-height' => true,
        'min-width' => true,
        'opacity' => true,
        'outline' => true,
        'outline-offset' => true,
        'overflow' => true,
        'padding' => true,
        'padding-bottom' => true,
        'padding-left' => true,
        'padding-right' => true,
        'padding-top' => true,
        'position' => true,
        'right' => true,
        'left' => true,
        'top' => true,
        'text-align' => true,
        'text-decoration' => true,
        'text-shadow' => true,
        'text-transform' => true,
        'transform' => true,
        'transition' => true,
        'vertical-align' => true,
        'white-space' => true,
        'width' => true,
        'word-break' => true,
        'z-index' => true,
    ];

    /**
     * @brief Maximum sanitized payload length per fragment.
     */
    private const MAX_FRAGMENT_BYTES = 65536;

    /**
     * @brief Sanitize a declaration-only CSS fragment (no selectors).
     * @param string|null $rawCss Raw CSS declarations pasted by an administrator.
     * @return string Sanitized declaration block safe for wrapping inside fixed selectors (each line ends with {@code ;}).
     * @date 2026-05-16
     * @author Stephane H.
     */
    public function sanitizeDeclarationBlock(?string $rawCss): string
    {
        if ($rawCss === null || trim($rawCss) === '') {
            return '';
        }

        if (strlen($rawCss) > self::MAX_FRAGMENT_BYTES) {
            return '';
        }

        $strippedComments = (string) preg_replace('#/\*[\s\S]*?\*/#', '', $rawCss);
        $normalized = str_replace(["\r\n", "\r"], "\n", $strippedComments);
        $chunks = preg_split('/;/', $normalized);
        if ($chunks === false) {
            return '';
        }

        $out = [];
        foreach ($chunks as $chunk) {
            $line = trim($chunk);
            if ($line === '') {
                continue;
            }

            if (false === str_contains($line, ':')) {
                continue;
            }

            $property = trim((string) strstr($line, ':', true));
            $value = trim((string) substr($line, strlen($property) + 1));
            if ($property === '' || $value === '') {
                continue;
            }

            $propertyKey = strtolower($property);
            if (!isset(self::ALLOWED_PROPERTIES[$propertyKey])) {
                continue;
            }

            if ($this->isDangerousValue($value)) {
                continue;
            }

            $out[] = $propertyKey.': '.$value.';';
        }

        return implode("\n", $out);
    }

    /**
     * @brief Detect unsafe CSS value payloads.
     * @param string $value Candidate declaration value.
     * @return bool True when the value must be rejected.
     * @date 2026-05-08
     * @author Stephane H.
     */
    private function isDangerousValue(string $value): bool
    {
        $lower = strtolower($value);
        if (str_contains($lower, 'expression(')) {
            return true;
        }
        if (str_contains($lower, 'javascript:')) {
            return true;
        }
        if (str_contains($lower, '@import')) {
            return true;
        }
        if (preg_match('/\bbehavior\b/', $lower) === 1) {
            return true;
        }
        if (preg_match('/url\s*\(\s*[\'"]?\s*javascript:/', $lower) === 1) {
            return true;
        }
        if (preg_match('/url\s*\(\s*[\'"]?\s*vbscript:/', $lower) === 1) {
            return true;
        }
        if (preg_match('/url\s*\(\s*[\'"]?[^\'"\)]*data\s*:/', $lower) === 1) {
            return true;
        }

        return false;
    }
}
