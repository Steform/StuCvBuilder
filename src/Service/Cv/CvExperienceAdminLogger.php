<?php

declare(strict_types=1);

namespace App\Service\Cv;

use Psr\Log\LoggerInterface;

/**
 * @brief Dev-only structured audit log for CV experience admin add/update/delete flows.
 */
final class CvExperienceAdminLogger
{
    /**
     * @brief Wire experience admin audit logger.
     *
     * @param LoggerInterface $logger Monolog channel `cv_experience_admin`.
     * @param bool $enabled True when kernel.debug is enabled (dev).
     * @return void
     * @date 2026-06-04
     * @author Stephane H.
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly bool $enabled,
    ) {
    }

    /**
     * @brief Log one audit event when dev logging is enabled.
     *
     * @param string $action Short action slug (e.g. save_start, merge_diff).
     * @param array<string, mixed> $context Structured context payload.
     * @return void
     * @date 2026-06-04
     * @author Stephane H.
     */
    public function log(string $action, array $context = []): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->logger->info('[cv_experience_admin] '.$action, $context);
    }

    /**
     * @brief Log a compact snapshot of entries grouped by locale.
     *
     * @param string $phase Pipeline phase label.
     * @param array<string, list<array<string, mixed>>> $entriesByLocale Locale-keyed rows.
     * @param array<string, mixed> $extra Additional context keys.
     * @return void
     * @date 2026-06-04
     * @author Stephane H.
     */
    public function logEntriesSnapshot(string $phase, array $entriesByLocale, array $extra = []): void
    {
        $this->log($phase, array_merge($extra, [
            'entries' => self::summarizeEntriesByLocale($entriesByLocale),
            'entryIds' => self::collectEntryIds($entriesByLocale),
        ]));
    }

    /**
     * @brief Log UUID diff between two locale maps (detect silent removals).
     *
     * @param string $phase Diff phase label.
     * @param array<string, list<array<string, mixed>>> $before Entries before transformation.
     * @param array<string, list<array<string, mixed>>> $after Entries after transformation.
     * @param array<string, mixed> $extra Additional context keys.
     * @return void
     * @date 2026-06-04
     * @author Stephane H.
     */
    public function logEntriesDiff(
        string $phase,
        array $before,
        array $after,
        array $extra = [],
    ): void {
        $beforeIds = self::collectEntryIds($before);
        $afterIds = self::collectEntryIds($after);

        $this->log($phase, array_merge($extra, [
            'beforeCount' => count($beforeIds),
            'afterCount' => count($afterIds),
            'removedIds' => array_values(array_diff($beforeIds, $afterIds)),
            'addedIds' => array_values(array_diff($afterIds, $beforeIds)),
            'before' => self::summarizeEntriesByLocale($before),
            'after' => self::summarizeEntriesByLocale($after),
        ]));
    }

    /**
     * @brief Log browser-side admin actions forwarded from cv-experience-admin.js.
     *
     * @param array<string, mixed> $payload Client event payload.
     * @return void
     * @date 2026-06-04
     * @author Stephane H.
     */
    public function logClientEvent(array $payload): void
    {
        $action = isset($payload['action']) && is_string($payload['action']) ? $payload['action'] : 'unknown';
        unset($payload['action']);

        $this->log('client_'.$action, $payload);
    }

    /**
     * @brief Build a compact per-locale summary for logs.
     *
     * @param array<string, list<array<string, mixed>>> $entriesByLocale Locale-keyed rows.
     * @return array<string, list<array<string, mixed>>>
     * @date 2026-06-04
     * @author Stephane H.
     */
    public static function summarizeEntriesByLocale(array $entriesByLocale): array
    {
        $summary = [];
        foreach ($entriesByLocale as $locale => $rows) {
            if (!is_string($locale) || !is_array($rows)) {
                continue;
            }

            $summary[$locale] = [];
            foreach ($rows as $index => $row) {
                if (!is_array($row)) {
                    continue;
                }

                $title = isset($row['title']) && is_string($row['title']) ? $row['title'] : '';
                $company = isset($row['companyName']) && is_string($row['companyName']) ? $row['companyName'] : '';

                $detailHtml = isset($row['detailHtml']) && is_string($row['detailHtml']) ? $row['detailHtml'] : '';

                $summary[$locale][] = [
                    'index' => $index,
                    'id' => isset($row['id']) && is_string($row['id']) ? $row['id'] : '',
                    'sortOrder' => $row['sortOrder'] ?? null,
                    'title' => mb_substr($title, 0, 80),
                    'companyName' => mb_substr($company, 0, 60),
                    'startDate' => $row['startDate'] ?? '',
                    'endDate' => $row['endDate'] ?? '',
                    'isCurrent' => $row['isCurrent'] ?? false,
                    'isPrimary' => $row['isPrimary'] ?? null,
                    'detailHtmlLength' => mb_strlen(trim($detailHtml)),
                ];
            }
        }

        return $summary;
    }

    /**
     * @brief Collect unique entry UUIDs across all locales.
     *
     * @param array<string, list<array<string, mixed>>> $entriesByLocale Locale-keyed rows.
     * @return list<string>
     * @date 2026-06-04
     * @author Stephane H.
     */
    public static function collectEntryIds(array $entriesByLocale): array
    {
        $ids = [];
        foreach ($entriesByLocale as $rows) {
            if (!is_array($rows)) {
                continue;
            }

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $entryId = isset($row['id']) && is_string($row['id']) ? trim($row['id']) : '';
                if ($entryId !== '' && !in_array($entryId, $ids, true)) {
                    $ids[] = $entryId;
                }
            }
        }

        return $ids;
    }
}
