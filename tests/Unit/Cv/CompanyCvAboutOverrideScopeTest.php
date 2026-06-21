<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cv;

use App\Cv\CompanyCvAboutOverrideScope;
use App\Service\Cv\AboutPresentationContract;
use PHPUnit\Framework\TestCase;

final class CompanyCvAboutOverrideScopeTest extends TestCase
{
    /**
     * @brief Merge replaces About keys on base payload.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testMergeIntoPayloadReplacesAboutKeys(): void
    {
        $base = [
            'pageTitleByLocale' => ['fr' => 'Global'],
            AboutPresentationContract::KEY_HTML_BY_LOCALE => ['fr' => '<p>Global</p>'],
        ];
        $override = [
            AboutPresentationContract::KEY_HTML_BY_LOCALE => ['fr' => '<p>Acme</p>'],
        ];

        $merged = CompanyCvAboutOverrideScope::mergeIntoPayload($base, $override);

        self::assertSame('<p>Acme</p>', $merged[AboutPresentationContract::KEY_HTML_BY_LOCALE]['fr']);
        self::assertSame('Global', $merged['pageTitleByLocale']['fr']);
    }
}
