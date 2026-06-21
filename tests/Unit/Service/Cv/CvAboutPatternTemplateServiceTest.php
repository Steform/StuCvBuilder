<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Cv;

use App\Service\Cv\CvAboutPatternTemplateService;
use App\Service\Security\SvgUploadSanitizer;
use PHPUnit\Framework\TestCase;

/**
 * @brief Unit tests for {@see CvAboutPatternTemplateService}.
 *
 * @date 2026-05-27
 * @author Stephane H.
 */
final class CvAboutPatternTemplateServiceTest extends TestCase
{
    /**
     * @brief Rendered SVG must map canonical grayscale tones to CSS variables.
     *
     * @return void
     * @date 2026-05-27
     * @author Stephane H.
     */
    public function testRenderTemplateMapsKnownGrayscalePalette(): void
    {
        $projectDir = $this->createProjectWithPattern([
            'fond-about-test.svg' => '<svg><path style="fill:#2d2d2d;stroke:#cbcbcb"/><path style="fill:#a1a1a1;stroke:#434343"/></svg>',
        ]);

        $service = new CvAboutPatternTemplateService($projectDir, new SvgUploadSanitizer());
        $rendered = $service->renderTemplate('fond-about-test');

        self::assertSame('fond-about-test', $rendered['patternId']);
        self::assertStringContainsString('class="cv-about__pattern-svg"', $rendered['svg']);
        self::assertStringContainsString('var(--cv-about-pattern-tone-1)', $rendered['svg']);
        self::assertStringContainsString('var(--cv-about-pattern-tone-4)', $rendered['svg']);
        self::assertSame([], $rendered['warnings']);
    }

    /**
     * @brief Unknown colors must remain in the SVG without admin warnings.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testRenderTemplateKeepsUnknownHexColorWithoutWarning(): void
    {
        $projectDir = $this->createProjectWithPattern([
            'fond-about-test.svg' => '<svg><path style="fill:#ff0000;stroke:#2d2d2d"/></svg>',
        ]);

        $service = new CvAboutPatternTemplateService($projectDir, new SvgUploadSanitizer());
        $rendered = $service->renderTemplate('fond-about-test');

        self::assertSame([], $rendered['warnings']);
        self::assertStringContainsString('#ff0000', $rendered['svg']);
        self::assertStringContainsString('var(--cv-about-pattern-tone-1)', $rendered['svg']);
    }

    /**
     * @brief Template discovery must return normalized ids sorted alphabetically.
     *
     * @return void
     * @date 2026-05-27
     * @author Stephane H.
     */
    public function testListPatternIdsDiscoversFilesUnderAboutDirectories(): void
    {
        $projectDir = $this->createProjectWithPattern([
            'fond-about-b.svg' => '<svg/>',
            'fond-about-a.svg' => '<svg/>',
        ]);

        $service = new CvAboutPatternTemplateService($projectDir, new SvgUploadSanitizer());
        self::assertSame(['fond-about-a', 'fond-about-b'], $service->listPatternIds());
    }

    /**
     * @brief Build temporary project tree with About pattern files.
     *
     * @param array<string, string> $files File name => SVG content.
     * @return string
     * @date 2026-05-27
     * @author Stephane H.
     */
    private function createProjectWithPattern(array $files): string
    {
        $projectDir = sys_get_temp_dir().'/cv-about-pattern-test-'.bin2hex(random_bytes(4));
        $patternDir = $projectDir.'/public/images/cv/about/patterns';
        mkdir($patternDir, 0777, true);

        foreach ($files as $name => $svg) {
            file_put_contents($patternDir.'/'.$name, $svg);
        }

        return $projectDir;
    }
}

