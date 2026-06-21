<?php

declare(strict_types=1);

namespace App\Service\Util;

/**
 * @brief Safe JSON decoding with depth limits for untrusted payloads.
 */
final class JsonDecoder
{
    /**
     * @brief Decode JSON string into associative array with strict error handling.
     *
     * @param string $json Raw JSON input.
     * @param int $maxDepth Maximum nesting depth allowed.
     * @return array<string, mixed> Decoded associative array.
     * @date 2026-06-21
     * @author Stephane H.
     */
    public static function decode(string $json, int $maxDepth = 512): array
    {
        $decoded = json_decode($json, true, $maxDepth, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new \JsonException('Expected JSON object');
        }

        return $decoded;
    }
}
