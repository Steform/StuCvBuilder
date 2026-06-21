<?php

declare(strict_types=1);

namespace App\Service\Customization;

use App\Exception\Customization\CustomizationBackupException;

/**
 * @brief Validate manifest format and entry checksums before restore.
 */
final class CustomizationBackupManifestValidator
{
    /**
     * @brief Validate manifest version and checksum map against extracted files.
     *
     * @param array<string, mixed> $manifest Decoded manifest.json.
     * @param array<string, string> $entryContents Map of ZIP entry path to raw bytes.
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function validate(array $manifest, array $entryContents): void
    {
        $formatVersion = (int) ($manifest['formatVersion'] ?? 0);
        if (!in_array($formatVersion, CustomizationBackupPaths::supportedFormatVersions(), true)) {
            throw CustomizationBackupException::withReason('format_version_unsupported', [
                '%expected%' => implode(', ', array_map('strval', CustomizationBackupPaths::supportedFormatVersions())),
                '%found%' => (string) $formatVersion,
            ]);
        }

        $checksums = $manifest['checksums'] ?? null;
        if (!is_array($checksums) || $checksums === []) {
            throw CustomizationBackupException::withReason('checksum_map_invalid');
        }

        foreach ($checksums as $path => $expectedHash) {
            if (!is_string($path) || !is_string($expectedHash)) {
                throw CustomizationBackupException::withReason('checksum_map_invalid');
            }

            if (!array_key_exists($path, $entryContents)) {
                throw CustomizationBackupException::withReason('checksum_missing_entry', [
                    '%path%' => $path,
                ]);
            }

            $actual = hash('sha256', $entryContents[$path]);
            if (!hash_equals($expectedHash, $actual)) {
                throw CustomizationBackupException::withReason('checksum_mismatch', [
                    '%path%' => $path,
                ]);
            }
        }

        $requiredDataPaths = [
            CustomizationBackupPaths::DATA_HOME,
            CustomizationBackupPaths::DATA_HOME_TRANSLATIONS,
            CustomizationBackupPaths::DATA_CV_PROFILE,
            CustomizationBackupPaths::DATA_LOCALE,
        ];

        foreach ($requiredDataPaths as $requiredPath) {
            if (!isset($checksums[$requiredPath])) {
                throw CustomizationBackupException::withReason('required_data_missing', [
                    '%path%' => $requiredPath,
                ]);
            }
        }

        if ($formatVersion >= 2) {
            foreach (CustomizationBackupPaths::employmentDataPaths() as $requiredPath) {
                if (!isset($checksums[$requiredPath])) {
                    throw CustomizationBackupException::withReason('required_data_missing', [
                        '%path%' => $requiredPath,
                    ]);
                }
            }
        }
    }
}
