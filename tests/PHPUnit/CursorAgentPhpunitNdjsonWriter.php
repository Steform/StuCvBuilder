<?php

declare(strict_types=1);

namespace App\Tests\PHPUnit;

/**
 * @brief Append one NDJSON debug line to the Cursor agent log file (session-scoped).
 *
 * @date 2026-05-02
 * @author Stephane H.
 */
final class CursorAgentPhpunitNdjsonWriter
{
    /**
     * @brief Append a single JSON object line to the log path.
     *
     * @param string $logPath Absolute filesystem path.
     * @param array<string, mixed> $payload Serializable payload (no secrets).
     *
     * @return void
     *
     * @date 2026-05-02
     * @author Stephane H.
     */
    public static function append(string $logPath, array $payload): void
    {
        $line = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($line === false) {
            return;
        }

        @file_put_contents($logPath, $line."\n", FILE_APPEND | LOCK_EX);
    }

    /**
     * @brief Overwrite the log file with a single JSON line (run boundary).
     *
     * @param string $logPath Absolute filesystem path.
     * @param array<string, mixed> $payload Serializable payload.
     *
     * @return void
     *
     * @date 2026-05-02
     * @author Stephane H.
     */
    public static function overwrite(string $logPath, array $payload): void
    {
        $line = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($line === false) {
            return;
        }

        @file_put_contents($logPath, $line."\n", LOCK_EX);
    }
}
