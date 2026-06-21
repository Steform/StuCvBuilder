<?php

declare(strict_types=1);

namespace App\Service\Cv;

/**
 * @brief Compute full calendar age in years from an ISO birth date.
 *
 * Uses the PHP default timezone (see date_default_timezone_get()) for "today" and birthday boundary.
 *
 * @date 2026-06-15
 * @author Stephane H.
 */
final class CvAgeYearsCalculator
{
    /**
     * @brief Compute age in full years from Y-m-d birth date or null when invalid/missing.
     *
     * @param string|null $birthDateYmd Birth date as Y-m-d or null.
     * @param \DateTimeImmutable|null $now Optional clock reference (defaults to now in default TZ).
     * @return int|null Non-negative age or null.
     * @date 2026-06-15
     * @author Stephane H.
     */
    public function computeFromBirthDate(?string $birthDateYmd, ?\DateTimeImmutable $now = null): ?int
    {
        if ($birthDateYmd === null || trim($birthDateYmd) === '') {
            return null;
        }

        $tzRaw = date_default_timezone_get();
        $tzName = is_string($tzRaw) && $tzRaw !== '' ? $tzRaw : 'UTC';
        $tz = new \DateTimeZone($tzName);
        $clock = $now ?? new \DateTimeImmutable('now', $tz);
        $todayLocal = $clock->setTimezone($tz)->setTime(0, 0, 0);

        $birth = \DateTimeImmutable::createFromFormat('Y-m-d', trim($birthDateYmd), $tz);
        if ($birth === false) {
            return null;
        }

        $birth = $birth->setTime(0, 0, 0);
        if ($birth > $todayLocal) {
            return null;
        }

        $age = (int) $todayLocal->format('Y') - (int) $birth->format('Y');
        $bMd = $birth->format('md');
        $tMd = $todayLocal->format('md');
        if ($tMd < $bMd) {
            --$age;
        }

        return $age >= 0 ? $age : null;
    }

    /**
     * @brief Compute age from cvPublicIdentity map birthDate field.
     *
     * @param array<string, mixed> $identity cvPublicIdentity map.
     * @param \DateTimeImmutable|null $now Optional clock reference.
     * @return int|null Non-negative age or null.
     * @date 2026-06-15
     * @author Stephane H.
     */
    public function computeFromIdentityMap(array $identity, ?\DateTimeImmutable $now = null): ?int
    {
        $raw = $identity[CvPublicIdentityContract::FIELD_BIRTH_DATE] ?? null;

        return $this->computeFromBirthDate(is_string($raw) ? $raw : null, $now);
    }
}
