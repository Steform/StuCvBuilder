<?php

declare(strict_types=1);

namespace App\Tests\Functional\Cv;

use App\Cv\CvProfilePersistenceScope;
use App\Service\Cv\CvInterestsSettingsService;
use App\Service\Cv\InterestsContract;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * @brief Functional checks for interests JSON persistence contract.
 *
 * @date 2026-06-11
 * @author Stephane H.
 */
final class CvInterestsPersistenceTest extends KernelTestCase
{
    /**
     * @brief Service must be registered in the container.
     *
     * @return void
     * @date 2026-06-11
     * @author Stephane H.
     */
    public function testCvInterestsSettingsServiceIsRegistered(): void
    {
        self::bootKernel();
        self::assertInstanceOf(
            CvInterestsSettingsService::class,
            static::getContainer()->get(CvInterestsSettingsService::class)
        );
    }

    /**
     * @brief Serialized payload round-trip keeps interest entries after sanitization.
     *
     * @return void
     * @date 2026-06-11
     * @author Stephane H.
     */
    public function testInterestsPayloadRoundTrip(): void
    {
        $service = new CvInterestsSettingsService();
        $payload = [
            InterestsContract::KEY_ENTRIES => [
                [
                    'id' => '550e8400-e29b-41d4-a716-446655440000',
                    'sortOrder' => 0,
                    'iconType' => InterestsContract::ICON_TYPE_BOOTSTRAP,
                    'icon' => 'bi-camera',
                    'iconPath' => null,
                    'labelByLocale' => [
                        'fr' => 'Photographie',
                        'en' => 'Photography',
                    ],
                ],
            ],
            InterestsContract::KEY_COLUMNS_PER_ROW => 5,
        ];

        $sanitized = CvProfilePersistenceScope::sanitizeForPersistence($payload);
        $json = (string) json_encode($sanitized, JSON_UNESCAPED_UNICODE);
        $resolved = $service->resolveFromContentJson($json, ['fr', 'en'], 'fr', 'en');

        self::assertTrue($resolved['hasPersistedEntries']);
        self::assertCount(1, $resolved['entries']);
        self::assertSame('Photography', $resolved['entries'][0]['label']);
        self::assertSame('bi-camera', $resolved['entries'][0]['icon']);
        self::assertSame(5, $resolved['columnsPerRow']);
    }

    /**
     * @brief Columns-per-row layout setting must survive persistence sanitization.
     *
     * @return void
     * @date 2026-06-11
     * @author Stephane H.
     */
    public function testInterestsColumnsPerRowPayloadRoundTrip(): void
    {
        $service = new CvInterestsSettingsService();
        $payload = [
            InterestsContract::KEY_COLUMNS_PER_ROW => 5,
        ];

        $sanitized = CvProfilePersistenceScope::sanitizeForPersistence($payload);
        $json = (string) json_encode($sanitized, JSON_UNESCAPED_UNICODE);
        $resolved = $service->resolveFromContentJson($json, ['fr'], 'fr', 'fr');

        self::assertSame(5, $resolved['columnsPerRow']);
        self::assertSame(5, $sanitized[InterestsContract::KEY_COLUMNS_PER_ROW]);
    }
}
