<?php

declare(strict_types=1);

namespace App\Tests\Functional\Cv;

use App\Entity\CvProfile;
use App\Repository\CvProfileRepository;
use App\Service\Cv\CvResolverService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;

/**
 * @brief Guardrails against reintroducing cv-symfony8 personalization seeds in shipped code.
 * @date 2026-06-21
 * @author Stephane H.
 */
final class CvGenericityComplianceTest extends KernelTestCase
{
    /** @var list<string> */
    private const LEGACY_SEED_MARKERS = [
        'Steform',
        'StuSlider',
        'stuslider-demo',
        'entry_funmooc',
        'CKELPROCESS',
        'chip_cobol',
        'Situation COBOL',
    ];

    /** @var list<string> */
    private const SCAN_RELATIVE_PATHS = [
        'src',
        'templates',
        'public/js',
    ];

    /**
     * @brief Project root directory.
     *
     * @return string Absolute project root path.
     * @date 2026-06-21
     * @author Stephane H.
     */
    private static function projectRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    /**
     * @brief Legacy seed markers must not appear in shipped source, templates, or public JS.
     *
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testSourceTreeHasNoLegacyPersonalizationSeeds(): void
    {
        $violations = [];

        foreach (self::SCAN_RELATIVE_PATHS as $relativePath) {
            $absolutePath = self::projectRoot().'/'.$relativePath;
            if (!is_dir($absolutePath)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($absolutePath, \FilesystemIterator::SKIP_DOTS)
            );

            /** @var \SplFileInfo $fileInfo */
            foreach ($iterator as $fileInfo) {
                if (!$fileInfo->isFile()) {
                    continue;
                }

                $extension = strtolower($fileInfo->getExtension());
                if (!in_array($extension, ['php', 'twig', 'js', 'yaml', 'yml'], true)) {
                    continue;
                }

                $contents = @file_get_contents($fileInfo->getPathname()) ?: '';
                foreach (self::LEGACY_SEED_MARKERS as $marker) {
                    if (!str_contains($contents, $marker)) {
                        continue;
                    }

                    if ($this->isAllowedDeprecatedMarkerOccurrence($fileInfo->getPathname(), $marker, $contents)) {
                        continue;
                    }

                    $violations[] = sprintf('%s contains %s', $this->relativeProjectPath($fileInfo->getPathname()), $marker);
                }
            }
        }

        self::assertSame([], $violations, "Legacy personalization seeds found:\n".implode("\n", $violations));
    }

    /**
     * @brief Public assets must not ship files named after the legacy owner.
     *
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testPublicAssetsHaveNoPersonalNamedFiles(): void
    {
        $publicRoot = self::projectRoot().'/public';
        self::assertDirectoryExists($publicRoot);

        $matches = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($publicRoot, \FilesystemIterator::SKIP_DOTS)
        );

        /** @var \SplFileInfo $fileInfo */
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            $basename = strtolower($fileInfo->getBasename());
            if (str_contains($basename, 'hirt') || str_contains($basename, 'stephane')) {
                $matches[] = $this->relativeProjectPath($fileInfo->getPathname());
            }
        }

        self::assertSame([], $matches, "Personal-named public assets found:\n".implode("\n", $matches));
    }

    /**
     * @brief Virgin persisted profile must resolve placeholder content without legacy business seeds.
     *
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testVirginProfileResolutionHasNoLegacyBusinessContent(): void
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
        $encoded = json_encode($resolved, JSON_THROW_ON_ERROR);

        self::assertTrue($resolved['isPlaceholderMode']);
        foreach (self::LEGACY_SEED_MARKERS as $marker) {
            self::assertStringNotContainsString($marker, $encoded, 'Legacy marker leaked into virgin profile resolution.');
        }
    }

    /**
     * @brief Public layouts must expose generic SEO metadata and discovery files.
     *
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testPublicSeoArtifactsArePresent(): void
    {
        $base = @file_get_contents(self::projectRoot().'/templates/base.html.twig') ?: '';
        self::assertStringContainsString('_site_seo_meta.html.twig', $base);
        self::assertStringContainsString('site_seo_meta_description', $base);
        self::assertStringContainsString('site_seo_open_graph_enabled', $base);

        $seoPartial = @file_get_contents(self::projectRoot().'/templates/components/_site_seo_meta.html.twig') ?: '';
        self::assertStringContainsString('{% if seoOpenGraphEnabled and seoRobots == \'\' %}', $seoPartial);
        self::assertStringContainsString('rel="canonical"', $seoPartial);
        self::assertStringContainsString('hreflang', $seoPartial);
        self::assertStringContainsString('name="robots"', $seoPartial);
        self::assertStringContainsString('og:image', $seoPartial);
        self::assertStringContainsString('og:locale', $seoPartial);
        self::assertStringContainsString('application/ld+json', @file_get_contents(self::projectRoot().'/templates/components/_site_structured_data.html.twig') ?: '');
        self::assertFileExists(self::projectRoot().'/src/Service/Site/SiteStructuredDataService.php');
        self::assertFileExists(self::projectRoot().'/public/css/cv-public-static-bundle.css');

        $cvShow = @file_get_contents(self::projectRoot().'/templates/cv/show.html.twig') ?: '';
        self::assertStringContainsString('cv-public-static-bundle.css', $cvShow);
        self::assertStringContainsString('site_seo_cv_page_title', $cvShow);
        self::assertStringContainsString('site_seo_cv_meta_description', $cvShow);
        self::assertStringContainsString('<h1 class="visually-hidden">{{ resolvedPageTitle }}</h1>', $cvShow);

        $cvAccess = @file_get_contents(self::projectRoot().'/templates/base_cv_access.html.twig') ?: '';
        self::assertStringContainsString('content="noindex, follow"', $cvAccess);

        $homeLanding = @file_get_contents(self::projectRoot().'/templates/layouts/home_landing.html.twig') ?: '';
        self::assertStringContainsString('site_seo_meta_description', $homeLanding);

        $robots = @file_get_contents(self::projectRoot().'/public/robots.txt') ?: '';
        self::assertStringContainsString('Sitemap: /sitemap.xml', $robots);
        self::assertStringContainsString('Disallow: /admin/', $robots);

        self::bootKernel();
        /** @var RouterInterface $router */
        $router = static::getContainer()->get(RouterInterface::class);
        self::assertSame('/sitemap.xml', $router->generate('app_sitemap'));
        self::assertFileExists(self::projectRoot().'/src/Service/Site/SiteSitemapService.php');

        self::assertFileExists(self::projectRoot().'/src/Service/Cv/CvLegacySeedContentService.php');
        self::assertFileExists(self::projectRoot().'/migrations/Version20260621190000.php');
    }

    /**
     * @brief Allow deprecated remapping constants that intentionally mention legacy paths or intro copies.
     *
     * @param string $absolutePath Inspected file path.
     * @param string $marker Legacy marker being scanned.
     * @param string $contents File contents.
     * @return bool True when the occurrence is an approved deprecated remapping reference.
     * @date 2026-06-21
     * @author Stephane H.
     */
    private function isAllowedDeprecatedMarkerOccurrence(string $absolutePath, string $marker, string $contents): bool
    {
        $relativePath = $this->relativeProjectPath($absolutePath);

        if (str_contains($relativePath, 'src/Service/Home/HomeCustomizationService.php')
            && str_contains($contents, 'DEPRECATED_LEGACY_INTRO_TEXTS')
        ) {
            return true;
        }

        if (str_contains($relativePath, 'src/Service/Cv/CvLegacySeedContentService.php')
            && str_contains($contents, 'DEPRECATED_')
        ) {
            return true;
        }

        if (str_contains($relativePath, 'src/Service/Cv/CvAboutProfileSettingsService.php')
            && str_contains($contents, 'DEPRECATED_PROFILE_PHOTO_PATHS')
            && in_array($marker, ['Stephane-HIRT.webp', 'hirt-stephane.webp'], true)
        ) {
            return true;
        }

        if (str_contains($relativePath, 'src/Service/Home/HomeCustomizationService.php')
            && str_contains($contents, 'DEPRECATED_HOME_IMAGE_PATHS')
            && str_contains($marker, 'hirt')
        ) {
            return true;
        }

        return false;
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
