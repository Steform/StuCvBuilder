<?php

declare(strict_types=1);

namespace App\Service\Customization;

use App\Exception\Customization\CustomizationBackupException;
use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\NotNullConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\Exception\ORMException;
use JsonException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

/**
 * @brief Map database restore failures to user-facing backup exception messages.
 */
final class CustomizationBackupRestoreFailureClassifier
{
    public const STEP_HOME_SCALARS = 'home_scalars';

    public const STEP_HOME_TRANSLATIONS = 'home_translations';

    public const STEP_HOME_QUICK_TILES = 'home_quick_tiles';

    public const STEP_CV_PROFILE = 'cv_profile';

    public const STEP_EMPLOYMENT = 'employment';

    /**
     * @param TranslatorInterface $translator Resolves step labels for flash placeholders.
     * @param bool $debug Whether verbose driver details may be shown to admins.
     */
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly bool $debug,
    ) {
    }

    /**
     * @brief Build a user-facing backup exception from a restore step failure.
     *
     * @param Throwable $cause Original failure thrown during restore.
     * @param string $step Restore step constant (STEP_*).
     * @return CustomizationBackupException
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function classify(Throwable $cause, string $step): CustomizationBackupException
    {
        if ($cause instanceof CustomizationBackupException) {
            return $cause;
        }

        $root = $this->resolveRootCause($cause);
        $step = $this->resolveEffectiveStep($root, $step);
        $stepLabel = $this->resolveStepLabel($step);
        $message = $root->getMessage();

        if ($root instanceof JsonException) {
            return CustomizationBackupException::withReason('cv_content_json_invalid', [], $root);
        }

        if ($root instanceof UniqueConstraintViolationException) {
            return CustomizationBackupException::withReason('db_unique_violation', [
                '%step%' => $stepLabel,
                '%constraint%' => $this->extractConstraintName($message),
            ], $root);
        }

        if ($root instanceof ForeignKeyConstraintViolationException) {
            return CustomizationBackupException::withReason('db_foreign_key_violation', [
                '%step%' => $stepLabel,
                '%constraint%' => $this->extractConstraintName($message),
            ], $root);
        }

        if ($root instanceof NotNullConstraintViolationException) {
            return CustomizationBackupException::withReason('db_not_null_violation', [
                '%step%' => $stepLabel,
                '%column%' => $this->extractColumnName($message),
            ], $root);
        }

        if ($root instanceof ConnectionException) {
            return CustomizationBackupException::withReason('db_connection_failed', [
                '%step%' => $stepLabel,
            ], $root);
        }

        if ($this->isSchemaMismatchMessage($message)) {
            return CustomizationBackupException::withReason('db_schema_mismatch', [
                '%step%' => $stepLabel,
                '%detail%' => $this->sanitizeDetailForUser($message),
            ], $root);
        }

        if ($root instanceof DriverException || $root instanceof ORMException) {
            return CustomizationBackupException::withReason('db_driver_error', [
                '%step%' => $stepLabel,
                '%detail%' => $this->sanitizeDetailForUser($message),
            ], $root);
        }

        return CustomizationBackupException::withReason('db_restore_failed', [
            '%step%' => $stepLabel,
            '%detail%' => $this->sanitizeDetailForUser($message),
        ], $root);
    }

    /**
     * @brief Sanitize a driver message for admin-facing flash output.
     *
     * @param string $message Raw exception message.
     * @return string Truncated, path-stripped detail safe for UI.
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function sanitizeDetailForUser(string $message): string
    {
        $normalized = trim($message);
        if ($normalized === '') {
            return 'unknown';
        }

        $normalized = (string) preg_replace('#([A-Za-z]:\\\\|/var/|/home/|c:\\\\wamp64)[^\s\'"]*#i', '[path]', $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        if (!$this->debug) {
            if (preg_match('/SQLSTATE\[([^\]]+)\]/', $normalized, $sqlStateMatch) === 1) {
                $sqlState = $sqlStateMatch[1];
                $constraint = $this->extractConstraintName($normalized);
                if ($constraint !== 'unknown') {
                    return 'SQLSTATE['.$sqlState.'] '.$constraint;
                }

                return 'SQLSTATE['.$sqlState.']';
            }

            if (strlen($normalized) > 120) {
                return substr($normalized, 0, 117).'...';
            }
        }

        if (strlen($normalized) > 200) {
            return substr($normalized, 0, 197).'...';
        }

        return $normalized;
    }

    /**
     * @brief Map misleading restore steps to the step that actually failed.
     *
     * @param Throwable $root Root technical cause.
     * @param string $step Restore step constant passed by the import service.
     * @return string Effective step constant for flash placeholders.
     * @date 2026-05-19
     * @author Stephane H.
     */
    private function resolveEffectiveStep(Throwable $root, string $step): string
    {
        return $this->inferStepFromFailure($root, $step);
    }

    /**
     * @brief Infer the restore step from a failure when the transaction-level catch has no step context.
     *
     * @param Throwable $cause Failure thrown during restore.
     * @return string Restore step constant (STEP_*).
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function inferStepFromFailure(Throwable $cause, string $fallbackStep = self::STEP_HOME_SCALARS): string
    {
        $root = $this->resolveRootCause($cause);
        $message = $root->getMessage();

        if (stripos($message, 'uniq_home_customization_locale') !== false
            || stripos($message, 'HomeCustomizationTranslation') !== false) {
            return self::STEP_HOME_TRANSLATIONS;
        }

        if (stripos($message, 'home_quick_tile') !== false) {
            return self::STEP_HOME_QUICK_TILES;
        }

        if (stripos($message, 'cv_profile') !== false) {
            return self::STEP_CV_PROFILE;
        }

        if (stripos($message, 'tracked_company') !== false
            || stripos($message, 'address_line1') !== false
            || stripos($message, 'address_line2') !== false
            || stripos($message, 'address_postal_code') !== false
            || stripos($message, 'address_city') !== false
            || stripos($message, 'employment_document') !== false
            || stripos($message, 'employment_country') !== false
            || stripos($message, 'company_cv_visit') !== false
            || stripos($message, 'cv_connection_log') !== false) {
            return self::STEP_EMPLOYMENT;
        }

        return $fallbackStep;
    }

    /**
     * @brief Resolve the deepest non-backup throwable in a chain.
     *
     * @param Throwable $cause Failure to unwrap.
     * @return Throwable Root technical cause.
     * @date 2026-05-19
     * @author Stephane H.
     */
    private function resolveRootCause(Throwable $cause): Throwable
    {
        $current = $cause;
        while ($current->getPrevious() !== null
            && !$current->getPrevious() instanceof CustomizationBackupException
            && !$current instanceof DriverException
            && !$current instanceof ORMException) {
            $current = $current->getPrevious();
        }

        return $current;
    }

    /**
     * @brief Translate a restore step code into a user-facing label.
     *
     * @param string $step Restore step constant (STEP_*).
     * @return string Localized step label for flash placeholders.
     * @date 2026-05-19
     * @author Stephane H.
     */
    private function resolveStepLabel(string $step): string
    {
        $key = 'dashboard.customization_backup.error.db_step_'.$step;

        return $this->translator->trans($key, [], 'messages');
    }

    /**
     * @brief Detect schema drift from common SQL error fragments.
     *
     * @param string $message Driver or ORM error message.
     * @return bool
     * @date 2026-05-19
     * @author Stephane H.
     */
    private function isSchemaMismatchMessage(string $message): bool
    {
        $lower = strtolower($message);

        return str_contains($lower, 'unknown column')
            || str_contains($lower, "doesn't exist")
            || str_contains($lower, 'does not exist')
            || str_contains($lower, 'no such table')
            || str_contains($lower, 'base table or view not found');
    }

    /**
     * @brief Extract a constraint name from a SQL error message when present.
     *
     * @param string $message Driver error message.
     * @return string Constraint name or unknown placeholder.
     * @date 2026-05-19
     * @author Stephane H.
     */
    private function extractConstraintName(string $message): string
    {
        if (preg_match("/constraint [`'\"]?([A-Za-z0-9_.]+)[`'\"]?/i", $message, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('/key [`\'"]?([A-Za-z0-9_.]+)[`\'"]?/i', $message, $matches) === 1) {
            return $matches[1];
        }

        return 'unknown';
    }

    /**
     * @brief Extract a column name from a SQL error message when present.
     *
     * @param string $message Driver error message.
     * @return string Column name or unknown placeholder.
     * @date 2026-05-19
     * @author Stephane H.
     */
    private function extractColumnName(string $message): string
    {
        if (preg_match("/column [`'\"]?([A-Za-z0-9_.]+)[`'\"]?/i", $message, $matches) === 1) {
            return $matches[1];
        }

        return 'unknown';
    }
}
