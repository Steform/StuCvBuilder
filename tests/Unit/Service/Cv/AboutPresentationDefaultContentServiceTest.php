<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Cv;

use App\Service\Cv\AboutPresentationDefaultContentService;
use App\Service\RichText\RichHtmlSanitizer;
use PHPUnit\Framework\TestCase;

/**
 * @brief Unit tests for default About presentation HTML skeleton.
 *
 * @date 2026-06-01
 * @author Stephane H.
 */
final class AboutPresentationDefaultContentServiceTest extends TestCase
{
    /**
     * @brief Default About skeleton exposes identity tokens and action placeholders.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testDefaultPresentationHtmlExposesIdentityTokens(): void
    {
        $service = new AboutPresentationDefaultContentService(new RichHtmlSanitizer());
        $html = $service->buildSanitizedHtmlForLocale('fr');

        self::assertStringContainsString('[[cv.display_name]]', $html);
        self::assertStringContainsString('[[cv.sought_position]]', $html);
        self::assertStringContainsString('[[cv.pdf]]', $html);
        self::assertStringContainsString('[[cv.learn_more]]', $html);
    }
}
