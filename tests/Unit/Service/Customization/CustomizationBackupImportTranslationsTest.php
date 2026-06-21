<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Customization;

use App\Service\Customization\CustomizationBackupImportService;
use App\Tests\Support\TestCaseMockFactory;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * @brief Unit tests for home translation deduplication during backup import.
 */
final class CustomizationBackupImportTranslationsTest extends TestCase
{
    /**
     * @brief Duplicate locales in backup JSON must collapse to a single row (last wins).
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function testDeduplicateHomeTranslationRowsKeepsLastLocaleEntry(): void
    {
        $importService = TestCaseMockFactory::createWithoutConstructor(CustomizationBackupImportService::class);

        $method = new ReflectionMethod(CustomizationBackupImportService::class, 'deduplicateHomeTranslationRows');
        $method->setAccessible(true);

        /** @var list<array{locale: string, introText: string}> $rows */
        $rows = $method->invoke($importService, [
            ['locale' => 'de', 'introText' => 'first'],
            ['locale' => 'fr', 'introText' => 'french'],
            ['locale' => 'DE', 'introText' => 'second'],
        ]);

        self::assertCount(2, $rows);

        $byLocale = [];
        foreach ($rows as $row) {
            $byLocale[strtolower($row['locale'])] = $row['introText'];
        }

        self::assertSame('second', $byLocale['de']);
        self::assertSame('french', $byLocale['fr']);
    }
}
