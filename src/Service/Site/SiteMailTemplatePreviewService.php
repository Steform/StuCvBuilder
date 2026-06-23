<?php

declare(strict_types=1);

namespace App\Service\Site;

use App\Service\RichText\RichHtmlSanitizer;
use App\Site\SiteMailTemplatesContract;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * @brief Renders HTML previews for site mail templates from admin draft form values.
 */
final class SiteMailTemplatePreviewService
{
    /**
     * @brief Build mail template preview service.
     *
     * @param SiteMailTemplateDefaultContentService $defaultContentService Default template content builder.
     * @param RichHtmlSanitizer $richHtmlSanitizer HTML sanitizer for draft blocks.
     * @param Environment $twig Twig renderer.
     * @param TranslatorInterface $translator Translation service.
     * @param string $envFromEmail Fallback sender email.
     * @param string $envToEmail Fallback recipient email.
     * @return void
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function __construct(
        private readonly SiteMailTemplateDefaultContentService $defaultContentService,
        private readonly RichHtmlSanitizer $richHtmlSanitizer,
        private readonly Environment $twig,
        private readonly TranslatorInterface $translator,
        private readonly string $envFromEmail = 'no-reply@localhost',
        private readonly string $envToEmail = '',
    ) {
    }

    /**
     * @brief Render HTML preview for one template type and locale from admin draft POST.
     *
     * @param Request $request Preview POST request with `mail_template_preview_type`, `mail_template_preview_locale`, and `mail_templates`.
     * @param list<string> $activeLocales Allowed locale codes.
     * @return array{
     *     type: string,
     *     locale: string,
     *     fromEmail: string,
     *     fromName: string,
     *     toEmail: string|null,
     *     subject: string,
     *     html: string
     * }
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function renderFromAdminPreviewRequest(Request $request, array $activeLocales): array
    {
        $type = strtolower(trim((string) $request->request->get('mail_template_preview_type', '')));
        $locale = strtolower(trim((string) $request->request->get('mail_template_preview_locale', '')));

        if (!in_array($type, SiteMailTemplatesContract::TEMPLATE_TYPES, true)) {
            throw new \InvalidArgumentException('dashboard.configuration_site.mail_templates.flash.invalid_type');
        }
        if (!in_array($locale, $activeLocales, true)) {
            throw new \InvalidArgumentException('dashboard.configuration_site.mail_templates.preview.invalid_locale');
        }

        /** @var array<string, mixed> $submitted */
        $submitted = $request->request->all('mail_templates');
        $typeSubmitted = is_array($submitted[$type] ?? null) ? $submitted[$type] : [];

        $merged = SiteMailTemplatesContract::mergeSubmitted(
            SiteMailTemplatesContract::normalize(null),
            [$type => $typeSubmitted],
            [$locale],
        );
        $typeData = $this->sanitizeTypeDraft($merged[$type], $type);
        $localeRow = $this->resolveLocaleRow($typeData, $type, $locale);
        $subjectParameters = $this->sampleSubjectParameters($type, $locale);

        $fromEmail = $typeData['fromEmail'] ?? null;
        if ($fromEmail === null || !SiteMailTemplatesContract::isValidFromEmail($fromEmail)) {
            $fromEmail = $this->resolveEnvFromEmail();
        }

        $fromName = trim((string) ($typeData['fromName'] ?? ''));
        if ($fromName === '') {
            $fromName = $this->resolveDefaultFromName($type, $locale);
        }

        $toEmail = null;
        if (SiteMailTemplatesContract::supportsToEmail($type)) {
            $storedTo = $typeData['toEmail'] ?? null;
            $toEmail = is_string($storedTo) && $storedTo !== '' ? $storedTo : $this->resolveEnvToEmail();
        }

        $subject = trim($localeRow['subject']);
        if ($subject === '') {
            $subject = $this->resolveDefaultSubject($type, $locale, $subjectParameters);
        } elseif ($subjectParameters !== []) {
            $subject = strtr($subject, $subjectParameters);
        }

        $blocks = [];
        foreach (SiteMailTemplatesContract::blockKeysForType($type) as $blockKey) {
            $storedBlock = trim($localeRow['blocks'][$blockKey] ?? '');
            if ($storedBlock !== '') {
                $blocks[$blockKey] = $storedBlock;
                continue;
            }
            $defaultBlock = $this->defaultContentService->buildLocaleDefaults($type, $locale)['blocks'][$blockKey] ?? '';
            $blocks[$blockKey] = $subjectParameters !== [] ? strtr($defaultBlock, $subjectParameters) : $defaultBlock;
        }

        $labels = [];
        foreach (SiteMailTemplatesContract::labelKeysForType($type) as $labelKey) {
            $storedLabel = trim($localeRow['labels'][$labelKey] ?? '');
            if ($storedLabel !== '') {
                $labels[$labelKey] = $storedLabel;
                continue;
            }
            $labels[$labelKey] = $this->defaultContentService->buildLocaleDefaults($type, $locale)['labels'][$labelKey] ?? '';
        }

        $html = $this->twig->render($this->templateForType($type), $this->sampleContextForType(
            $type,
            $locale,
            $blocks,
            $labels,
            $subject,
        ));

        return [
            'type' => $type,
            'locale' => $locale,
            'fromEmail' => $fromEmail,
            'fromName' => $fromName,
            'toEmail' => $toEmail,
            'subject' => $subject,
            'html' => $html,
        ];
    }

    /**
     * @brief Sanitize rich-text blocks in one normalized type row.
     *
     * @param array{fromEmail: string|null, fromName: string|null, toEmail: string|null, locales: array<string, array{subject: string, blocks: array<string, string>, labels: array<string, string>}>} $typeData Type row.
     * @param string $type Template type key.
     * @return array{fromEmail: string|null, fromName: string|null, toEmail: string|null, locales: array<string, array{subject: string, blocks: array<string, string>, labels: array<string, string>}>}
     * @date 2026-06-16
     * @author Stephane H.
     */
    private function sanitizeTypeDraft(array $typeData, string $type): array
    {
        foreach ($typeData['locales'] as $locale => $localeRow) {
            foreach (SiteMailTemplatesContract::blockKeysForType($type) as $blockKey) {
                $raw = $localeRow['blocks'][$blockKey] ?? '';
                $typeData['locales'][$locale]['blocks'][$blockKey] = $this->richHtmlSanitizer->sanitize($raw);
            }
        }

        return $typeData;
    }

    /**
     * @brief Resolve locale row with defaults for preview rendering.
     *
     * @param array{fromEmail: string|null, fromName: string|null, toEmail: string|null, locales: array<string, array{subject: string, blocks: array<string, string>, labels: array<string, string>}>} $typeData Type row.
     * @param string $type Template type key.
     * @param string $locale Locale code.
     * @return array{subject: string, blocks: array<string, string>, labels: array<string, string>}
     * @date 2026-06-16
     * @author Stephane H.
     */
    private function resolveLocaleRow(array $typeData, string $type, string $locale): array
    {
        if (isset($typeData['locales'][$locale]) && is_array($typeData['locales'][$locale])) {
            return $typeData['locales'][$locale];
        }

        return $this->defaultContentService->buildLocaleDefaults($type, $locale);
    }

    /**
     * @brief Map template type to Twig HTML template path.
     *
     * @param string $type Template type key.
     * @return string Twig template path.
     * @date 2026-06-16
     * @author Stephane H.
     */
    private function templateForType(string $type): string
    {
        return match ($type) {
            SiteMailTemplatesContract::TYPE_TOTP => 'emails/totp_code.html.twig',
            SiteMailTemplatesContract::TYPE_INVITATION => 'emails/invitation.html.twig',
            SiteMailTemplatesContract::TYPE_CV_CONTACT => 'emails/cv_contact.html.twig',
            SiteMailTemplatesContract::TYPE_RECRUITER_VISIT => 'emails/recruiter_visit.html.twig',
            default => throw new \InvalidArgumentException('dashboard.configuration_site.mail_templates.flash.invalid_type'),
        };
    }

    /**
     * @brief Build sample Twig context for one template type preview.
     *
     * @param string $type Template type key.
     * @param string $locale Locale code.
     * @param array<string, string> $blocks Resolved HTML blocks.
     * @param array<string, string> $labels Resolved labels.
     * @param string $subject Resolved subject line.
     * @return array<string, mixed>
     * @date 2026-06-16
     * @author Stephane H.
     */
    private function sampleContextForType(
        string $type,
        string $locale,
        array $blocks,
        array $labels,
        string $subject,
    ): array {
        $base = [
            'locale' => $locale,
            'blocks' => $blocks,
            'labels' => $labels,
        ];

        return match ($type) {
            SiteMailTemplatesContract::TYPE_TOTP => $base + [
                'totpCode' => $this->trans('dashboard.configuration_site.mail_templates.preview.sample.totp_code', $locale),
            ],
            SiteMailTemplatesContract::TYPE_INVITATION => $base + [
                'activationUrl' => $this->trans('dashboard.configuration_site.mail_templates.preview.sample.invite_url', $locale),
            ],
            SiteMailTemplatesContract::TYPE_CV_CONTACT => $base + [
                'visitorName' => $this->trans('dashboard.configuration_site.mail_templates.preview.sample.contact_name', $locale),
                'visitorEmail' => $this->trans('dashboard.configuration_site.mail_templates.preview.sample.contact_email', $locale),
                'subjectLine' => $this->trans('dashboard.configuration_site.mail_templates.preview.sample.contact_subject', $locale),
                'messageBody' => $this->trans('dashboard.configuration_site.mail_templates.preview.sample.contact_message', $locale),
            ],
            SiteMailTemplatesContract::TYPE_RECRUITER_VISIT => $base + [
                'subject' => $subject,
                'companyName' => $this->trans('dashboard.configuration_site.mail_templates.preview.sample.company_name', $locale),
                'companyCode' => $this->trans('dashboard.configuration_site.mail_templates.preview.sample.company_code', $locale),
                'visitDate' => $this->trans('dashboard.configuration_site.mail_templates.preview.sample.visit_date', $locale),
                'countryCode' => $this->trans('dashboard.configuration_site.mail_templates.preview.sample.country_code', $locale),
                'adminVisitsUrl' => $this->trans('dashboard.configuration_site.mail_templates.preview.sample.admin_visits_url', $locale),
            ],
            default => $base,
        };
    }

    /**
     * @brief Build subject placeholder map for preview rendering.
     *
     * @param string $type Template type key.
     * @param string $locale Locale code.
     * @return array<string, string>
     * @date 2026-06-16
     * @author Stephane H.
     */
    private function sampleSubjectParameters(string $type, string $locale): array
    {
        return match ($type) {
            SiteMailTemplatesContract::TYPE_CV_CONTACT => [
                '%subject%' => $this->trans('dashboard.configuration_site.mail_templates.preview.sample.contact_subject', $locale),
            ],
            SiteMailTemplatesContract::TYPE_RECRUITER_VISIT => [
                '%company_name%' => $this->trans('dashboard.configuration_site.mail_templates.preview.sample.company_name', $locale),
                '%company_code%' => $this->trans('dashboard.configuration_site.mail_templates.preview.sample.company_code', $locale),
                '%visit_date%' => $this->trans('dashboard.configuration_site.mail_templates.preview.sample.visit_date', $locale),
                '%country_code%' => $this->trans('dashboard.configuration_site.mail_templates.preview.sample.country_code', $locale),
            ],
            default => [],
        };
    }

    /**
     * @brief Resolve default subject translation for preview.
     *
     * @param string $type Template type key.
     * @param string $locale Locale code.
     * @param array<string, string> $subjectParameters Subject placeholders.
     * @return string
     * @date 2026-06-16
     * @author Stephane H.
     */
    private function resolveDefaultSubject(string $type, string $locale, array $subjectParameters): string
    {
        $key = match ($type) {
            SiteMailTemplatesContract::TYPE_TOTP => 'mail.totp.subject',
            SiteMailTemplatesContract::TYPE_INVITATION => 'mail.invite.subject',
            SiteMailTemplatesContract::TYPE_CV_CONTACT => 'mail.cv_contact.subject',
            SiteMailTemplatesContract::TYPE_RECRUITER_VISIT => 'mail.recruiter_visit.subject',
            default => '',
        };
        if ($key === '') {
            return '';
        }

        return $this->translator->trans($key, $subjectParameters, 'messages', $locale);
    }

    /**
     * @brief Resolve default sender display name for preview.
     *
     * @param string $type Template type key.
     * @param string $locale Locale code.
     * @return string
     * @date 2026-06-16
     * @author Stephane H.
     */
    private function resolveDefaultFromName(string $type, string $locale): string
    {
        $key = match ($type) {
            SiteMailTemplatesContract::TYPE_TOTP => 'mail.totp.brand',
            SiteMailTemplatesContract::TYPE_INVITATION => 'mail.totp.brand',
            SiteMailTemplatesContract::TYPE_CV_CONTACT => 'mail.cv_contact.brand',
            SiteMailTemplatesContract::TYPE_RECRUITER_VISIT => 'mail.recruiter_visit.brand',
            default => 'mail.totp.brand',
        };

        return $this->translator->trans($key, [], 'messages', $locale);
    }

    /**
     * @brief Resolve environment fallback sender email.
     *
     * @return string
     * @date 2026-06-16
     * @author Stephane H.
     */
    private function resolveEnvFromEmail(): string
    {
        $candidate = strtolower(trim($this->envFromEmail));
        if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_EMAIL) !== false) {
            return $candidate;
        }

        return 'no-reply@localhost';
    }

    /**
     * @brief Resolve environment fallback recipient email.
     *
     * @return string|null
     * @date 2026-06-16
     * @author Stephane H.
     */
    private function resolveEnvToEmail(): ?string
    {
        $candidate = strtolower(trim($this->envToEmail));
        if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_EMAIL) !== false) {
            return $candidate;
        }

        return null;
    }

    /**
     * @brief Translate preview sample string for target locale.
     *
     * @param string $key Translation key.
     * @param string $locale Locale code.
     * @return string
     * @date 2026-06-16
     * @author Stephane H.
     */
    private function trans(string $key, string $locale): string
    {
        return $this->translator->trans($key, [], 'messages', $locale);
    }
}
