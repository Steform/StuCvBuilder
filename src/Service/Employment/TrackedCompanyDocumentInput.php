<?php

declare(strict_types=1);

namespace App\Service\Employment;

/**
 * Optional CV / LM document variant ids for a tracked company.
 */
final class TrackedCompanyDocumentInput
{
    /**
     * @brief Build document assignment input.
     *
     * @param int|null $cvDocumentVariantId CV variant primary key or null.
     * @param int|null $lmDocumentVariantId LM variant primary key or null.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function __construct(
        public readonly ?int $cvDocumentVariantId = null,
        public readonly ?int $lmDocumentVariantId = null,
    ) {
    }
}
