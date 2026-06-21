<?php

declare(strict_types=1);

namespace App\Service\Site;

use App\Site\SiteMailTemplatesContract;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @brief Builds default mail template admin values from existing translation keys.
 */
final class SiteMailTemplateDefaultContentService
{
    /**
     * @brief Build default content service.
     *
     * @param TranslatorInterface $translator Translation service.
     * @return void
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @brief Build full normalized templates map seeded from translation defaults.
     *
     * @param list<string> $activeLocales Active locale codes.
     * @return array<string, array{fromEmail: string|null, fromName: string|null, locales: array<string, array{subject: string, blocks: array<string, string>, labels: array<string, string>}>}>
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function buildDefaultsForLocales(array $activeLocales): array
    {
        $templates = SiteMailTemplatesContract::normalize(null);
        foreach (SiteMailTemplatesContract::TEMPLATE_TYPES as $type) {
            foreach ($activeLocales as $locale) {
                $templates[$type]['locales'][$locale] = $this->buildLocaleDefaults($type, $locale);
            }
        }

        return $templates;
    }

    /**
     * @brief Build default locale row for one template type.
     *
     * @param string $type Template type key.
     * @param string $locale Locale code.
     * @return array{subject: string, blocks: array<string, string>, labels: array<string, string>}
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function buildLocaleDefaults(string $type, string $locale): array
    {
        return match ($type) {
            SiteMailTemplatesContract::TYPE_TOTP => [
                'subject' => $this->trans('mail.totp.subject', $locale),
                'blocks' => [
                    'title' => $this->wrapHeading($this->trans('mail.totp.title', $locale), 2),
                    'intro' => $this->wrapParagraph($this->trans('mail.totp.intro', $locale)),
                    'expiry_hint' => $this->wrapParagraph($this->trans('mail.totp.expiry_hint', $locale)),
                    'security_hint' => $this->wrapParagraph($this->trans('mail.totp.security_hint', $locale)),
                    'footer' => $this->wrapParagraph($this->trans('mail.totp.footer', $locale)),
                ],
                'labels' => [
                    'brand' => $this->trans('mail.totp.brand', $locale),
                    'code_label' => $this->trans('mail.totp.code_label', $locale),
                ],
            ],
            SiteMailTemplatesContract::TYPE_INVITATION => [
                'subject' => $this->trans('mail.invite.subject', $locale),
                'blocks' => [
                    'title' => $this->wrapHeading($this->trans('mail.invite.title', $locale), 2),
                    'intro' => $this->wrapParagraph($this->trans('mail.invite.intro', $locale)),
                    'expiry_hint' => $this->wrapParagraph($this->trans('mail.invite.expiry_hint', $locale)),
                    'security_hint' => $this->wrapParagraph($this->trans('mail.invite.security_hint', $locale)),
                    'footer' => $this->wrapParagraph($this->trans('mail.invite.footer', $locale)),
                ],
                'labels' => [
                    'cta' => $this->trans('mail.invite.cta', $locale),
                ],
            ],
            SiteMailTemplatesContract::TYPE_CV_CONTACT => [
                'subject' => $this->trans('mail.cv_contact.subject', $locale, ['%subject%' => '%subject%']),
                'blocks' => [
                    'intro' => $this->wrapParagraph($this->trans('mail.cv_contact.intro', $locale)),
                ],
                'labels' => [
                    'field_name' => $this->trans('mail.cv_contact.field_name', $locale),
                    'field_email' => $this->trans('mail.cv_contact.field_email', $locale),
                    'field_subject' => $this->trans('mail.cv_contact.field_subject', $locale),
                    'field_message' => $this->trans('mail.cv_contact.field_message', $locale),
                ],
            ],
            SiteMailTemplatesContract::TYPE_RECRUITER_VISIT => [
                'subject' => $this->trans('mail.recruiter_visit.subject', $locale, [
                    '%company_name%' => '%company_name%',
                ]),
                'blocks' => [
                    'title' => $this->wrapHeading($this->trans('mail.recruiter_visit.title', $locale), 2),
                    'intro' => $this->wrapParagraph($this->trans('mail.recruiter_visit.intro', $locale)),
                    'company_details' => $this->wrapParagraph($this->trans('mail.recruiter_visit.company_details', $locale)),
                    'visit_summary' => $this->wrapParagraph($this->trans('mail.recruiter_visit.visit_summary', $locale)),
                    'footer' => $this->wrapParagraph($this->trans('mail.recruiter_visit.footer', $locale)),
                ],
                'labels' => [
                    'field_company' => $this->trans('mail.recruiter_visit.field_company', $locale),
                    'field_code' => $this->trans('mail.recruiter_visit.field_code', $locale),
                    'field_date' => $this->trans('mail.recruiter_visit.field_date', $locale),
                    'field_country' => $this->trans('mail.recruiter_visit.field_country', $locale),
                    'field_admin_link' => $this->trans('mail.recruiter_visit.field_admin_link', $locale),
                ],
            ],
            default => [
                'subject' => '',
                'blocks' => [],
                'labels' => [],
            ],
        };
    }

    /**
     * @brief Reset one template type to translation defaults for active locales.
     *
     * @param string $type Template type key.
     * @param list<string> $activeLocales Active locale codes.
     * @return array{fromEmail: string|null, fromName: string|null, locales: array<string, array{subject: string, blocks: array<string, string>, labels: array<string, string>}>}
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function buildTypeDefaults(string $type, array $activeLocales): array
    {
        $typeData = [
            'fromEmail' => null,
            'fromName' => null,
            'locales' => [],
        ];
        foreach ($activeLocales as $locale) {
            $typeData['locales'][$locale] = $this->buildLocaleDefaults($type, $locale);
        }

        return $typeData;
    }

    /**
     * @brief Translate a mail default key for a locale.
     *
     * @param string $key Translation key.
     * @param string $locale Locale code.
     * @param array<string, string> $parameters Optional translation parameters.
     * @return string Translated string.
     * @date 2026-06-16
     * @author Stephane H.
     */
    private function trans(string $key, string $locale, array $parameters = []): string
    {
        return $this->translator->trans($key, $parameters, 'messages', $locale);
    }

    /**
     * @brief Wrap plain text in a paragraph for CKEditor seed content.
     *
     * @param string $text Plain text.
     * @return string HTML paragraph.
     * @date 2026-06-16
     * @author Stephane H.
     */
    private function wrapParagraph(string $text): string
    {
        return '<p>'.htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</p>';
    }

    /**
     * @brief Wrap plain text in a heading for CKEditor seed content.
     *
     * @param string $text Plain text.
     * @param int $level Heading level (2-6).
     * @return string HTML heading.
     * @date 2026-06-16
     * @author Stephane H.
     */
    private function wrapHeading(string $text, int $level): string
    {
        $level = max(2, min(6, $level));
        $tag = 'h'.$level;

        return '<'.$tag.'>'.htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</'.$tag.'>';
    }
}
