<?php

declare(strict_types=1);

namespace App\Cv;

/**
 * @brief Workflow status for a public CV access request.
 *
 * @date 2026-07-22
 * @author Stephane H.
 */
enum CvAccessRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
