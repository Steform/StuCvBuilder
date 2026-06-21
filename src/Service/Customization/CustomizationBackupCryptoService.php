<?php

declare(strict_types=1);

namespace App\Service\Customization;

use App\Exception\Customization\CustomizationBackupException;

/**
 * @brief AES-256-GCM encrypt/decrypt for customization backup blobs (Option A envelope).
 */
final class CustomizationBackupCryptoService
{
    private const PREFIX = 'cbak.v1.';

    /**
     * @param string $secretMaterial Value from APP_CUSTOMIZATION_BACKUP_ENCRYPTION_KEY.
     */
    public function __construct(
        private readonly string $secretMaterial,
    ) {
    }

    /**
     * @brief Whether encryption key material is configured.
     *
     * @param void No input parameter.
     * @return bool
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function isConfigured(): bool
    {
        return trim($this->secretMaterial) !== '';
    }

    /**
     * @brief Encrypt raw ZIP bytes into an ASCII-safe backup blob.
     *
     * @param string $plainZipBytes ZIP binary payload.
     * @return string Encrypted blob with version prefix.
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function encrypt(string $plainZipBytes): string
    {
        $this->assertConfigured();

        $key = $this->deriveKey();
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plainZipBytes, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, self::PREFIX);
        if ($cipher === false || strlen($tag) !== 16) {
            throw CustomizationBackupException::withReason('encrypt_failed');
        }

        return self::PREFIX.base64_encode($iv.$tag.$cipher);
    }

    /**
     * @brief Decrypt a backup blob back to raw ZIP bytes.
     *
     * @param string $encryptedBlob Stored or uploaded ciphertext.
     * @return string ZIP binary payload.
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function decrypt(string $encryptedBlob): string
    {
        $this->assertConfigured();

        if (!str_starts_with($encryptedBlob, self::PREFIX)) {
            throw CustomizationBackupException::withReason('decrypt_prefix_missing');
        }

        $raw = base64_decode(substr($encryptedBlob, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) < 12 + 16) {
            throw CustomizationBackupException::withReason('decrypt_payload_too_short');
        }

        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $key = $this->deriveKey();
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, self::PREFIX);
        if ($plain === false) {
            throw CustomizationBackupException::withReason('decrypt_failed');
        }

        return $plain;
    }

    /**
     * @brief Ensure secret material is present before crypto operations.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    private function assertConfigured(): void
    {
        if (!$this->isConfigured()) {
            throw CustomizationBackupException::withReason('key_missing');
        }
    }

    /**
     * @brief Derive a 256-bit key from configured secret material.
     *
     * @param void No input parameter.
     * @return string Raw 32-byte key.
     * @date 2026-05-16
     * @author Stephane H.
     */
    private function deriveKey(): string
    {
        return hash('sha256', self::PREFIX.$this->secretMaterial, true);
    }
}
