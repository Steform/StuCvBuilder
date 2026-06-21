<?php

declare(strict_types=1);

namespace App\Tests\PHPUnit;

use PHPUnit\Event\TestRunner\Finished;
use PHPUnit\Event\TestRunner\FinishedSubscriber;

/**
 * @brief Appends a final NDJSON summary after the test runner finishes.
 *
 * @date 2026-05-02
 * @author Stephane H.
 */
final class CursorAgentPhpunitFinishedSubscriber implements FinishedSubscriber
{
    public function __construct(
        private readonly string $logPath,
        private readonly string $sessionId,
        private readonly CursorAgentPhpunitRunBuffer $buffer,
    ) {
    }

    /**
     * @brief Handle TestRunner Finished: emit aggregate counts and compact per-test summaries (no duplicate stack traces).
     *
     * @param Finished $event PHPUnit shutdown event.
     *
     * @return void
     *
     * @date 2026-05-02
     * @author Stephane H.
     */
    public function notify(Finished $event): void
    {
        $failures = $this->buffer->failures();
        $errors = $this->buffer->errors();

        $mapShort = static function (array $rows): array {
            $out = [];
            foreach ($rows as $row) {
                $out[] = [
                    'testId' => $row['testId'],
                    'throwableClass' => $row['throwableClass'],
                    'message' => $row['message'],
                ];
            }

            return $out;
        };

        CursorAgentPhpunitNdjsonWriter::append($this->logPath, [
            'sessionId' => $this->sessionId,
            'hypothesisId' => 'PHPUNIT',
            'location' => 'CursorAgentPhpunitFinishedSubscriber',
            'message' => 'phpunit_run_finished',
            'data' => [
                'failureCount' => \count($failures),
                'errorCount' => \count($errors),
                'failureSummaries' => $mapShort($failures),
                'errorSummaries' => $mapShort($errors),
            ],
            'timestamp' => (int) round(microtime(true) * 1000),
        ]);
    }
}
