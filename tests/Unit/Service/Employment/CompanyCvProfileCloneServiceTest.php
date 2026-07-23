<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Employment;

use App\Employment\CompanyCvContentMode;
use App\Entity\CvProfile;
use App\Entity\TrackedCompany;
use App\Exception\Employment\CompanyCvProfileCloneException;
use App\Repository\CvProfileRepository;
use App\Service\Cv\ExperienceContract;
use App\Service\Employment\CompanyCvProfileCloneService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class CompanyCvProfileCloneServiceTest extends TestCase
{
    /**
     * @brief Switching to custom creates a company-bound profile from the global snapshot.
     *
     * @return void
     * @date 2026-07-23
     * @author Stephane H.
     */
    public function testSwitchToCustomCreatesCloneFromGlobal(): void
    {
        $company = new TrackedCompany('ABCDEFGHIJKL', 'Acme');
        $global = new CvProfile('Global CV', '{"experienceEntriesByLocale":{"fr":[]}}');

        $repo = $this->createMock(CvProfileRepository::class);
        $repo->method('findOneForCompany')->willReturn(null);
        $repo->method('findGlobal')->willReturn($global);

        $persisted = null;
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::atLeastOnce())->method('persist')->willReturnCallback(
            static function (object $entity) use (&$persisted): void {
                $persisted = $entity;
            }
        );
        $em->method('flush');

        $projectDir = $this->makeWritableProjectDir();
        $service = new CompanyCvProfileCloneService($em, $repo, new NullLogger(), $projectDir);
        $clone = $service->switchToCustom($company);

        self::assertSame(CompanyCvContentMode::CUSTOM, $company->getCvContentMode());
        self::assertInstanceOf(CvProfile::class, $clone);
        self::assertSame($company, $clone->getTrackedCompany());
        self::assertSame($persisted, $clone);
    }

    /**
     * @brief Switching to synced removes the company profile and resets the mode.
     *
     * @return void
     * @date 2026-07-23
     * @author Stephane H.
     */
    public function testSwitchToSyncedRemovesClone(): void
    {
        $company = new TrackedCompany('ABCDEFGHIJKL', 'Acme');
        $company->setCvContentMode(CompanyCvContentMode::CUSTOM);
        $clone = new CvProfile('Acme', '{}');
        $clone->setTrackedCompany($company);

        $repo = $this->createMock(CvProfileRepository::class);
        $repo->method('findOneForCompany')->willReturn($clone);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('remove')->with($clone);
        $em->expects(self::atLeastOnce())->method('flush');

        $service = new CompanyCvProfileCloneService($em, $repo, new NullLogger(), sys_get_temp_dir());
        $service->switchToSynced($company);

        self::assertSame(CompanyCvContentMode::SYNCED, $company->getCvContentMode());
    }

    /**
     * @brief Clone copies purgeable experience logos under the company asset tree.
     *
     * @return void
     * @date 2026-07-23
     * @author Stephane H.
     */
    public function testCreateCloneCopiesCustomExperienceLogo(): void
    {
        $projectDir = $this->makeWritableProjectDir();
        $relative = ExperienceContract::EXPERIENCE_LOGO_PATH_PREFIX.'experience-logo-test.webp';
        $source = $projectDir.'/public/'.$relative;
        self::assertTrue(is_dir(dirname($source)) || mkdir(dirname($source), 0775, true));
        self::assertNotFalse(file_put_contents($source, 'webp-bytes'));

        $company = new TrackedCompany('ABCDEFGHIJKL', 'Acme');
        $globalPayload = [
            ExperienceContract::KEY_ENTRIES_BY_LOCALE => [
                'fr' => [[
                    'id' => '11111111-1111-4111-8111-111111111111',
                    'sortOrder' => 0,
                    'title' => 'Dev',
                    'companyName' => 'Acme',
                    'companyLogoPath' => $relative,
                    'startDate' => '2020-01',
                    'endDate' => '',
                    'isCurrent' => true,
                    'location' => '',
                    'hideCompanyName' => false,
                    'isPrimary' => true,
                    'detailHtml' => '<p>x</p>',
                    'companyWebsiteUrl' => '',
                ]],
            ],
        ];
        $global = new CvProfile('Global CV', (string) json_encode($globalPayload));

        $repo = $this->createMock(CvProfileRepository::class);
        $repo->method('findOneForCompany')->willReturn(null);
        $repo->method('findGlobal')->willReturn($global);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('persist');
        $em->method('flush');

        $service = new CompanyCvProfileCloneService($em, $repo, new NullLogger(), $projectDir);
        $clone = $service->createCloneFromGlobal($company);
        $decoded = json_decode($clone->getContentJson(), true);
        self::assertIsArray($decoded);

        $clonedPath = $decoded[ExperienceContract::KEY_ENTRIES_BY_LOCALE]['fr'][0]['companyLogoPath'] ?? null;
        self::assertSame(
            ExperienceContract::EXPERIENCE_LOGO_PATH_PREFIX.'company/ABCDEFGHIJKL/experience-logo-test.webp',
            $clonedPath
        );
        self::assertFileExists($projectDir.'/public/'.$clonedPath);
    }

    /**
     * @brief Missing purgeable source asset must abort the clone with an exception.
     *
     * @return void
     * @date 2026-07-23
     * @author Stephane H.
     */
    public function testCreateCloneFailsWhenSourceAssetMissing(): void
    {
        $projectDir = $this->makeWritableProjectDir();
        $relative = ExperienceContract::EXPERIENCE_LOGO_PATH_PREFIX.'experience-logo-missing.webp';

        $company = new TrackedCompany('ABCDEFGHIJKL', 'Acme');
        $globalPayload = [
            ExperienceContract::KEY_ENTRIES_BY_LOCALE => [
                'fr' => [[
                    'id' => '11111111-1111-4111-8111-111111111111',
                    'sortOrder' => 0,
                    'title' => 'Dev',
                    'companyName' => 'Acme',
                    'companyLogoPath' => $relative,
                    'startDate' => '2020-01',
                    'endDate' => '',
                    'isCurrent' => true,
                    'location' => '',
                    'hideCompanyName' => false,
                    'isPrimary' => true,
                    'detailHtml' => '<p>x</p>',
                    'companyWebsiteUrl' => '',
                ]],
            ],
        ];
        $global = new CvProfile('Global CV', (string) json_encode($globalPayload));

        $repo = $this->createMock(CvProfileRepository::class);
        $repo->method('findOneForCompany')->willReturn(null);
        $repo->method('findGlobal')->willReturn($global);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');

        $service = new CompanyCvProfileCloneService($em, $repo, new NullLogger(), $projectDir);

        $this->expectException(CompanyCvProfileCloneException::class);
        $this->expectExceptionMessage('source asset missing');
        $service->createCloneFromGlobal($company);
    }

    /**
     * @brief Build a temporary project root with a public/ directory.
     *
     * @return string Absolute project directory.
     */
    private function makeWritableProjectDir(): string
    {
        $projectDir = sys_get_temp_dir().'/stucv-clone-'.bin2hex(random_bytes(4));
        self::assertTrue(mkdir($projectDir.'/public', 0775, true));

        return $projectDir;
    }
}
