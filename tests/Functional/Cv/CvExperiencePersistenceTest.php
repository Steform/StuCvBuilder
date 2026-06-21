<?php

declare(strict_types=1);

namespace App\Tests\Functional\Cv;

use App\Repository\CvProfileRepository;
use App\Service\Customization\CustomizationPlaceholderStateService;
use App\Service\Cv\CvExperienceSettingsService;
use App\Service\Cv\ExperienceContract;
use App\Service\RichText\RichHtmlSanitizer;
use App\Tests\Support\CvPdfPlaceholderTestTranslator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * @brief Functional checks for experience JSON persistence contract.
 * @date 2026-05-15
 * @author Stephane H.
 */
final class CvExperiencePersistenceTest extends KernelTestCase
{
    /**
     * @brief Service must be registered in the container.
     * @return void
     * @date 2026-05-15
     * @author Stephane H.
     */
    public function testCvExperienceSettingsServiceIsRegistered(): void
    {
        self::bootKernel();
        self::assertInstanceOf(
            CvExperienceSettingsService::class,
            static::getContainer()->get(CvExperienceSettingsService::class)
        );
    }

    /**
     * @brief Serialized payload round-trip keeps experience map key.
     * @return void
     * @date 2026-05-15
     * @author Stephane H.
     */
    public function testExperiencePayloadRoundTrip(): void
    {
        $repository = self::createStub(CvProfileRepository::class);
        $repository->method('count')->willReturn(1);
        $service = new CvExperienceSettingsService(
            CvPdfPlaceholderTestTranslator::create(),
            new RichHtmlSanitizer(),
        );
        $payload = [
            ExperienceContract::KEY_ENTRIES_BY_LOCALE => [
                'fr' => [
                    [
                        'id' => '550e8400-e29b-41d4-a716-446655440000',
                        'sortOrder' => 0,
                        'startDate' => '2020-01',
                        'endDate' => '2021-06',
                        'isCurrent' => false,
                        'title' => 'Role A',
                        'companyName' => 'Company A',
                        'companyWebsiteUrl' => null,
                        'highlights' => ['Detail'],
                        'isPrimary' => false,
                    ],
                ],
            ],
        ];

        $json = (string) json_encode($payload, JSON_UNESCAPED_UNICODE);
        $resolved = $service->resolveFromContentJson($json, ['fr'], 'fr', 'fr');

        self::assertTrue($resolved['hasSecondaryVisible']);
        $primary = $service->filterPrimaryVisible($resolved['entries']);
        $all = $service->filterAllVisible($resolved['entries']);
        self::assertCount(0, $primary);
        self::assertCount(1, $all);
        self::assertSame('Role A', $all[0]['title']);
        self::assertCount(1, $resolved['entriesFull']);
        self::assertTrue($resolved['entriesFull'][0]['hiddenOnPrimary']);
    }
}
