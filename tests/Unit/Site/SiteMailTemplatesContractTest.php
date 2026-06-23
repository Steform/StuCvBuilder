<?php

declare(strict_types=1);

namespace App\Tests\Unit\Site;

use App\Site\SiteMailTemplatesContract;
use PHPUnit\Framework\TestCase;

/**
 * @brief Unit tests for {@see SiteMailTemplatesContract}.
 */
final class SiteMailTemplatesContractTest extends TestCase
{
    /**
     * @brief Merge submitted mail template fields into normalized map.
     *
     * @return void
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function testMergeSubmittedPersistsTypeSenderAndLocaleFields(): void
    {
        $existing = SiteMailTemplatesContract::normalize(null);
        $merged = SiteMailTemplatesContract::mergeSubmitted($existing, [
            'totp' => [
                'from_email' => 'Custom@Example.COM',
                'from_name' => 'Custom Sender',
                'locales' => [
                    'fr' => [
                        'subject' => 'Objet TOTP',
                        'blocks' => [
                            'intro' => '<p>Intro <strong>test</strong></p>',
                        ],
                        'labels' => [
                            'brand' => 'Marque',
                        ],
                    ],
                ],
            ],
        ], ['fr', 'en']);

        self::assertSame('custom@example.com', $merged['totp']['fromEmail']);
        self::assertSame('Custom Sender', $merged['totp']['fromName']);
        self::assertSame('Objet TOTP', $merged['totp']['locales']['fr']['subject']);
        self::assertSame('<p>Intro <strong>test</strong></p>', $merged['totp']['locales']['fr']['blocks']['intro']);
        self::assertSame('Marque', $merged['totp']['locales']['fr']['labels']['brand']);
    }

    /**
     * @brief Encode and decode round-trip keeps normalized structure.
     *
     * @return void
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function testEncodeDecodeRoundTrip(): void
    {
        $templates = SiteMailTemplatesContract::normalize(null);
        $templates['invitation']['fromEmail'] = 'invite@example.com';
        $templates['invitation']['locales']['en']['subject'] = 'Invite subject';
        $templates['invitation']['locales']['en']['labels']['cta'] = 'Activate';

        $json = SiteMailTemplatesContract::encodeForStorage($templates);
        self::assertIsString($json);

        $decoded = SiteMailTemplatesContract::decodeFromStorage($json);
        self::assertSame('invite@example.com', $decoded['invitation']['fromEmail']);
        self::assertSame('Invite subject', $decoded['invitation']['locales']['en']['subject']);
        self::assertSame('Activate', $decoded['invitation']['locales']['en']['labels']['cta']);
    }

    /**
     * @brief Invalid from email is rejected by validator helper.
     *
     * @return void
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function testIsValidFromEmailRejectsInvalidAddress(): void
    {
        self::assertTrue(SiteMailTemplatesContract::isValidFromEmail(null));
        self::assertTrue(SiteMailTemplatesContract::isValidFromEmail(''));
        self::assertTrue(SiteMailTemplatesContract::isValidFromEmail('valid@example.com'));
        self::assertFalse(SiteMailTemplatesContract::isValidFromEmail('not-an-email'));
    }

    /**
     * @brief Recruiter visit template supports customizable recipient email.
     *
     * @return void
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function testMergeSubmittedPersistsRecruiterVisitToEmail(): void
    {
        $existing = SiteMailTemplatesContract::normalize(null);
        $merged = SiteMailTemplatesContract::mergeSubmitted($existing, [
            'recruiter_visit' => [
                'to_email' => 'Alerts@Example.COM',
            ],
        ], ['fr']);

        self::assertTrue(SiteMailTemplatesContract::supportsToEmail(SiteMailTemplatesContract::TYPE_RECRUITER_VISIT));
        self::assertSame('alerts@example.com', $merged['recruiter_visit']['toEmail']);
    }
}
