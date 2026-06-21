<?php

declare(strict_types=1);

namespace App\Service\Cv;

use App\Service\Employment\EmploymentPublicDocumentPdfResolver;
use App\Service\RichText\RichHtmlSanitizer;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @brief Replace whitelisted `[[cv.*]]` placeholders in sanitized About HTML using `cvPublicIdentity` from the resolved payload.
 *
 * Runs only after {@see \App\Service\RichText\RichHtmlSanitizer}. Most replacement values are HTML-escaped text; the
 * `pdf`, `lm_pdf`, and `learn_more` tokens expand to fixed server-built anchors. Unknown tokens are left literal.
 * Age uses full calendar years with birthday rule in the default PHP timezone (see `date_default_timezone_get()`).
 *
 * @date 2026-05-14
 * @author Stephane H.
 */
final class CvPublicIdentityPlaceholderService
{
    /**
     * @brief Construct service with translator for localized `pdf` token output.
     * @param TranslatorInterface $translator Symfony translator for button label by viewer locale.
     * @param RichHtmlSanitizer $richHtmlSanitizer Re-sanitize tagline HTML fragments at substitution time.
     * @param CvAgeYearsCalculator $cvAgeYearsCalculator Shared age calculator for birth date.
     * @param EmploymentPublicDocumentPdfResolver $employmentPublicDocumentPdfResolver Public LM PDF resolver.
     * @return void
     * @date 2026-06-17
     * @author Stephane H.
     */
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly RichHtmlSanitizer $richHtmlSanitizer,
        private readonly CvAgeYearsCalculator $cvAgeYearsCalculator,
        private readonly EmploymentPublicDocumentPdfResolver $employmentPublicDocumentPdfResolver,
    ) {
    }

    /**
     * @brief Apply placeholder substitution to each locale map entry and to the projected scalar HTML key when present.
     *
     * @param array<string, string> $sanitizedHtmlByLocale Locale code => sanitized HTML.
     * @param array<string, mixed> $payload Merged CV profile payload (includes {@see CvPublicIdentityContract::KEY_ROOT} when configured).
     * @param list<string> $activeLocalesOrder Active locales for tagline fallback order.
     * @param string $defaultLocale Site default locale.
     * @param \DateTimeImmutable $now Clock reference for age, document year, experience years, and date_now.
     * @return array{htmlByLocale: array<string, string>, html: string} Updated map plus scalar `html` picked for `$displayLocale`.
     * @date 2026-05-14
     * @author Stephane H.
     */
    public function applyToSanitizedPresentation(
        array $sanitizedHtmlByLocale,
        array $payload,
        string $displayLocale,
        array $activeLocalesOrder,
        string $defaultLocale,
        \DateTimeImmutable $now
    ): array {
        $outByLocale = [];
        foreach ($sanitizedHtmlByLocale as $loc => $html) {
            $localeCode = is_string($loc) ? $loc : (string) $loc;
            $outByLocale[$localeCode] = $this->applyToHtml(is_string($html) ? $html : '', $payload, $localeCode, $activeLocalesOrder, $defaultLocale, $now);
        }

        $scalarHtml = AboutPresentationContract::pickPresentationHtmlForLocale(
            $outByLocale,
            $displayLocale,
            $defaultLocale,
            $activeLocalesOrder
        );

        return [
            'htmlByLocale' => $outByLocale,
            'html' => $scalarHtml,
        ];
    }

    /**
     * @brief Replace whitelisted tokens inside one HTML fragment for a specific viewer locale.
     *
     * @param string $html Sanitized HTML body.
     * @param array<string, mixed> $payload Merged CV JSON payload.
     * @param string $viewerLocale Locale used for `tagline` and `date_now` formatting.
     * @param list<string> $activeLocalesOrder Active locale order for tagline fallback.
     * @param string $defaultLocale Default locale for tagline fallback.
     * @param \DateTimeImmutable $now Render-time clock.
     * @return string HTML with placeholders replaced.
     * @date 2026-05-14
     * @author Stephane H.
     */
    public function applyToHtml(
        string $html,
        array $payload,
        string $viewerLocale,
        array $activeLocalesOrder,
        string $defaultLocale,
        \DateTimeImmutable $now
    ): string {
        $identity = $payload[CvPublicIdentityContract::KEY_ROOT] ?? null;
        $identityMap = is_array($identity) ? $identity : [];

        $normalizedHtml = $this->normalizeCvPlaceholderSyntaxInHtml($html);

        $replacements = [];
        foreach (CvPublicIdentityContract::PLACEHOLDER_TOKEN_NAMES as $name) {
            $replacements['[[cv.'.$name.']]'] = $this->resolveToken(
                $name,
                $identityMap,
                $payload,
                $viewerLocale,
                $activeLocalesOrder,
                $defaultLocale,
                $now
            );
        }

        $replaced = strtr($normalizedHtml, $replacements);

        return $this->replaceRemainingCvPlaceholderMarkers(
            $replaced,
            $identityMap,
            $payload,
            $viewerLocale,
            $activeLocalesOrder,
            $defaultLocale,
            $now
        );
    }

    /**
     * @brief Normalize CKEditor or copy-paste variants of `[[cv.token]]` markers before substitution.
     *
     * @param string $html Sanitized HTML fragment.
     * @return string HTML with canonical `[[cv.snake_case]]` markers.
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function normalizeCvPlaceholderSyntaxInHtml(string $html): string
    {
        if ($html === '') {
            return '';
        }

        $normalized = str_replace(
            ["\u{200B}", "\u{200C}", "\u{200D}", "\u{FEFF}", '［', '］'],
            ['', '', '', '', '[', ']'],
            $html
        );

        $canonical = preg_replace(
            '/\[\[\s*cv\.([a-z][a-z0-9_]*)\s*\]\]/i',
            '[[cv.$1]]',
            $normalized
        );

        return is_string($canonical) ? $canonical : $normalized;
    }

    /**
     * @brief Replace any remaining whitelisted `[[cv.*]]` markers after {@see strtr()} (safety net).
     *
     * @param string $html HTML after primary substitution pass.
     * @param array<string, mixed> $identity Decoded `cvPublicIdentity` object.
     * @param array<string, mixed> $payload Full CV payload (employment format code).
     * @param string $viewerLocale Active locale for localized tokens.
     * @param list<string> $activeLocalesOrder Active locale order for tagline fallback.
     * @param string $defaultLocale Default locale for tagline fallback.
     * @param \DateTimeImmutable $now Render-time clock.
     * @return string HTML with remaining known placeholders expanded.
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function replaceRemainingCvPlaceholderMarkers(
        string $html,
        array $identity,
        array $payload,
        string $viewerLocale,
        array $activeLocalesOrder,
        string $defaultLocale,
        \DateTimeImmutable $now
    ): string {
        if (!str_contains($html, '[[cv.')) {
            return $html;
        }

        $replaced = preg_replace_callback(
            '/\[\[cv\.([a-z][a-z0-9_]*)\]\]/',
            function (array $matches) use ($identity, $payload, $viewerLocale, $activeLocalesOrder, $defaultLocale, $now): string {
                $tokenName = strtolower($matches[1]);
                if (!in_array($tokenName, CvPublicIdentityContract::PLACEHOLDER_TOKEN_NAMES, true)) {
                    return $matches[0];
                }

                return $this->resolveToken(
                    $tokenName,
                    $identity,
                    $payload,
                    $viewerLocale,
                    $activeLocalesOrder,
                    $defaultLocale,
                    $now
                );
            },
            $html
        );

        return is_string($replaced) ? $replaced : $html;
    }

    /**
     * @brief Resolve plain-text `[[cv.display_name]]` for document titles (no HTML escaping).
     *
     * @param array<string, mixed> $payload Merged CV profile payload.
     * @param string $viewerLocale Locale for the fallback label when display name is empty.
     * @return string Owner display name or localized fallback.
     * @date 2026-05-16
     * @author Stephane H.
     */
    public function resolveDisplayNamePlain(array $payload, string $viewerLocale): string
    {
        $identity = $payload[CvPublicIdentityContract::KEY_ROOT] ?? null;
        $identityMap = is_array($identity) ? $identity : [];

        return $this->resolveDisplayName($identityMap, $viewerLocale);
    }

    /**
     * @brief Resolve localized sought position as plain text for SEO titles and descriptions.
     *
     * @param array<string, mixed> $payload Merged CV profile payload.
     * @param string $viewerLocale Active viewer locale.
     * @param list<string> $activeLocalesOrder Active locale order for fallback.
     * @param string $defaultLocale Site default locale.
     * @return string Plain-text sought position or empty string.
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function resolveSoughtPositionPlain(
        array $payload,
        string $viewerLocale,
        array $activeLocalesOrder,
        string $defaultLocale,
    ): string {
        $identity = $payload[CvPublicIdentityContract::KEY_ROOT] ?? null;
        $identityMap = is_array($identity) ? $identity : [];

        return $this->resolveSoughtPosition($identityMap, $viewerLocale, $defaultLocale, $activeLocalesOrder);
    }

    /**
     * @brief Resolve the About header location tile line as `city | region | country`.
     *
     * Omits empty segments. Country uses {@see self::resolveCountry()} for the viewer locale chain.
     *
     * @param array<string, mixed> $payload Merged CV profile payload.
     * @param string $viewerLocale Active public CV locale.
     * @param list<string> $activeLocalesOrder Active locales for country fallback.
     * @param string $defaultLocale Site default locale.
     * @return string Plain location line for the About tile (pipe-separated parts).
     * @date 2026-05-23
     * @author Stephane H.
     */
    public function resolveAboutHeaderLocationLine(
        array $payload,
        string $viewerLocale,
        array $activeLocalesOrder,
        string $defaultLocale,
    ): string {
        $identity = $payload[CvPublicIdentityContract::KEY_ROOT] ?? null;
        $identityMap = is_array($identity) ? $identity : [];

        $parts = [];
        $city = $this->stringField($identityMap, CvPublicIdentityContract::FIELD_CITY);
        if ($city !== '') {
            $parts[] = $city;
        }

        $region = $this->stringField($identityMap, CvPublicIdentityContract::FIELD_REGION);
        if ($region !== '') {
            $parts[] = $region;
        }

        $country = $this->resolveCountry($identityMap, $viewerLocale, $defaultLocale, $activeLocalesOrder);
        if ($country !== '') {
            $parts[] = $country;
        }

        return implode(' | ', $parts);
    }

    /**
     * @brief Resolve one token to an HTML-safe string (entities).
     *
     * @param string $tokenName Token without `[[cv.` / `]]` wrapper.
     * @param array<string, mixed> $identity Decoded `cvPublicIdentity` object.
     * @param array<string, mixed> $payload Full CV payload (employment format code).
     * @param string $viewerLocale Active locale for localized tokens.
     * @param list<string> $activeLocalesOrder Fallback order for tagline.
     * @param string $defaultLocale Site default locale.
     * @param \DateTimeImmutable $now Render instant.
     * @return string Escaped replacement text, or safe HTML for the `pdf`, `lm_pdf`, and `learn_more` tokens (may be empty for scalar tokens).
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function resolveToken(
        string $tokenName,
        array $identity,
        array $payload,
        string $viewerLocale,
        array $activeLocalesOrder,
        string $defaultLocale,
        \DateTimeImmutable $now
    ): string {
        $tzRaw = date_default_timezone_get();
        $tzName = is_string($tzRaw) && $tzRaw !== '' ? $tzRaw : 'UTC';
        $tz = new \DateTimeZone($tzName);
        $todayLocal = $now->setTimezone($tz)->setTime(0, 0, 0);

        return match ($tokenName) {
            'display_name' => $this->escapeString($this->resolveDisplayName($identity, $viewerLocale)),
            'city' => $this->escapeString($this->stringField($identity, CvPublicIdentityContract::FIELD_CITY)),
            'region' => $this->escapeString($this->stringField($identity, CvPublicIdentityContract::FIELD_REGION)),
            'country' => $this->escapeString($this->resolveCountry($identity, $viewerLocale, $defaultLocale, $activeLocalesOrder)),
            'sought_position' => $this->escapeString($this->resolveSoughtPosition($identity, $viewerLocale, $defaultLocale, $activeLocalesOrder)),
            'status' => $this->escapeString($this->resolveStatus($identity, $viewerLocale, $defaultLocale, $activeLocalesOrder)),
            'career_start_year' => $this->escapeString($this->formatCareerStartYear($identity, $now, $tz)),
            'experience_years' => $this->escapeString($this->resolveExperienceYears($identity, $now, $tz)),
            'age_years' => $this->escapeString($this->resolveAgeYears($identity, $todayLocal, $tz)),
            'tagline' => $this->richHtmlSanitizer->sanitize($this->resolveTagline($identity, $viewerLocale, $defaultLocale, $activeLocalesOrder)),
            'document_year' => $this->escapeString((string) (int) $now->setTimezone($tz)->format('Y')),
            'date_now' => $this->escapeString($this->formatDateNowLocalized($now, $viewerLocale, $tz)),
            'pdf' => $this->resolvePdfPlaceholderHtml($viewerLocale, $payload),
            'lm_pdf' => $this->resolveLmPdfPlaceholderHtml($viewerLocale, $payload),
            'learn_more' => $this->resolveLearnMorePlaceholderHtml($viewerLocale),
            default => '',
        };
    }

    /**
     * @brief Build the public Situation page link for `[[cv.learn_more]]` (same dark pill style as `[[cv.pdf]]`).
     *
     * @param string $viewerLocale Request locale for the visible label.
     * @return string Safe HTML fragment (no user-controlled markup).
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function resolveLearnMorePlaceholderHtml(string $viewerLocale): string
    {
        $label = $this->translator->trans(
            'cv.about.learn_more_link',
            [],
            'messages',
            $viewerLocale
        );
        $ariaLabel = $this->translator->trans(
            'cv.about.learn_more_link_aria',
            [],
            'messages',
            $viewerLocale
        );
        $escapedLabel = htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $escapedAria = htmlspecialchars($ariaLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<a href="/cv/situation" class="btn btn-dark cv-about__pdf-btn rounded-pill" title="'.$escapedAria.'" aria-label="'.$escapedAria.'">'
            .'<i class="bi bi-arrow-right-circle me-2" aria-hidden="true"></i>'
            .$escapedLabel
            .'</a>';
    }

    /**
     * @brief Build the public PDF download button for `[[cv.pdf]]` (localized label, dark pill, download icon).
     *
     * @param string $viewerLocale Request locale for the visible label.
     * @param array<string, mixed> $payload CV payload with optional employment format code.
     * @return string Safe HTML fragment (no user-controlled markup).
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function resolvePdfPlaceholderHtml(string $viewerLocale, array $payload): string
    {
        $label = $this->translator->trans(
            'cv.about.pdf_download_button',
            [],
            'messages',
            $viewerLocale
        );
        $escapedLabel = htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $formatCode = $payload[CvPublicIdentityContract::KEY_EMPLOYMENT_FORMAT_CODE] ?? '';
        $formatCode = is_string($formatCode) ? trim($formatCode) : '';
        $href = '/cv/pdf';
        if ($formatCode !== '') {
            $href .= '?format='.rawurlencode($formatCode);
        }
        $escapedHref = htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<a href="'.$escapedHref.'" class="btn btn-dark cv-about__pdf-btn rounded-pill" title="'.$escapedLabel.'">'
            .'<i class="bi bi-download me-2" aria-hidden="true"></i>'
            .$escapedLabel
            .'</a>';
    }

    /**
     * @brief Build the cover letter PDF download button for `[[cv.lm_pdf]]` (same pill style as `[[cv.pdf]]`).
     *
     * @param string $viewerLocale Request locale for the visible label.
     * @param array<string, mixed> $payload CV payload with optional employment format code.
     * @return string Safe HTML fragment (no user-controlled markup).
     * @date 2026-06-17
     * @author Stephane H.
     */
    private function resolveLmPdfPlaceholderHtml(string $viewerLocale, array $payload): string
    {
        $formatCode = $payload[CvPublicIdentityContract::KEY_EMPLOYMENT_FORMAT_CODE] ?? '';
        $formatCode = is_string($formatCode) ? trim($formatCode) : '';
        if ($formatCode !== '' && $this->employmentPublicDocumentPdfResolver->resolveLm($formatCode, $viewerLocale) === null) {
            return '';
        }

        $label = $this->translator->trans(
            'cv.about.lm_pdf_download_button',
            [],
            'messages',
            $viewerLocale
        );
        $escapedLabel = htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $href = '/cv/lm-pdf';
        if ($formatCode !== '') {
            $href .= '?format='.rawurlencode($formatCode);
        }
        $escapedHref = htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<a href="'.$escapedHref.'" class="btn btn-dark cv-about__pdf-btn rounded-pill" title="'.$escapedLabel.'">'
            .'<i class="bi bi-download me-2" aria-hidden="true"></i>'
            .$escapedLabel
            .'</a>';
    }

    /**
     * @param array<string, mixed> $identity Identity map.
     * @param string $field Field name under identity.
     * @return string Trimmed scalar or empty.
     * @date 2026-05-14
     * @author Stephane H.
     */
    private function stringField(array $identity, string $field): string
    {
        $v = $identity[$field] ?? null;

        return is_string($v) ? trim($v) : '';
    }

    /**
     * @brief Resolve display name or a localized “fill in your name” hint for About presentation.
     *
     * @param array<string, mixed> $identity Identity map.
     * @param string $viewerLocale Locale for the fallback label.
     * @return string Plain display name or presentation fallback text.
     * @date 2026-05-16
     * @author Stephane H.
     */
    private function resolveDisplayName(array $identity, string $viewerLocale): string
    {
        $name = $this->stringField($identity, CvPublicIdentityContract::FIELD_DISPLAY_NAME);
        if ($name !== '') {
            return $name;
        }

        return $this->translator->trans(
            'cv.about.presentation_default.fallback_display_name',
            [],
            'messages',
            $viewerLocale
        );
    }

    /**
     * @brief Resolve age in full years or a fixed placeholder when birth date is missing/invalid.
     *
     * @param array<string, mixed> $identity Identity map.
     * @param \DateTimeImmutable $todayLocal Local-midnight today for age boundary.
     * @param \DateTimeZone $tz Timezone for birth parsing.
     * @return string Age as digits or {@see CvPublicIdentityContract::PRESENTATION_FALLBACK_AGE_YEARS} as string.
     * @date 2026-05-16
     * @author Stephane H.
     */
    private function resolveAgeYears(array $identity, \DateTimeImmutable $todayLocal, \DateTimeZone $tz): string
    {
        $age = $this->formatAgeYears($identity, $todayLocal, $tz);
        if ($age !== '') {
            return $age;
        }

        return (string) CvPublicIdentityContract::PRESENTATION_FALLBACK_AGE_YEARS;
    }

    /**
     * @param array<string, mixed> $identity Identity map.
     * @return string Year digits or empty when unset/invalid.
     * @date 2026-05-14
     * @author Stephane H.
     */
    private function formatCareerStartYear(array $identity, \DateTimeImmutable $now, \DateTimeZone $tz): string
    {
        $y = $this->parseCareerStartYear($identity, $now, $tz);

        return $y !== null ? (string) $y : '';
    }

    /**
     * @brief Resolve experience span in full years or a fixed placeholder when career start year is missing/invalid.
     *
     * @param array<string, mixed> $identity Identity map.
     * @param \DateTimeImmutable $now Clock reference for current calendar year.
     * @param \DateTimeZone $tz Timezone for year boundary.
     * @return string Year span as digits or {@see CvPublicIdentityContract::PRESENTATION_FALLBACK_EXPERIENCE_YEARS} as string.
     * @date 2026-05-18
     * @author Stephane H.
     */
    private function resolveExperienceYears(array $identity, \DateTimeImmutable $now, \DateTimeZone $tz): string
    {
        $years = $this->formatExperienceYears($identity, $now, $tz);
        if ($years !== '') {
            return $years;
        }

        return (string) CvPublicIdentityContract::PRESENTATION_FALLBACK_EXPERIENCE_YEARS;
    }

    /**
     * @param array<string, mixed> $identity Identity map.
     * @param \DateTimeImmutable $now Clock.
     * @param \DateTimeZone $tz Timezone for current calendar year.
     * @return string Non-negative year span or empty when career start year cannot be parsed.
     * @date 2026-05-18
     * @author Stephane H.
     */
    private function formatExperienceYears(array $identity, \DateTimeImmutable $now, \DateTimeZone $tz): string
    {
        $y = $this->parseCareerStartYear($identity, $now, $tz);
        if ($y === null) {
            return '';
        }

        $currentYear = (int) $now->setTimezone($tz)->format('Y');
        $span = $currentYear - $y;

        return $span >= 0 ? (string) $span : '';
    }

    /**
     * @param array<string, mixed> $identity Identity map.
     * @return int|null Validated year or null.
     * @date 2026-05-14
     * @author Stephane H.
     */
    private function parseCareerStartYear(array $identity, \DateTimeImmutable $now, \DateTimeZone $tz): ?int
    {
        $raw = $identity[CvPublicIdentityContract::FIELD_CAREER_START_YEAR] ?? null;
        if (is_int($raw)) {
            $y = $raw;
        } elseif (is_string($raw) && ctype_digit($raw)) {
            $y = (int) $raw;
        } else {
            return null;
        }

        $maxYear = (int) $now->setTimezone($tz)->format('Y');
        if ($y < CvPublicIdentityContract::CAREER_START_YEAR_MIN || $y > $maxYear) {
            return null;
        }

        return $y;
    }

    /**
     * @param array<string, mixed> $identity Identity map.
     * @param \DateTimeImmutable $todayLocal Local-midnight "today" for age boundary.
     * @param \DateTimeZone $tz Timezone for birth parsing.
     * @return string Non-negative age or empty.
     * @date 2026-05-14
     * @author Stephane H.
     */
    private function formatAgeYears(array $identity, \DateTimeImmutable $todayLocal, \DateTimeZone $tz): string
    {
        $age = $this->cvAgeYearsCalculator->computeFromIdentityMap($identity, $todayLocal);

        return $age !== null ? (string) $age : '';
    }

    /**
     * @brief Resolve localized tagline string from `taglineByLocale` with locale fallback.
     * @param array<string, mixed> $identity Identity map.
     * @param string $viewerLocale Preferred locale.
     * @param string $defaultLocale Default locale.
     * @param list<string> $activeLocalesOrder Order for fallback scan.
     * @return string Tagline fragment (plain or HTML); may be re-sanitized at substitution.
     * @date 2026-05-14
     * @author Stephane H.
     */
    private function resolveTagline(
        array $identity,
        string $viewerLocale,
        string $defaultLocale,
        array $activeLocalesOrder
    ): string {
        return $this->resolveLocalizedStringMap(
            $identity,
            CvPublicIdentityContract::FIELD_TAGLINE_BY_LOCALE,
            $viewerLocale,
            $defaultLocale,
            $activeLocalesOrder
        );
    }

    /**
     * @brief Resolve localized country label from `countryByLocale` with locale fallback.
     *
     * @param array<string, mixed> $identity Identity map.
     * @param string $viewerLocale Preferred locale.
     * @param string $defaultLocale Default locale.
     * @param list<string> $activeLocalesOrder Order for fallback scan.
     * @return string Country label or empty string.
     * @date 2026-05-23
     * @author Stephane H.
     */
    private function resolveCountry(
        array $identity,
        string $viewerLocale,
        string $defaultLocale,
        array $activeLocalesOrder
    ): string {
        return $this->resolveLocalizedStringMap(
            $identity,
            CvPublicIdentityContract::FIELD_COUNTRY_BY_LOCALE,
            $viewerLocale,
            $defaultLocale,
            $activeLocalesOrder
        );
    }

    /**
     * @brief Resolve localized sought position from `soughtPositionByLocale` with locale fallback.
     *
     * @param array<string, mixed> $identity Identity map.
     * @param string $viewerLocale Preferred locale.
     * @param string $defaultLocale Default locale.
     * @param list<string> $activeLocalesOrder Order for fallback scan.
     * @return string Sought position label or empty string.
     * @date 2026-05-23
     * @author Stephane H.
     */
    private function resolveSoughtPosition(
        array $identity,
        string $viewerLocale,
        string $defaultLocale,
        array $activeLocalesOrder
    ): string {
        return $this->resolveLocalizedStringMap(
            $identity,
            CvPublicIdentityContract::FIELD_SOUGHT_POSITION_BY_LOCALE,
            $viewerLocale,
            $defaultLocale,
            $activeLocalesOrder
        );
    }

    /**
     * @brief Resolve localized professional status for `[[cv.status]]`.
     *
     * @param array<string, mixed> $identity Identity map.
     * @param string $viewerLocale Preferred locale.
     * @param string $defaultLocale Default locale.
     * @param list<string> $activeLocalesOrder Fallback locale order.
     * @return string Status label or empty.
     * @date 2026-05-23
     * @author Stephane H.
     */
    private function resolveStatus(
        array $identity,
        string $viewerLocale,
        string $defaultLocale,
        array $activeLocalesOrder
    ): string {
        return $this->resolveLocalizedStringMap(
            $identity,
            CvPublicIdentityContract::FIELD_STATUS_BY_LOCALE,
            $viewerLocale,
            $defaultLocale,
            $activeLocalesOrder
        );
    }

    /**
     * @brief Pick a scalar string from a locale-keyed map using the standard CV locale fallback order.
     *
     * @param array<string, mixed> $identity Identity map.
     * @param string $field Locale map field name.
     * @param string $viewerLocale Preferred locale.
     * @param string $defaultLocale Default locale.
     * @param list<string> $activeLocalesOrder Order for fallback scan.
     * @return string Resolved string or empty.
     * @date 2026-05-23
     * @author Stephane H.
     */
    private function resolveLocalizedStringMap(
        array $identity,
        string $field,
        string $viewerLocale,
        string $defaultLocale,
        array $activeLocalesOrder
    ): string {
        $raw = $identity[$field] ?? null;
        if (!is_array($raw)) {
            return '';
        }

        /** @var array<string, string> $map */
        $map = [];
        foreach ($raw as $k => $v) {
            if (is_string($k) && is_string($v)) {
                $trimmed = trim($v);
                if ($trimmed !== '') {
                    $map[$k] = $trimmed;
                }
            }
        }

        return AboutPresentationContract::pickPresentationHtmlForLocale(
            $map,
            $viewerLocale,
            $defaultLocale,
            $activeLocalesOrder
        );
    }

    /**
     * @brief Format "today" as a long locale-specific date (no time) using ext-intl when available.
     *
     * @param \DateTimeImmutable $now Instant to format.
     * @param string $viewerLocale BCP 47 locale code.
     * @param \DateTimeZone $tz Display timezone.
     * @return string Plain text date.
     * @date 2026-05-14
     * @author Stephane H.
     */
    private function formatDateNowLocalized(\DateTimeImmutable $now, string $viewerLocale, \DateTimeZone $tz): string
    {
        $local = $now->setTimezone($tz);
        if (class_exists(\IntlDateFormatter::class)) {
            $formatter = new \IntlDateFormatter(
                $viewerLocale,
                \IntlDateFormatter::LONG,
                \IntlDateFormatter::NONE,
                $tz->getName(),
                \IntlDateFormatter::GREGORIAN
            );
            if ($formatter !== null) {
                $formatted = $formatter->format($local);
                if (is_string($formatted) && $formatted !== '') {
                    return $formatted;
                }
            }
        }

        return $local->format('Y-m-d');
    }

    /**
     * @brief Escape for HTML text nodes and attribute-safe fragments.
     *
     * @param string $value Raw UTF-8 string.
     * @return string Escaped value.
     * @date 2026-05-14
     * @author Stephane H.
     */
    private function escapeString(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
