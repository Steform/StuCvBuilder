<?php

declare(strict_types=1);

namespace App\Exception\Customization;

use RuntimeException;
use Throwable;

/**
 * @brief User-facing customization backup failure with i18n key and optional parameters.
 */
final class CustomizationBackupException extends RuntimeException
{
    private const TRANSLATION_PREFIX = 'dashboard.customization_backup.error.';

    /** @var array<string, string> */
    private const REASON_TO_KEY = [
        'key_missing' => 'key_missing',
        'encrypt_failed' => 'encrypt_failed',
        'export_failed' => 'export_failed',
        'export_too_large' => 'export_too_large',
        'invalid_archive' => 'invalid_archive',
        'decrypt_prefix_missing' => 'decrypt_prefix_missing',
        'decrypt_payload_too_short' => 'decrypt_payload_too_short',
        'decrypt_failed' => 'decrypt_failed',
        'upload_invalid' => 'upload_invalid',
        'file_empty' => 'file_empty',
        'file_too_large' => 'file_too_large',
        'manifest_missing' => 'manifest_missing',
        'manifest_invalid_json' => 'manifest_invalid_json',
        'zip_temp_failed' => 'zip_temp_failed',
        'zip_write_failed' => 'zip_write_failed',
        'zip_unreadable' => 'zip_unreadable',
        'zip_entry_read_failed' => 'zip_entry_read_failed',
        'json_entry_missing' => 'json_entry_missing',
        'json_entry_invalid' => 'json_entry_invalid',
        'format_version_unsupported' => 'format_version_unsupported',
        'checksum_map_invalid' => 'checksum_map_invalid',
        'checksum_missing_entry' => 'checksum_missing_entry',
        'checksum_mismatch' => 'checksum_mismatch',
        'required_data_missing' => 'required_data_missing',
        'locale_data_invalid' => 'locale_data_invalid',
        'path_traversal_blocked' => 'path_traversal_blocked',
        'directory_create_failed' => 'directory_create_failed',
        'file_write_failed' => 'file_write_failed',
        'db_restore_failed' => 'db_restore_failed',
        'db_unique_violation' => 'db_unique_violation',
        'db_foreign_key_violation' => 'db_foreign_key_violation',
        'db_not_null_violation' => 'db_not_null_violation',
        'db_connection_failed' => 'db_connection_failed',
        'db_driver_error' => 'db_driver_error',
        'db_schema_mismatch' => 'db_schema_mismatch',
        'cv_content_json_invalid' => 'cv_content_json_invalid',
        'restore_failed' => 'restore_failed',
        'reset_failed' => 'reset_failed',
        'reset_partial' => 'reset_partial',
        'snapshot_invalid_name' => 'snapshot_invalid_name',
        'snapshot_not_found' => 'snapshot_not_found',
    ];

    /**
     * @param string $reasonCode Internal failure identifier for logs.
     * @param string $translationKey Full Symfony translation key.
     * @param array<string, string|int> $translationParameters Placeholder map for trans filter.
     * @param Throwable|null $previous Wrapped cause for server logs.
     */
    public function __construct(
        private readonly string $reasonCode,
        private readonly string $translationKey,
        private readonly array $translationParameters = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($translationKey, 0, $previous);
    }

    /**
     * @brief Build exception from a known reason code and optional translation parameters.
     *
     * @param string $reasonCode Internal failure identifier listed in REASON_TO_KEY.
     * @param array<string, string|int> $parameters Placeholder map for trans filter.
     * @param Throwable|null $previous Wrapped cause for server logs.
     * @return self
     * @date 2026-05-19
     * @author Stephane H.
     */
    public static function withReason(string $reasonCode, array $parameters = [], ?Throwable $previous = null): self
    {
        $suffix = self::REASON_TO_KEY[$reasonCode] ?? 'invalid_archive';
        $resolvedReason = array_key_exists($reasonCode, self::REASON_TO_KEY) ? $reasonCode : 'invalid_archive';

        return new self(
            $resolvedReason,
            self::TRANSLATION_PREFIX.$suffix,
            self::normalizeParameters($parameters),
            $previous,
        );
    }

    /**
     * @brief Format byte size for user-facing flash messages.
     *
     * @param int $bytes Size in bytes.
     * @return string Human-readable size label.
     * @date 2026-05-19
     * @author Stephane H.
     */
    public static function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / (1024 * 1024), 1).' MB';
    }

    /**
     * @brief Internal failure identifier for logs.
     *
     * @return string
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function getReasonCode(): string
    {
        return $this->reasonCode;
    }

    /**
     * @brief Full Symfony translation key for flash rendering.
     *
     * @return string
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function getTranslationKey(): string
    {
        return $this->translationKey;
    }

    /**
     * @brief Placeholder map passed to the translator.
     *
     * @return array<string, string|int>
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function getTranslationParameters(): array
    {
        return $this->translationParameters;
    }

    /**
     * @brief Coerce parameter values to translator-compatible scalars.
     *
     * @param array<string, string|int> $parameters Raw placeholder map.
     * @return array<string, string|int>
     * @date 2026-05-19
     * @author Stephane H.
     */
    private static function normalizeParameters(array $parameters): array
    {
        $normalized = [];
        foreach ($parameters as $key => $value) {
            $normalized[$key] = $value;
        }

        return $normalized;
    }
}
