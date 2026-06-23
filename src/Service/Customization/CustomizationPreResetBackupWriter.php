<?php

declare(strict_types=1);

namespace App\Service\Customization;

use App\Exception\Customization\CustomizationBackupException;

/**
 * @brief Persist encrypted pre-reset snapshots on the application server.
 */
final class CustomizationPreResetBackupWriter
{
    private const SNAPSHOT_SUBDIR = 'customization-reset-snapshots';

    public function __construct(
        private readonly CustomizationBackupExportService $exportService,
        private readonly string $projectDir,
    ) {
    }

    /**
     * @brief Write an encrypted backup of the current customization before a reset wipe.
     *
     * @return string Basename of the written snapshot file.
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function writePreResetSnapshot(): string
    {
        $result = $this->exportService->export();
        $directory = $this->getSnapshotDirectory();
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw CustomizationBackupException::withReason('reset_failed');
        }

        $filename = sprintf(
            'pre-reset-%s-%s.cvbackup',
            (new \DateTimeImmutable())->format('Ymd-His'),
            bin2hex(random_bytes(4))
        );
        $path = $directory.'/'.$filename;

        if (file_put_contents($path, $result['content']) === false) {
            throw CustomizationBackupException::withReason('reset_failed');
        }

        return $filename;
    }

    /**
     * @brief List pre-reset snapshot files sorted by modification time (newest first).
     *
     * @return list<array{basename: string, modifiedAt: int}>
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function listSnapshots(): array
    {
        $directory = $this->getSnapshotDirectory();
        if (!is_dir($directory)) {
            return [];
        }

        $items = [];
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || !str_ends_with($entry, '.cvbackup')) {
                continue;
            }

            $path = $directory.'/'.$entry;
            if (!is_file($path)) {
                continue;
            }

            $items[] = [
                'basename' => $entry,
                'modifiedAt' => (int) filemtime($path),
            ];
        }

        usort($items, static fn (array $a, array $b): int => $b['modifiedAt'] <=> $a['modifiedAt']);

        return $items;
    }

    /**
     * @brief Delete one snapshot file by basename when it lives under the snapshot directory.
     *
     * @param string $basename Snapshot filename (no path segments).
     * @return void
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function deleteSnapshot(string $basename): void
    {
        $safe = basename($basename);
        if ($safe === '' || $safe !== $basename || !str_ends_with($safe, '.cvbackup')) {
            throw CustomizationBackupException::withReason('snapshot_invalid_name');
        }

        $path = $this->getSnapshotDirectory().'/'.$safe;
        if (!is_file($path)) {
            throw CustomizationBackupException::withReason('snapshot_not_found', [
                '%filename%' => $safe,
            ]);
        }

        if (!unlink($path)) {
            throw CustomizationBackupException::withReason('reset_failed');
        }
    }

    /**
     * @brief Absolute path to the snapshot storage directory.
     *
     * @return string
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function getSnapshotDirectory(): string
    {
        return rtrim($this->projectDir, '/').'/var/'.self::SNAPSHOT_SUBDIR;
    }
}
