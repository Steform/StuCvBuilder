<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Cv;

use App\Entity\CvProfile;
use App\Repository\CvProfileRepository;
use App\Service\Cv\CvResolverService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * @brief Integration tests for empty CV resolution in {@see CvResolverService}.
 * @date 2026-05-16
 * @author Stephane H.
 */
final class CvResolverServiceAboutPlaceholderTest extends KernelTestCase
{
    /**
     * @brief Missing CvProfile must return an empty resolved payload.
     *
     * @return void
     * @date 2026-05-16
     * @author Stephane H.
     */
    public function testResolveReturnsEmptyPayloadWhenNoProfileExists(): void
    {
        self::bootKernel();
        $repository = $this->createMock(CvProfileRepository::class);
        $repository->method('findOneBy')->willReturn(null);
        $repository->method('count')->with([])->willReturn(0);
        static::getContainer()->set(CvProfileRepository::class, $repository);

        /** @var CvResolverService $resolver */
        $resolver = static::getContainer()->get(CvResolverService::class);
        $resolved = $resolver->resolve('', 'fr');

        self::assertSame(0, $resolved['cvProfileId']);
        self::assertSame('', $resolved['title']);
        self::assertFalse($resolved['payload']['publicNavVisibility']['skills']);
        self::assertTrue($resolved['payload']['publicNavVisibility']['about']);
        self::assertFalse($resolved['isPlaceholderMode']);
    }

    /**
     * @brief Existing virgin CvProfile keeps placeholder mode and generic page title.
     *
     * @return void
     * @date 2026-05-16
     * @author Stephane H.
     */
    public function testResolveUsesPlaceholderModeWhenProfileExistsWithoutSections(): void
    {
        self::bootKernel();
        $profile = new CvProfile('My CV', '{}');
        $repository = $this->createMock(CvProfileRepository::class);
        $repository->method('findOneBy')->willReturn($profile);
        $repository->method('count')->with([])->willReturn(1);
        static::getContainer()->set(CvProfileRepository::class, $repository);

        /** @var CvResolverService $resolver */
        $resolver = static::getContainer()->get(CvResolverService::class);
        $resolved = $resolver->resolve('', 'fr');

        self::assertTrue($resolved['isPlaceholderMode']);
        self::assertSame('CV à compléter', $resolved['title']);
        self::assertStringContainsString('Votre nom', (string) ($resolved['payload']['aboutPresentationHtml'] ?? ''));
    }
}
