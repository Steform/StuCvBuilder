<?php

declare(strict_types=1);

namespace App\Service\Customization;

/**
 * @brief Build manifest.json with SHA-256 checksums for backup archive entries.
 */
final class CustomizationBackupManifestBuilder
{
    /**
     * @brief Build manifest structure for archive members.
     *
     * @param array<string, string> $entryContents Map of ZIP entry path to raw bytes.
     * @param string $appVersion Application version string.
     * @param string|null $fileScope Optional scope label (e.g. full_trees).
     * @return array<string, mixed> Manifest payload.
     * @date 2026-05-16
     * @author Stephane H.
     */
    public function build(array $entryContents, string $appVersion, ?string $fileScope = null): array
    {
        $checksums = [];
        foreach ($entryContents as $path => $bytes) {
            $checksums[$path] = hash('sha256', $bytes);
        }

        $manifest = [
            'formatVersion' => CustomizationBackupPaths::FORMAT_VERSION,
            'exportedAt' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(\DateTimeInterface::ATOM),
            'appVersion' => $appVersion,
            'checksums' => $checksums,
        ];

        if ($fileScope !== null && $fileScope !== '') {
            $manifest['fileScope'] = $fileScope;
        }

        return $manifest;
    }
}
