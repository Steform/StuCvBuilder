<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Customization;

use App\Exception\Customization\CustomizationBackupException;
use App\Service\Customization\CustomizationBackupManifestValidator;
use App\Service\Customization\CustomizationBackupPaths;
use PHPUnit\Framework\TestCase;

final class CustomizationBackupManifestValidatorTest extends TestCase
{
    /**
     * @brief Unsupported format version must expose expected and found placeholders.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function testUnsupportedFormatVersion(): void
    {
        $validator = new CustomizationBackupManifestValidator();

        try {
            $validator->validate(['formatVersion' => 99, 'checksums' => []], []);
            self::fail('Expected CustomizationBackupException');
        } catch (CustomizationBackupException $exception) {
            self::assertSame('format_version_unsupported', $exception->getReasonCode());
            self::assertStringContainsString((string) CustomizationBackupPaths::FORMAT_VERSION, $exception->getTranslationParameters()['%expected%']);
            self::assertSame('99', $exception->getTranslationParameters()['%found%']);
        }
    }

    /**
     * @brief Checksum mismatch must identify the affected archive path.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function testChecksumMismatchIncludesPath(): void
    {
        $validator = new CustomizationBackupManifestValidator();
        $path = CustomizationBackupPaths::DATA_HOME;
        $manifest = [
            'formatVersion' => CustomizationBackupPaths::FORMAT_VERSION,
            'checksums' => [
                $path => hash('sha256', 'expected-bytes'),
                CustomizationBackupPaths::DATA_HOME_TRANSLATIONS => hash('sha256', '[]'),
                CustomizationBackupPaths::DATA_CV_PROFILE => hash('sha256', '{}'),
                CustomizationBackupPaths::DATA_LOCALE => hash('sha256', '{}'),
            ],
        ];
        $entries = [
            $path => 'actual-bytes',
            CustomizationBackupPaths::DATA_HOME_TRANSLATIONS => '[]',
            CustomizationBackupPaths::DATA_CV_PROFILE => '{}',
            CustomizationBackupPaths::DATA_LOCALE => '{}',
        ];

        try {
            $validator->validate($manifest, $entries);
            self::fail('Expected CustomizationBackupException');
        } catch (CustomizationBackupException $exception) {
            self::assertSame('checksum_mismatch', $exception->getReasonCode());
            self::assertSame($path, $exception->getTranslationParameters()['%path%']);
        }
    }
}
