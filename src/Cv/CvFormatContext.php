<?php

declare(strict_types=1);

namespace App\Cv;

/**
 * @brief Resolved recruiter format context for the current CV request.
 *
 * @date 2026-07-22
 * @author Stephane H.
 */
enum CvFormatContext: string
{
    case None = 'none';
    case Invalid = 'invalid';
    case Valid = 'valid';
}
