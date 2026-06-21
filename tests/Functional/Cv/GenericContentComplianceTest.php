<?php

declare(strict_types=1);

namespace App\Tests\Functional\Cv;

use App\Entity\CvProfile;
use App\Repository\CvProfileRepository;
use App\Service\Cv\CvResolverService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @brief Permanent zero-legacy guardrails for translations, PHP URLs, and virgin CV resolution.
 * @date 2026-06-21
 * @author Stephane H.
 */
final class GenericContentComplianceTest extends KernelTestCase
{
    /** @var list<string> */
    private const TRANSLATION_LEGACY_MARKERS = [
        'HIRT',
        'Steform',
        'StuSlider',
        'stuslider',
        'CKELPROCESS',
        'ROLE_SHARE',
        'files_tile:',
        'files_cloud:',
        'theme_files:',
        'public-file-landing',
        'public-folder-landing',
        'isPublicSharePath',
        '/p/…',
    ];

    /** @var list<string> */
    private const FORBIDDEN_PUBLIC_ASSET_FRAGMENTS = [
        'public-file-landing',
        'public-folder-landing',
        'media-preview.js',
        'media-preview.css',
    ];

    /** @var list<string> */
    private const FORBIDDEN_LEGACY_URL_FRAGMENTS = [
        'github.com/Steform',
        'ckelprocess.fr',
    ];

    /** @var list<string> */
    private const RESOLUTION_LEGACY_MARKERS = [
        'Steform',
        'StuSlider',
        'CKELPROCESS',
        'Situation COBOL',
        'chip_cobol',
    ];

    /**
     * @brief Project root directory.
     *
     * @param void No input parameter.
     * @return string Absolute project root path.
     * @date 2026-06-21
     * @author Stephane H.
     */
    private static function projectRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    /**
     * @brief Shipped translation files must not contain legacy owner or demo business markers.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testTranslationFilesHaveNoLegacyBusinessMarkers(): void
    {
        $translationsDir = self::projectRoot().'/translations';
        self::assertDirectoryExists($translationsDir);

        $violations = [];
        $iterator = new \DirectoryIterator($translationsDir);

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile() || $fileInfo->getExtension() !== 'yaml') {
                continue;
            }

            $contents = @file_get_contents($fileInfo->getPathname()) ?: '';
            $relativePath = 'translations/'.$fileInfo->getFilename();

            foreach (self::TRANSLATION_LEGACY_MARKERS as $marker) {
                if (stripos($contents, $marker) === false) {
                    continue;
                }

                $violations[] = sprintf('%s contains %s', $relativePath, $marker);
            }

            if (preg_match('/\bCOBOL\b/u', $contents) === 1) {
                foreach (preg_split('/\R/u', $contents) ?: [] as $lineNumber => $line) {
                    if (!preg_match('/\bCOBOL\b/u', $line)) {
                        continue;
                    }

                    if ($this->isAllowedTranslationCobolLine($line)) {
                        continue;
                    }

                    $violations[] = sprintf('%s:%d contains COBOL', $relativePath, $lineNumber + 1);
                }
            }
        }

        self::assertSame([], $violations, "Legacy business markers found in translations:\n".implode("\n", $violations));
    }

    /**
     * @brief Production PHP must not ship hard-coded legacy demo URLs outside approved remapping services.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testProductionPhpHasNoLegacyDemoUrls(): void
    {
        $srcRoot = self::projectRoot().'/src';
        $violations = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($srcRoot, \FilesystemIterator::SKIP_DOTS)
        );

        /** @var \SplFileInfo $fileInfo */
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile() || strtolower($fileInfo->getExtension()) !== 'php') {
                continue;
            }

            $contents = @file_get_contents($fileInfo->getPathname()) ?: '';
            foreach (self::FORBIDDEN_LEGACY_URL_FRAGMENTS as $fragment) {
                if (!str_contains($contents, $fragment)) {
                    continue;
                }

                if ($this->isAllowedLegacyUrlOccurrence($fileInfo->getPathname(), $contents)) {
                    continue;
                }

                $violations[] = sprintf('%s contains %s', $this->relativeProjectPath($fileInfo->getPathname()), $fragment);
            }
        }

        self::assertSame([], $violations, "Legacy demo URLs found in src/:\n".implode("\n", $violations));
    }

    /**
     * @brief Removed file-sharing module assets must not reappear under public/.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testPublicAssetsHaveNoRemovedFileSharingModuleFiles(): void
    {
        $publicRoot = self::projectRoot().'/public';
        $violations = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($publicRoot, \FilesystemIterator::SKIP_DOTS)
        );

        /** @var \SplFileInfo $fileInfo */
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            $relativePath = $this->relativeProjectPath($fileInfo->getPathname());
            foreach (self::FORBIDDEN_PUBLIC_ASSET_FRAGMENTS as $fragment) {
                if (str_contains($relativePath, $fragment)) {
                    $violations[] = sprintf('%s matches removed module asset %s', $relativePath, $fragment);
                }
            }
        }

        self::assertSame([], $violations, "Removed file-sharing assets found:\n".implode("\n", $violations));
    }

    /**
     * @brief Virgin persisted profile must expose only placeholder business titles or empty section lists.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testVirginProfileResolutionUsesOnlyPlaceholderBusinessContent(): void
    {
        self::bootKernel();

        $profile = new CvProfile('My CV', '{}');
        $repository = $this->createMock(CvProfileRepository::class);
        $repository->method('findOneBy')->willReturn($profile);
        $repository->method('count')->with([])->willReturn(1);
        static::getContainer()->set(CvProfileRepository::class, $repository);

        /** @var CvResolverService $resolver */
        $resolver = static::getContainer()->get(CvResolverService::class);
        /** @var TranslatorInterface $translator */
        $translator = static::getContainer()->get(TranslatorInterface::class);

        $resolved = $resolver->resolve('', 'fr');
        $encoded = json_encode($resolved, JSON_THROW_ON_ERROR);

        self::assertTrue($resolved['isPlaceholderMode']);
        foreach (self::RESOLUTION_LEGACY_MARKERS as $marker) {
            self::assertStringNotContainsString($marker, $encoded, 'Legacy marker leaked into virgin profile resolution.');
        }

        $allowedExperienceTitles = [$translator->trans('cv.placeholder.experience.title', [], 'messages', 'fr')];
        $allowedExperienceCompanies = [$translator->trans('cv.placeholder.experience.company', [], 'messages', 'fr')];
        $allowedCertificationTitles = [$translator->trans('cv.placeholder.certification.title', [], 'messages', 'fr')];
        $allowedCertificationProviders = [$translator->trans('cv.placeholder.certification.provider', [], 'messages', 'fr')];

        foreach ($this->normalizeEntryList($resolved['experienceEntries'] ?? null) as $entry) {
            self::assertContains($entry['title'], $allowedExperienceTitles);
            self::assertContains($entry['companyName'], $allowedExperienceCompanies);
        }

        foreach ($this->normalizeEntryList($resolved['certificationEntries'] ?? null) as $entry) {
            self::assertContains($entry['title'], $allowedCertificationTitles);
            self::assertContains($entry['providerName'], $allowedCertificationProviders);
        }

        self::assertSame([], $this->normalizeEntryList($resolved['flagshipProjects'] ?? null));

        $skillsTree = is_array($resolved['skillsTreePrimary'] ?? null) ? $resolved['skillsTreePrimary'] : [];
        $categories = is_array($skillsTree['categories'] ?? null) ? $skillsTree['categories'] : [];
        self::assertSame([], $categories);
    }

    /**
     * @brief Allow COBOL only in admin DSL syntax help examples, not in visitor-facing situation copy.
     *
     * @param string $line One YAML line from a translation file.
     * @return bool True when COBOL is part of an approved admin help example.
     * @date 2026-06-21
     * @author Stephane H.
     */
    private function isAllowedTranslationCobolLine(string $line): bool
    {
        return str_contains($line, 'field_focus_chips_dsl_help');
    }

    /**
     * @brief Allow legacy URL fragments inside explicit deprecated remapping allowlists.
     *
     * @param string $absolutePath Inspected PHP file path.
     * @param string $contents File contents.
     * @return bool True when the file is an approved deprecated remapping service.
     * @date 2026-06-21
     * @author Stephane H.
     */
    private function isAllowedLegacyUrlOccurrence(string $absolutePath, string $contents): bool
    {
        $relativePath = $this->relativeProjectPath($absolutePath);

        return str_contains($relativePath, 'src/Service/Cv/CvLegacySeedContentService.php')
            && str_contains($contents, 'DEPRECATED_');
    }

    /**
     * @brief Normalize resolved entry lists for assertions.
     *
     * @param mixed $entries Resolved entries from CvResolverService.
     * @return list<array<string, mixed>>
     * @date 2026-06-21
     * @author Stephane H.
     */
    private function normalizeEntryList(mixed $entries): array
    {
        if (!is_array($entries)) {
            return [];
        }

        $normalized = [];
        foreach ($entries as $entry) {
            if (is_array($entry)) {
                $normalized[] = $entry;
            }
        }

        return $normalized;
    }

    /**
     * @brief Convert an absolute path to a project-relative path for readable assertions.
     *
     * @param string $absolutePath Absolute filesystem path.
     * @return string Project-relative path.
     * @date 2026-06-21
     * @author Stephane H.
     */
    private function relativeProjectPath(string $absolutePath): string
    {
        $root = self::projectRoot().'/';

        return str_starts_with($absolutePath, $root)
            ? substr($absolutePath, strlen($root))
            : $absolutePath;
    }
}
