<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Locale;

use App\Service\Locale\LocaleCodeNormalizer;
use App\Service\Locale\LocaleConfigurationService;
use PHPUnit\Framework\TestCase;

/**
 * @brief Unit tests for persisted locale configuration auto-heal.
 *
 * @date 2026-07-05
 * @author Stephane H.
 */
final class LocaleConfigurationServiceAutoHealTest extends TestCase
{
    /**
     * @brief Reading legacy no codes must rewrite locale_configuration.json to nb.
     *
     * @return void
     * @date 2026-07-05
     * @author Stephane H.
     */
    public function testGetConfigurationRewritesLegacyNoOnDisk(): void
    {
        $projectDir = sys_get_temp_dir().'/stu-cv-locale-auto-heal-'.bin2hex(random_bytes(4));
        $configDir = $projectDir.'/var/config';
        mkdir($configDir, 0775, true);

        $configPath = $configDir.'/locale_configuration.json';
        file_put_contents($configPath, (string) json_encode([
            'active_locales' => ['fr', 'en', 'no'],
            'default_locale' => 'no',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $service = new LocaleConfigurationService(
            ['fr', 'en', 'de', 'lt', 'nb'],
            'en',
            $projectDir,
            new LocaleCodeNormalizer(),
        );

        $configuration = $service->getConfiguration();

        self::assertSame(['fr', 'en', 'nb'], $configuration['activeLocales']);
        self::assertSame('nb', $configuration['defaultLocale']);

        $decoded = json_decode((string) file_get_contents($configPath), true);
        self::assertIsArray($decoded);
        self::assertSame(['fr', 'en', 'nb'], $decoded['active_locales']);
        self::assertSame('nb', $decoded['default_locale']);
    }
}
