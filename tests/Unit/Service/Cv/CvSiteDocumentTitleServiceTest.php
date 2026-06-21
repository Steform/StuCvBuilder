<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Cv;

use App\Entity\CvProfile;
use App\Repository\CvProfileRepository;
use App\Service\Cv\CvPublicIdentityContract;
use App\Tests\Support\CvPublicIdentityPlaceholderServiceFactory;
use App\Service\Cv\CvSiteDocumentTitleService;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @brief Unit tests for {@see CvSiteDocumentTitleService}.
 * @date 2026-05-16
 * @author Stephane H.
 */
final class CvSiteDocumentTitleServiceTest extends TestCase
{
    /**
     * @brief Owner prefix must use configured display name from CV public identity.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-16
     * @author Stephane H.
     */
    public function testResolveOwnerPrefixUsesCvDisplayName(): void
    {
        $profile = new CvProfile('default', json_encode([
            CvPublicIdentityContract::KEY_ROOT => [
                CvPublicIdentityContract::FIELD_DISPLAY_NAME => 'Alex Example',
            ],
        ], JSON_THROW_ON_ERROR));

        $repository = $this->createMock(CvProfileRepository::class);
        $repository->method('findOneBy')->willReturn($profile);

        $translator = $this->createMock(TranslatorInterface::class);
        $service = new CvSiteDocumentTitleService(
            $repository,
            CvPublicIdentityPlaceholderServiceFactory::createWithTranslator($translator),
        );

        self::assertSame('Alex Example', $service->resolveOwnerPrefix('fr'));
    }

    /**
     * @brief Empty display name must fall back to localized placeholder label.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-16
     * @author Stephane H.
     */
    public function testResolveOwnerPrefixFallsBackWhenDisplayNameMissing(): void
    {
        $profile = new CvProfile('default', json_encode([
            CvPublicIdentityContract::KEY_ROOT => [],
        ], JSON_THROW_ON_ERROR));

        $repository = $this->createMock(CvProfileRepository::class);
        $repository->method('findOneBy')->willReturn($profile);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')
            ->with('cv.about.presentation_default.fallback_display_name', [], 'messages', 'fr')
            ->willReturn('Votre nom');

        $service = new CvSiteDocumentTitleService(
            $repository,
            CvPublicIdentityPlaceholderServiceFactory::createWithTranslator($translator),
        );

        self::assertSame('Votre nom', $service->resolveOwnerPrefix('fr'));
    }
}
