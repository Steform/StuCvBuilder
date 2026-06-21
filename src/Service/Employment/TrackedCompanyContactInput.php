<?php

declare(strict_types=1);

namespace App\Service\Employment;

/**
 * Optional recruiter contact fields for tracked company create/update.
 */
final readonly class TrackedCompanyContactInput
{
    /**
     * @brief Build contact input value object.
     *
     * @param string|null $recruiterName Recruiter display name.
     * @param string|null $addressLine1 Street address line 1.
     * @param string|null $addressLine2 Street address line 2.
     * @param string|null $addressPostalCode Postal or ZIP code.
     * @param string|null $addressCity City.
     * @param string|null $phone Phone number.
     * @param string|null $email Email address.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function __construct(
        public ?string $recruiterName,
        public ?string $addressLine1,
        public ?string $addressLine2,
        public ?string $addressPostalCode,
        public ?string $addressCity,
        public ?string $phone,
        public ?string $email,
    ) {
    }
}
