<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Customization;

use App\Exception\Customization\CustomizationBackupException;
use App\Service\Customization\CustomizationBackupCryptoService;
use PHPUnit\Framework\TestCase;

final class CustomizationBackupCryptoServiceTest extends TestCase
{
    /**
     * @brief Ensure encrypt/decrypt round-trip preserves ZIP bytes.
     *
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function testEncryptDecryptRoundTrip(): void
    {
        $service = new CustomizationBackupCryptoService('test-customization-backup-key-material');
        $plain = "PK\x03\x04".str_repeat('A', 128);

        $encrypted = $service->encrypt($plain);
        self::assertStringStartsWith('cbak.v1.', $encrypted);

        $decrypted = $service->decrypt($encrypted);
        self::assertSame($plain, $decrypted);
    }

    /**
     * @brief Ensure missing key is reported as not configured.
     *
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function testMissingKeyThrows(): void
    {
        $service = new CustomizationBackupCryptoService('');

        self::assertFalse($service->isConfigured());

        $this->expectException(CustomizationBackupException::class);
        $this->expectExceptionMessage('dashboard.customization_backup.error.key_missing');
        $service->encrypt('data');
    }

    /**
     * @brief Wrong encryption key must yield decrypt_failed, not a generic invalid archive message.
     *
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function testWrongKeyYieldsDecryptFailed(): void
    {
        $exporter = new CustomizationBackupCryptoService('export-key-material');
        $importer = new CustomizationBackupCryptoService('import-key-material');
        $encrypted = $exporter->encrypt('zip-payload');

        try {
            $importer->decrypt($encrypted);
            self::fail('Expected CustomizationBackupException');
        } catch (CustomizationBackupException $exception) {
            self::assertSame('decrypt_failed', $exception->getReasonCode());
            self::assertSame('dashboard.customization_backup.error.decrypt_failed', $exception->getTranslationKey());
        }
    }

    /**
     * @brief Non-backup content must report missing cbak.v1. prefix.
     *
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function testMissingPrefixYieldsDecryptPrefixMissing(): void
    {
        $service = new CustomizationBackupCryptoService('test-key');

        try {
            $service->decrypt('not-a-backup-blob');
            self::fail('Expected CustomizationBackupException');
        } catch (CustomizationBackupException $exception) {
            self::assertSame('decrypt_prefix_missing', $exception->getReasonCode());
        }
    }
}
