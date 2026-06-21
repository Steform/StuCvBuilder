<?php

declare(strict_types=1);

namespace App\Tests\PHPUnit;

use PHPUnit\Event\Test\Failed;
use PHPUnit\Event\Test\FailedSubscriber;

/**
 * @brief Records assertion failures into the shared buffer and NDJSON log.
 *
 * @date 2026-05-02
 * @author Stephane H.
 */
final class CursorAgentPhpunitFailedSubscriber implements FailedSubscriber
{
    private const DETAIL_MAX_BYTES = 12000;

    public function __construct(
        private readonly string $logPath,
        private readonly string $sessionId,
        private readonly CursorAgentPhpunitRunBuffer $buffer,
    ) {
    }

    /**
     * @brief Persist failure metadata for Cursor agent analysis.
     *
     * @param Failed $event Assertion failure event.
     *
     * @return void
     *
     * @date 2026-05-02
     * @author Stephane H.
     */
    public function notify(Failed $event): void
    {
        $throwable = $event->throwable();
        $testId = $event->test()->id();
        $detail = $throwable->asString();
        if (strlen($detail) > self::DETAIL_MAX_BYTES) {
            $detail = substr($detail, 0, self::DETAIL_MAX_BYTES).'…';
        }

        $this->buffer->addFailure(
            $testId,
            $throwable->className(),
            $throwable->message(),
            $detail
        );

        CursorAgentPhpunitNdjsonWriter::append($this->logPath, [
            'sessionId' => $this->sessionId,
            'hypothesisId' => 'PHPUNIT',
            'location' => $testId,
            'message' => 'test_failed',
            'data' => [
                'throwableClass' => $throwable->className(),
                'shortMessage' => $throwable->message(),
                'detail' => $detail,
            ],
            'timestamp' => (int) round(microtime(true) * 1000),
        ]);
    }
}
