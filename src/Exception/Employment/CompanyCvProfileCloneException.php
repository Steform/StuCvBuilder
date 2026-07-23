<?php

declare(strict_types=1);

namespace App\Exception\Employment;

use RuntimeException;
use Throwable;

/**
 * @brief Thrown when a company CV profile clone cannot isolate customizable assets on disk.
 */
final class CompanyCvProfileCloneException extends RuntimeException
{
    /**
     * @brief Build clone asset failure exception.
     *
     * @param string $message Technical detail for logs.
     * @param Throwable|null $previous Previous exception when chaining.
     * @return void
     * @date 2026-07-23
     * @author Stephane H.
     */
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
