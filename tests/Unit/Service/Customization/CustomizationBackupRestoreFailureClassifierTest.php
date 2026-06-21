<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Customization;

use App\Exception\Customization\CustomizationBackupException;
use App\Service\Customization\CustomizationBackupRestoreFailureClassifier;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;

final class CustomizationBackupRestoreFailureClassifierTest extends TestCase
{
    /**
     * @brief Build a translator with step labels for classifier tests.
     *
     * @param void No input parameter.
     * @return TranslatorInterface
     * @date 2026-05-19
     * @author Stephane H.
     */
    private function createTranslator(): TranslatorInterface
    {
        $translator = new Translator('fr');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', [
            'dashboard.customization_backup.error.db_step_home_quick_tiles' => 'tuiles accueil',
            'dashboard.customization_backup.error.db_step_home_translations' => 'traductions accueil',
            'dashboard.customization_backup.error.db_step_cv_profile' => 'profil CV',
        ], 'fr', 'messages');

        return $translator;
    }

    /**
     * @brief Unique constraint violations must map to db_unique_violation with constraint placeholder.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function testUniqueConstraintMapsToDedicatedReason(): void
    {
        $classifier = new CustomizationBackupRestoreFailureClassifier($this->createTranslator(), false);
        $cause = new UniqueConstraintViolationException(
            \Doctrine\DBAL\Driver\PDO\Exception::new(new \PDOException("Duplicate entry for key 'home_quick_tile.PRIMARY'")),
            null,
        );

        $result = $classifier->classify($cause, CustomizationBackupRestoreFailureClassifier::STEP_HOME_QUICK_TILES);

        self::assertSame('db_unique_violation', $result->getReasonCode());
        self::assertSame('dashboard.customization_backup.error.db_unique_violation', $result->getTranslationKey());
        self::assertSame('tuiles accueil', $result->getTranslationParameters()['%step%']);
        self::assertSame('home_quick_tile.PRIMARY', $result->getTranslationParameters()['%constraint%']);
    }

    /**
     * @brief Sanitized details must strip absolute filesystem paths from messages.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function testSanitizeDetailRemovesAbsolutePaths(): void
    {
        $classifier = new CustomizationBackupRestoreFailureClassifier($this->createTranslator(), true);
        $detail = $classifier->sanitizeDetailForUser('Error at C:\\wamp64\\www\\project\\file.sql');

        self::assertStringNotContainsString('wamp64', $detail);
        self::assertStringContainsString('[path]', $detail);
    }

    /**
     * @brief Non-debug mode must shorten driver messages using SQLSTATE when available.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function testSanitizeDetailInProdUsesSqlStateSummary(): void
    {
        $classifier = new CustomizationBackupRestoreFailureClassifier($this->createTranslator(), false);
        $detail = $classifier->sanitizeDetailForUser(
            "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'x' for key 'home_quick_tile.PRIMARY'"
        );

        self::assertStringContainsString('SQLSTATE[23000]', $detail);
        self::assertLessThanOrEqual(120, strlen($detail));
    }

    /**
     * @brief JsonException during CV restore must map to cv_content_json_invalid.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function testJsonExceptionMapsToCvContentInvalid(): void
    {
        $classifier = new CustomizationBackupRestoreFailureClassifier($this->createTranslator(), false);

        $result = $classifier->classify(
            new \JsonException('Malformed UTF-8'),
            CustomizationBackupRestoreFailureClassifier::STEP_CV_PROFILE
        );

        self::assertSame('cv_content_json_invalid', $result->getReasonCode());
    }

    /**
     * @brief Schema mismatch fragments must map to db_schema_mismatch with detail.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function testUnknownColumnMapsToSchemaMismatch(): void
    {
        $classifier = new CustomizationBackupRestoreFailureClassifier($this->createTranslator(), false);

        $result = $classifier->classify(
            new \RuntimeException("SQLSTATE[42S22]: Column not found: 1054 Unknown column 'foo' in 'field list'"),
            CustomizationBackupRestoreFailureClassifier::STEP_HOME_SCALARS
        );

        self::assertSame('db_schema_mismatch', $result->getReasonCode());
        self::assertNotSame('', $result->getTranslationParameters()['%detail%'] ?? '');
    }

    /**
     * @brief Home customization locale unique violations must not be attributed to quick tiles.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function testHomeCustomizationLocaleUniqueViolationRemapsStep(): void
    {
        $classifier = new CustomizationBackupRestoreFailureClassifier($this->createTranslator(), false);
        $cause = new UniqueConstraintViolationException(
            \Doctrine\DBAL\Driver\PDO\Exception::new(new \PDOException(
                "SQLSTATE[23000]: Duplicate entry '1-de' for key 'uniq_home_customization_locale'"
            )),
            null,
        );

        $result = $classifier->classify($cause, CustomizationBackupRestoreFailureClassifier::STEP_HOME_QUICK_TILES);

        self::assertSame('db_unique_violation', $result->getReasonCode());
        self::assertSame('traductions accueil', $result->getTranslationParameters()['%step%']);
    }

    /**
     * @brief Transaction-level failures on home locale constraint must not be labeled as main fields.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function testInferStepFromFailureMapsHomeLocaleConstraint(): void
    {
        $classifier = new CustomizationBackupRestoreFailureClassifier($this->createTranslator(), false);
        $cause = new \RuntimeException(
            "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicata du champ '1-de' pour la clef 'uniq_home_customization_locale'"
        );

        self::assertSame(
            CustomizationBackupRestoreFailureClassifier::STEP_HOME_TRANSLATIONS,
            $classifier->inferStepFromFailure($cause)
        );

        $result = $classifier->classify($cause, CustomizationBackupRestoreFailureClassifier::STEP_HOME_SCALARS);
        self::assertSame('traductions accueil', $result->getTranslationParameters()['%step%']);
    }

    /**
     * @brief Already classified backup exceptions must be returned unchanged.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function testPreservesCustomizationBackupException(): void
    {
        $classifier = new CustomizationBackupRestoreFailureClassifier($this->createTranslator(), false);
        $original = CustomizationBackupException::withReason('checksum_mismatch', ['%path%' => 'manifest.json']);

        self::assertSame($original, $classifier->classify($original, CustomizationBackupRestoreFailureClassifier::STEP_CV_PROFILE));
    }
}
