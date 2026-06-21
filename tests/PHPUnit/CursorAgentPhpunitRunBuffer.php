<?php

declare(strict_types=1);

namespace App\Tests\PHPUnit;

/**
 * @brief Mutable accumulator for PHPUnit failure/error rows emitted during one run.
 *
 * @date 2026-05-02
 * @author Stephane H.
 */
final class CursorAgentPhpunitRunBuffer
{
    /** @var list<array{testId: string, throwableClass: string, message: string, detail: string}> */
    private array $failures = [];

    /** @var list<array{testId: string, throwableClass: string, message: string, detail: string}> */
    private array $errors = [];

    /**
     * @brief Reset buffers so a new run starts empty.
     *
     * @return void
     *
     * @date 2026-05-02
     * @author Stephane H.
     */
    public function reset(): void
    {
        $this->failures = [];
        $this->errors = [];
    }

    /**
     * @brief Record an assertion failure row.
     *
     * @param string $testId PHPUnit test identifier (class::method).
     * @param string $throwableClass Throwable class name from the event.
     * @param string $message Short message line.
     * @param string $detail Long description (truncated by caller if needed).
     *
     * @return void
     *
     * @date 2026-05-02
     * @author Stephane H.
     */
    public function addFailure(string $testId, string $throwableClass, string $message, string $detail): void
    {
        $this->failures[] = [
            'testId' => $testId,
            'throwableClass' => $throwableClass,
            'message' => $message,
            'detail' => $detail,
        ];
    }

    /**
     * @brief Record a PHP/test error row.
     *
     * @param string $testId PHPUnit test identifier (class::method).
     * @param string $throwableClass Throwable class name from the event.
     * @param string $message Short message line.
     * @param string $detail Long description (truncated by caller if needed).
     *
     * @return void
     *
     * @date 2026-05-02
     * @author Stephane H.
     */
    public function addError(string $testId, string $throwableClass, string $message, string $detail): void
    {
        $this->errors[] = [
            'testId' => $testId,
            'throwableClass' => $throwableClass,
            'message' => $message,
            'detail' => $detail,
        ];
    }

    /**
     * @brief Failure rows captured so far.
     *
     * @return list<array{testId: string, throwableClass: string, message: string, detail: string}>
     *
     * @date 2026-05-02
     * @author Stephane H.
     */
    public function failures(): array
    {
        return $this->failures;
    }

    /**
     * @brief Error rows captured so far.
     *
     * @return list<array{testId: string, throwableClass: string, message: string, detail: string}>
     *
     * @date 2026-05-02
     * @author Stephane H.
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
