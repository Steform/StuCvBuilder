<?php

declare(strict_types=1);

namespace App\Employment;

/**
 * Admin company list consultation status derived from visits and connection logs.
 */
final class CompanyConsultationLevel
{
    public const NONE = 0;

    public const ATTEMPT_WITHOUT_GATE = 1;

    public const OFFICIAL = 2;
}
