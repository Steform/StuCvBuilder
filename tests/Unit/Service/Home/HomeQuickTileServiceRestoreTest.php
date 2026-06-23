<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Home;

use App\Entity\HomeCustomization;
use App\Entity\HomeQuickTile;
use App\Repository\HomeQuickTileRepository;
use App\Service\Home\HomeCustomizationService;
use App\Service\Home\HomeQuickTileLabelFormatter;
use App\Service\Home\HomeQuickTileLinkValidator;
use App\Service\Home\HomeQuickTileService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @brief Unit tests for backup restore behavior in {@see HomeQuickTileService}.
 */
final class HomeQuickTileServiceRestoreTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;

    private HomeQuickTileRepository&MockObject $quickTileRepository;

    private HomeQuickTileService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->quickTileRepository = $this->createMock(HomeQuickTileRepository::class);

        $this->service = new HomeQuickTileService(
            $this->entityManager,
            $this->createMock(HomeCustomizationService::class),
            $this->quickTileRepository,
            $this->createMock(HomeQuickTileLinkValidator::class),
            new HomeQuickTileLabelFormatter(),
            sys_get_temp_dir(),
        );
    }

    /**
     * @brief replaceAllFromBackup must use scoped DQL delete and never flush the unit of work.
     *
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function testReplaceAllFromBackupDoesNotFlushAndDeletesViaRepository(): void
    {
        $home = new HomeCustomization();
        $existing = new HomeQuickTile();
        $existing->setIconRelativePath('images/home/dashboard.svg');
        $home->addQuickTile($existing);

        $this->quickTileRepository
            ->expects(self::once())
            ->method('findAllOrdered')
            ->with($home)
            ->willReturn([$existing]);

        $this->quickTileRepository
            ->expects(self::once())
            ->method('deleteAllForCustomization')
            ->with($home)
            ->willReturn(1);

        $this->entityManager
            ->expects(self::never())
            ->method('flush');

        $this->entityManager
            ->method('contains')
            ->with($existing)
            ->willReturn(true);

        $this->entityManager
            ->expects(self::once())
            ->method('detach')
            ->with($existing);

        $this->service->replaceAllFromBackup($home, []);
    }
}
