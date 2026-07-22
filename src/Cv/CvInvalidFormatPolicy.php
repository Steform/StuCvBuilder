<?php

declare(strict_types=1);

namespace App\Cv;

/**
 * @brief Policy when an invalid recruiter format query is present on public CV routes.
 *
 * @date 2026-07-22
 * @author Stephane H.
 */
enum CvInvalidFormatPolicy: string
{
    case Allow = 'allow';
    case Deny = 'deny';

    /**
     * @brief Resolve stored value with safe default preserving legacy behaviour.
     *
     * @param mixed $raw Raw persisted policy slug.
     * @return self Resolved invalid-format policy.
     * @date 2026-07-22
     * @author Stephane H.
     */
    public static function fromStored(mixed $raw): self
    {
        if (is_string($raw) && $raw !== '') {
            $resolved = self::tryFrom($raw);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return self::Allow;
    }
}
