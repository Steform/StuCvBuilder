<?php

declare(strict_types=1);

namespace App\Service\Security;

/**
 * @brief Validate and sanitize uploaded SVG fragments before public storage.
 */
final class SvgUploadSanitizer
{
    /**
     * @brief Reject dangerous SVG payloads and strip event handler attributes.
     *
     * @param string $svgContent Raw SVG upload content.
     * @return string Sanitized SVG content safe for static serving.
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function sanitize(string $svgContent): string
    {
        $trimmed = trim($svgContent);
        if ($trimmed === '') {
            throw new \InvalidArgumentException('Invalid SVG content');
        }

        if (preg_match('/<script|onload=|onerror=|javascript:|foreignObject|<iframe/i', $trimmed)) {
            throw new \InvalidArgumentException('Invalid SVG content');
        }

        $previous = libxml_use_internal_errors(true);
        try {
            $dom = new \DOMDocument('1.0', 'UTF-8');
            if (@$dom->loadXML($trimmed, LIBXML_NONET | LIBXML_NOENT) === false) {
                throw new \InvalidArgumentException('Invalid SVG content');
            }

            $xpath = new \DOMXPath($dom);
            foreach (['script', 'foreignObject', 'iframe'] as $tag) {
                $nodes = $xpath->query('//'.$tag);
                if ($nodes !== false) {
                    foreach ($nodes as $node) {
                        $node->parentNode?->removeChild($node);
                    }
                }
            }

            foreach ($xpath->query('//@*') ?: [] as $attribute) {
                if (!$attribute instanceof \DOMAttr) {
                    continue;
                }
                if (str_starts_with(strtolower($attribute->name), 'on')) {
                    $attribute->ownerElement?->removeAttributeNode($attribute);
                }
            }

            $sanitized = $dom->saveXML($dom->documentElement);
            if (!is_string($sanitized) || trim($sanitized) === '') {
                throw new \InvalidArgumentException('Invalid SVG content');
            }

            return $sanitized;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
