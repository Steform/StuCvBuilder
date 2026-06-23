<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Cv;

use App\Service\Cv\CvPublicIdentityContract;
use App\Tests\Support\CvPublicIdentityPlaceholderServiceFactory;
use PHPUnit\Framework\TestCase;

/**
 * @brief Unit tests for [[cv.*]] placeholder replacement after sanitization.
 * @date 2026-05-14
 * @author Stephane H.
 */
final class CvPublicIdentityPlaceholderServiceTest extends TestCase
{
    /**
     * @brief Whitelisted tokens must expand using identity and fixed clock.
     * @return void
     * @date 2026-05-14
     * @author Stephane H.
     */
    public function testReplacesKnownTokens(): void
    {
        $service = CvPublicIdentityPlaceholderServiceFactory::create();
        $now = new \DateTimeImmutable('2026-06-15 12:00:00', new \DateTimeZone('UTC'));
        $payload = [
            CvPublicIdentityContract::KEY_ROOT => [
                CvPublicIdentityContract::FIELD_DISPLAY_NAME => 'Ada',
                CvPublicIdentityContract::FIELD_CITY => 'Oslo',
                CvPublicIdentityContract::FIELD_REGION => 'Viken',
                CvPublicIdentityContract::FIELD_COUNTRY_BY_LOCALE => [
                    'en' => 'Norway',
                    'fr' => 'Norvege',
                ],
                CvPublicIdentityContract::FIELD_CAREER_START_YEAR => 1995,
                CvPublicIdentityContract::FIELD_BIRTH_DATE => '1990-03-10',
                CvPublicIdentityContract::FIELD_TAGLINE_BY_LOCALE => [
                    'en' => 'Hello EN',
                    'fr' => 'Bonjour FR',
                ],
            ],
        ];

        $html = '<p>[[cv.display_name]] [[cv.city]] [[cv.region]] [[cv.country]] [[cv.career_start_year]] [[cv.experience_years]] [[cv.age_years]] [[cv.tagline]] [[cv.document_year]]</p>';
        $out = $service->applyToHtml($html, $payload, 'en', ['en', 'fr'], 'fr', $now);

        self::assertStringContainsString('Ada', $out);
        self::assertStringContainsString('Oslo', $out);
        self::assertStringContainsString('Viken', $out);
        self::assertStringContainsString('Norway', $out);
        self::assertStringContainsString('1995', $out);
        self::assertStringContainsString('31', $out);
        self::assertStringContainsString('36', $out);
        self::assertStringContainsString('Hello EN', $out);
        self::assertStringContainsString('2026', $out);
        self::assertStringNotContainsString('[[cv.', $out);
    }

    /**
     * @brief `[[cv.sought_position]]` resolves from `soughtPositionByLocale` independently of `[[cv.country]]`.
     * @return void
     * @date 2026-05-23
     * @author Stephane H.
     */
    public function testSoughtPositionResolvesFromLocaleMap(): void
    {
        $service = CvPublicIdentityPlaceholderServiceFactory::create();
        $now = new \DateTimeImmutable('2026-01-01');
        $payload = [
            CvPublicIdentityContract::KEY_ROOT => [
                CvPublicIdentityContract::FIELD_COUNTRY_BY_LOCALE => [
                    'fr' => 'France',
                ],
                CvPublicIdentityContract::FIELD_SOUGHT_POSITION_BY_LOCALE => [
                    'fr' => 'Développeur Cobol',
                ],
            ],
        ];

        $countryOut = $service->applyToHtml('[[cv.country]]', $payload, 'fr', ['fr'], 'fr', $now);
        $soughtOut = $service->applyToHtml('[[cv.sought_position]]', $payload, 'fr', ['fr'], 'fr', $now);

        self::assertStringContainsString('France', $countryOut);
        self::assertStringContainsString('Développeur Cobol', $soughtOut);
        self::assertNotSame($countryOut, $soughtOut);
    }

    /**
     * @brief `[[cv.status]]` resolves from `statusByLocale` independently of other identity fields.
     *
     * @return void
     * @date 2026-05-23
     * @author Stephane H.
     */
    public function testStatusResolvesFromLocaleMap(): void
    {
        $service = CvPublicIdentityPlaceholderServiceFactory::create();
        $now = new \DateTimeImmutable('2026-01-01');
        $payload = [
            CvPublicIdentityContract::KEY_ROOT => [
                CvPublicIdentityContract::FIELD_SOUGHT_POSITION_BY_LOCALE => [
                    'fr' => 'Développeur Cobol',
                ],
                CvPublicIdentityContract::FIELD_STATUS_BY_LOCALE => [
                    'fr' => 'Disponible immédiatement',
                ],
            ],
        ];

        $soughtOut = $service->applyToHtml('[[cv.sought_position]]', $payload, 'fr', ['fr'], 'fr', $now);
        $statusOut = $service->applyToHtml('[[cv.status]]', $payload, 'fr', ['fr'], 'fr', $now);

        self::assertStringContainsString('Développeur Cobol', $soughtOut);
        self::assertStringContainsString('Disponible immédiatement', $statusOut);
        self::assertNotSame($soughtOut, $statusOut);
    }

    /**
     * @brief `[[cv.pdf]]` expands to a dark download button with localized label in every locale.
     * @return void
     * @date 2026-05-14
     * @author Stephane H.
     */
    public function testReplacesCvPdfTokenWithLocalizedLabel(): void
    {
        $service = CvPublicIdentityPlaceholderServiceFactory::create();
        $now = new \DateTimeImmutable('2026-01-01');
        $frOut = $service->applyToHtml('<p>[[cv.pdf]]</p>', [], 'fr', ['fr'], 'fr', $now);
        self::assertStringNotContainsString('[[cv.pdf]]', $frOut);
        self::assertStringContainsString('href="/cv/pdf"', $frOut);
        self::assertStringContainsString('cv-about__pdf-btn', $frOut);
        self::assertStringContainsString('bi-download', $frOut);
        self::assertStringContainsString('Télécharger le CV PDF', $frOut);

        $enOut = $service->applyToHtml('<p>[[cv.pdf]]</p>', [], 'en', ['en'], 'en', $now);
        self::assertStringContainsString('Download CV PDF', $enOut);
    }

    /**
     * @brief `[[cv.pdf]]` appends format query when employment format is present in payload.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testReplacesCvPdfTokenWithFormatQueryWhenPresent(): void
    {
        $service = CvPublicIdentityPlaceholderServiceFactory::create();
        $now = new \DateTimeImmutable('2026-01-01');
        $payload = [
            CvPublicIdentityContract::KEY_EMPLOYMENT_FORMAT_CODE => 'Ab3xY9kLm2Qp',
        ];
        $out = $service->applyToHtml('<p>[[cv.pdf]]</p>', $payload, 'fr', ['fr'], 'fr', $now);

        self::assertStringContainsString('href="/cv/pdf?format=Ab3xY9kLm2Qp"', $out);
    }

    /**
     * @brief `[[cv.lm_pdf]]` expands to a dark download button with localized label in every locale.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testReplacesCvLmPdfTokenWithLocalizedLabel(): void
    {
        $service = CvPublicIdentityPlaceholderServiceFactory::create();
        $now = new \DateTimeImmutable('2026-01-01');
        $frOut = $service->applyToHtml('<p>[[cv.lm_pdf]]</p>', [], 'fr', ['fr'], 'fr', $now);
        self::assertStringNotContainsString('[[cv.lm_pdf]]', $frOut);
        self::assertStringContainsString('href="/cv/lm-pdf"', $frOut);
        self::assertStringContainsString('cv-about__pdf-btn', $frOut);
        self::assertStringContainsString('bi-download', $frOut);
        self::assertStringContainsString('Télécharger la lettre de motivation PDF', $frOut);

        $enOut = $service->applyToHtml('<p>[[cv.lm_pdf]]</p>', [], 'en', ['en'], 'en', $now);
        self::assertStringContainsString('Download cover letter PDF', $enOut);
    }

    /**
     * @brief `[[cv.lm_pdf]]` is omitted when company format has no assigned LM PDF.
     *
     * @return void
     * @date 2026-06-17
     * @author Stephane H.
     */
    public function testHidesCvLmPdfTokenWhenCompanyFormatHasNoAssignedLm(): void
    {
        $service = CvPublicIdentityPlaceholderServiceFactory::create(false);
        $now = new \DateTimeImmutable('2026-01-01');
        $payload = [
            CvPublicIdentityContract::KEY_EMPLOYMENT_FORMAT_CODE => 'Ab3xY9kLm2Qp',
        ];
        $out = $service->applyToHtml('<p>[[cv.lm_pdf]]</p>', $payload, 'fr', ['fr'], 'fr', $now);

        self::assertSame('<p></p>', $out);
        self::assertStringNotContainsString('lm-pdf', $out);
    }

    /**
     * @brief `[[cv.learn_more]]` expands to a localized outline link to the Situation page.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testReplacesCvLearnMoreTokenWithLocalizedLabel(): void
    {
        $service = CvPublicIdentityPlaceholderServiceFactory::create();
        $now = new \DateTimeImmutable('2026-01-01');
        $frOut = $service->applyToHtml('<p>[[cv.learn_more]]</p>', [], 'fr', ['fr'], 'fr', $now);
        self::assertStringNotContainsString('[[cv.learn_more]]', $frOut);
        self::assertStringContainsString('href="/cv/situation"', $frOut);
        self::assertStringContainsString('cv-about__pdf-btn', $frOut);
        self::assertStringContainsString('bi-arrow-right-circle', $frOut);
        self::assertStringContainsString('En savoir plus', $frOut);

        $enOut = $service->applyToHtml('<p>[[cv.learn_more]]</p>', [], 'en', ['en'], 'en', $now);
        self::assertStringContainsString('Learn more', $enOut);
    }

    /**
     * @brief Placeholder markers with optional inner whitespace still expand on the public CV.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testReplacesCvLearnMoreTokenWithInnerWhitespace(): void
    {
        $service = CvPublicIdentityPlaceholderServiceFactory::create();
        $now = new \DateTimeImmutable('2026-01-01');
        $out = $service->applyToHtml('<p>[[ cv.learn_more ]]</p>', [], 'fr', ['fr'], 'fr', $now);

        self::assertStringNotContainsString('[[cv.learn_more]]', $out);
        self::assertStringContainsString('href="/cv/situation"', $out);
        self::assertStringContainsString('En savoir plus', $out);
    }

    /**
     * @brief `[[cv.tagline]]` inserts sanitized rich HTML (no double entity escaping).
     * @return void
     * @date 2026-05-14
     * @author Stephane H.
     */
    public function testTaglinePlaceholderEmitsSanitizedColoredHtml(): void
    {
        $service = CvPublicIdentityPlaceholderServiceFactory::create();
        $payload = [
            CvPublicIdentityContract::KEY_ROOT => [
                CvPublicIdentityContract::FIELD_TAGLINE_BY_LOCALE => [
                    'fr' => '<p><span style="color:#00ff00">Vert</span></p>',
                ],
            ],
        ];
        $now = new \DateTimeImmutable('2026-01-01');
        $out = $service->applyToHtml('[[cv.tagline]]', $payload, 'fr', ['fr'], 'fr', $now);
        self::assertStringContainsString('<span', $out);
        self::assertStringContainsString('color:', $out);
        self::assertStringNotContainsString('&lt;span', $out);
        self::assertStringNotContainsString('[[cv.tagline]]', $out);
    }

    /**
     * @brief Unknown `[[cv.*]]` patterns must remain unchanged.
     * @return void
     * @date 2026-05-14
     * @author Stephane H.
     */
    public function testLeavesUnknownTokensLiteral(): void
    {
        $service = CvPublicIdentityPlaceholderServiceFactory::create();
        $html = '<span>[[cv.unknown_token]]</span>';
        $out = $service->applyToHtml($html, [], 'fr', ['fr'], 'fr', new \DateTimeImmutable('2026-01-01'));

        self::assertSame($html, $out);
    }

    /**
     * @brief Malicious display name must not inject HTML after escaping.
     * @return void
     * @date 2026-05-14
     * @author Stephane H.
     */
    public function testEscapesMaliciousDisplayName(): void
    {
        $service = CvPublicIdentityPlaceholderServiceFactory::create();
        $payload = [
            CvPublicIdentityContract::KEY_ROOT => [
                CvPublicIdentityContract::FIELD_DISPLAY_NAME => '<script>alert(1)</script>',
            ],
        ];
        $out = $service->applyToHtml('[[cv.display_name]]', $payload, 'fr', ['fr'], 'fr', new \DateTimeImmutable());

        self::assertStringNotContainsString('<script>', $out);
        self::assertStringContainsString('&lt;script&gt;', $out);
    }

    /**
     * @brief Tagline must follow viewer locale with fallback to default then first non-empty.
     * @return void
     * @date 2026-05-14
     * @author Stephane H.
     */
    public function testTaglineLocaleFallback(): void
    {
        $service = CvPublicIdentityPlaceholderServiceFactory::create();
        $payload = [
            CvPublicIdentityContract::KEY_ROOT => [
                CvPublicIdentityContract::FIELD_TAGLINE_BY_LOCALE => [
                    'fr' => 'FR line',
                    'en' => '',
                    'de' => '',
                ],
            ],
        ];
        $now = new \DateTimeImmutable('2026-01-01');
        $enOut = $service->applyToHtml('[[cv.tagline]]', $payload, 'en', ['en', 'fr', 'de'], 'fr', $now);
        self::assertStringContainsString('FR line', $enOut);

        $frOut = $service->applyToHtml('[[cv.tagline]]', $payload, 'fr', ['en', 'fr', 'de'], 'fr', $now);
        self::assertStringContainsString('FR line', $frOut);
    }

    /**
     * @brief Per-locale maps must apply tagline per locale row.
     * @return void
     * @date 2026-05-14
     * @author Stephane H.
     */
    /**
     * @brief Missing identity must show localized name hint and placeholder age in About presentation.
     *
     * @return void
     * @date 2026-05-16
     * @author Stephane H.
     */
    public function testPresentationFallbacksWhenDisplayNameAndBirthDateAreMissing(): void
    {
        $service = CvPublicIdentityPlaceholderServiceFactory::create();
        $now = new \DateTimeImmutable('2026-06-15', new \DateTimeZone('UTC'));
        $html = '<h1>[[cv.display_name]], [[cv.age_years]] ans</h1>';

        $frOut = $service->applyToHtml($html, [], 'fr', ['fr'], 'fr', $now);
        self::assertStringContainsString('Votre nom', $frOut);
        self::assertStringContainsString('100', $frOut);
        self::assertStringNotContainsString('[[cv.display_name]]', $frOut);

        $enOut = $service->applyToHtml($html, [], 'en', ['en'], 'en', $now);
        self::assertStringContainsString('Your name', $enOut);
        self::assertStringContainsString('100', $enOut);
    }

    /**
     * @brief Missing or invalid career start year must show placeholder experience span in About presentation.
     *
     * @return void
     * @date 2026-05-18
     * @author Stephane H.
     */
    /**
     * @brief About header location line joins city, region, and localized country with ` | `.
     *
     * @return void
     * @date 2026-05-23
     * @author Stephane H.
     */
    public function testResolveAboutHeaderLocationLineJoinsCityRegionCountry(): void
    {
        $service = CvPublicIdentityPlaceholderServiceFactory::create();
        $payload = [
            CvPublicIdentityContract::KEY_ROOT => [
                CvPublicIdentityContract::FIELD_CITY => 'Paris',
                CvPublicIdentityContract::FIELD_REGION => 'IDF',
                CvPublicIdentityContract::FIELD_COUNTRY_BY_LOCALE => [
                    'en' => 'France',
                    'fr' => 'France',
                ],
            ],
        ];

        $line = $service->resolveAboutHeaderLocationLine($payload, 'en', ['en', 'fr'], 'fr');
        self::assertSame('Paris | IDF | France', $line);
    }

    /**
     * @brief About header location line omits empty country and still uses pipe separators.
     *
     * @return void
     * @date 2026-05-23
     * @author Stephane H.
     */
    public function testResolveAboutHeaderLocationLineOmitsEmptyCountry(): void
    {
        $service = CvPublicIdentityPlaceholderServiceFactory::create();
        $payload = [
            CvPublicIdentityContract::KEY_ROOT => [
                CvPublicIdentityContract::FIELD_CITY => 'Oslo',
                CvPublicIdentityContract::FIELD_REGION => 'Viken',
                CvPublicIdentityContract::FIELD_COUNTRY_BY_LOCALE => [
                    'en' => '',
                ],
            ],
        ];

        $line = $service->resolveAboutHeaderLocationLine($payload, 'en', ['en', 'fr'], 'fr');
        self::assertSame('Oslo | Viken', $line);
    }

    public function testPresentationFallbackExperienceYearsWhenCareerStartYearMissing(): void
    {
        $service = CvPublicIdentityPlaceholderServiceFactory::create();
        $now = new \DateTimeImmutable('2026-06-15', new \DateTimeZone('UTC'));
        $html = '<p>[[cv.experience_years]] years</p>';

        $out = $service->applyToHtml($html, [], 'fr', ['fr'], 'fr', $now);
        self::assertStringContainsString('200', $out);
        self::assertStringNotContainsString('[[cv.experience_years]]', $out);

        $invalidPayload = [
            CvPublicIdentityContract::KEY_ROOT => [
                CvPublicIdentityContract::FIELD_CAREER_START_YEAR => 3000,
            ],
        ];
        $invalidOut = $service->applyToHtml($html, $invalidPayload, 'fr', ['fr'], 'fr', $now);
        self::assertStringContainsString('200', $invalidOut);
    }

    public function testApplyToSanitizedPresentationUsesLocaleSpecificTagline(): void
    {
        $service = CvPublicIdentityPlaceholderServiceFactory::create();
        $payload = [
            CvPublicIdentityContract::KEY_ROOT => [
                CvPublicIdentityContract::FIELD_TAGLINE_BY_LOCALE => [
                    'fr' => 'Slogan FR',
                    'en' => 'Slogan EN',
                ],
            ],
        ];
        $now = new \DateTimeImmutable('2026-05-01');
        $map = [
            'fr' => '<p>[[cv.tagline]]</p>',
            'en' => '<p>[[cv.tagline]]</p>',
        ];
        $result = $service->applyToSanitizedPresentation($map, $payload, 'en', ['fr', 'en'], 'fr', $now);
        self::assertStringContainsString('Slogan FR', $result['htmlByLocale']['fr']);
        self::assertStringContainsString('Slogan EN', $result['htmlByLocale']['en']);
    }
}
