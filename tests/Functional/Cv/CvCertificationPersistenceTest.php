<?php

declare(strict_types=1);

namespace App\Tests\Functional\Cv;

use App\Repository\CvProfileRepository;
use App\Service\Customization\CustomizationPlaceholderStateService;
use App\Service\Cv\CertificationContract;
use App\Service\Cv\CvCertificationSettingsService;
use App\Tests\Support\CvPdfPlaceholderTestTranslator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * @brief Functional checks for certification JSON persistence contract.
 *
 * @date 2026-06-11
 * @author Stephane H.
 */
final class CvCertificationPersistenceTest extends KernelTestCase
{
    /**
     * @brief Service must be registered in the container.
     *
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function testCvCertificationSettingsServiceIsRegistered(): void
    {
        self::bootKernel();
        self::assertInstanceOf(
            CvCertificationSettingsService::class,
            static::getContainer()->get(CvCertificationSettingsService::class)
        );
    }

    /**
     * @brief Serialized payload round-trip keeps canonical key and secondary visibility.
     *
     * @return void
     * @date 2026-06-11
     * @author Stephane H.
     */
    public function testCertificationPayloadRoundTrip(): void
    {
        $repository = self::createStub(CvProfileRepository::class);
        $repository->method('count')->willReturn(1);
        $service = new CvCertificationSettingsService(
            CvPdfPlaceholderTestTranslator::create(),
        );
        $payload = [
            CertificationContract::KEY_ENTRIES => [
                [
                    'id' => '550e8400-e29b-41d4-a716-446655440000',
                    'sortOrder' => 0,
                    'startDate' => '2020-06',
                    'endDate' => '2020-06',
                    'isCurrent' => false,
                    'titleByLocale' => ['fr' => 'Phone training'],
                    'providerNameByLocale' => ['fr' => 'BT Formation'],
                    'locationByLocale' => [],
                    'providerWebsiteUrl' => null,
                    'proofPdfPath' => CertificationContract::CERTIFICATION_PROOF_PDF_PATH_PREFIX.'certification-proof-test.pdf',
                    'highlightsByLocale' => ['fr' => ['Professional tone']],
                    'isPrimary' => false,
                ],
            ],
        ];

        $json = (string) json_encode($payload, JSON_UNESCAPED_UNICODE);
        $resolved = $service->resolveFromContentJson($json, ['fr'], 'fr', 'fr');

        self::assertTrue($resolved['hasPersistedEntries']);
        $primary = $service->filterPrimaryVisible($resolved['entries']);
        $all = $service->filterAllVisible($resolved['entries']);
        self::assertCount(0, $primary);
        self::assertCount(1, $all);
        self::assertSame('Phone training', $all[0]['title']);
        self::assertSame(
            CertificationContract::CERTIFICATION_PROOF_PDF_PATH_PREFIX.'certification-proof-test.pdf',
            $all[0]['proofPdfPath'] ?? null
        );
        self::assertCount(1, $resolved['entriesFull']);
        self::assertTrue($resolved['entriesFull'][0]['hiddenOnPrimary']);
    }
}
