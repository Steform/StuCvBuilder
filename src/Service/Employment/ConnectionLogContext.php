<?php

declare(strict_types=1);

namespace App\Service\Employment;

use App\Entity\CompanyCvVisit;
use App\Entity\TrackedCompany;

/**
 * Input DTO for persisting a CV connection log row.
 */
final class ConnectionLogContext
{
    /**
     * @brief Build connection log context.
     *
     * @param string $connectionKind ConnectionKind constant.
     * @param string|null $formatRaw Raw format from request.
     * @param TrackedCompany|null $company Resolved company when applicable.
     * @param string|null $ipAddress Client IP.
     * @param string|null $countryCode Geo country code.
     * @param string|null $userAgent User-Agent header.
     * @param bool $gatePassed Whether gate was passed.
     * @param string|null $attestationMethod Attestation method or null.
     * @param int|null $technicalScore Technical score.
     * @param bool $countableForCompany Official company visit flag.
     * @param bool $isAdminBypass Admin bypass flag.
     * @param string|null $requestPath Request path.
     * @param string|null $requestRoute Route name.
     * @param CompanyCvVisit|null $visit Linked official visit.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function __construct(
        public readonly string $connectionKind,
        public readonly ?string $formatRaw = null,
        public readonly ?TrackedCompany $company = null,
        public readonly ?string $ipAddress = null,
        public readonly ?string $countryCode = null,
        public readonly ?string $userAgent = null,
        public readonly bool $gatePassed = false,
        public readonly ?string $attestationMethod = null,
        public readonly ?int $technicalScore = null,
        public readonly bool $countableForCompany = false,
        public readonly bool $isAdminBypass = false,
        public readonly ?string $requestPath = null,
        public readonly ?string $requestRoute = null,
        public readonly ?CompanyCvVisit $visit = null,
    ) {
    }
}
