<?php

declare(strict_types=1);

namespace App\Tests\PHPUnit;

use PHPUnit\Event\TestRunner\Started;
use PHPUnit\Event\TestRunner\StartedSubscriber;

/**
 * @brief Marks the beginning of a PHPUnit run and resets the debug log + buffer.
 *
 * @date 2026-05-02
 * @author Stephane H.
 */
final class CursorAgentPhpunitStartedSubscriber implements StartedSubscriber
{
    public function __construct(
        private readonly string $logPath,
        private readonly string $sessionId,
        private readonly CursorAgentPhpunitRunBuffer $buffer,
    ) {
    }

    /**
     * @brief Handle TestRunner Started: truncate log and reset captured rows.
     *
     * @param Started $event PHPUnit startup event.
     *
     * @return void
     *
     * @date 2026-05-02
     * @author Stephane H.
     */
    public function notify(Started $event): void
    {
        $this->buffer->reset();

        CursorAgentPhpunitNdjsonWriter::overwrite($this->logPath, [
            'sessionId' => $this->sessionId,
            'hypothesisId' => 'PHPUNIT',
            'location' => 'CursorAgentPhpunitStartedSubscriber',
            'message' => 'phpunit_run_started',
            'data' => [
                'runId' => bin2hex(random_bytes(8)),
            ],
            'timestamp' => (int) round(microtime(true) * 1000),
        ]);
    }
}
