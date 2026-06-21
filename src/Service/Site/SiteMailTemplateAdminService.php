<?php

declare(strict_types=1);

namespace App\Service\Site;

use App\Entity\HomeCustomization;
use App\Service\Home\HomeCustomizationService;
use App\Service\RichText\RichHtmlSanitizer;
use App\Site\SiteMailTemplatesContract;
use Symfony\Component\HttpFoundation\Request;

/**
 * @brief Parse, sanitize and persist site mail templates from admin requests.
 */
final class SiteMailTemplateAdminService
{
    /**
     * @brief Build admin mail template service.
     *
     * @param HomeCustomizationService $homeCustomizationService Home customization persistence.
     * @param RichHtmlSanitizer $richHtmlSanitizer HTML sanitizer for rich blocks.
     * @param SiteMailTemplateDefaultContentService $defaultContentService Default template content builder.
     * @return void
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function __construct(
        private readonly HomeCustomizationService $homeCustomizationService,
        private readonly RichHtmlSanitizer $richHtmlSanitizer,
        private readonly SiteMailTemplateDefaultContentService $defaultContentService,
    ) {
    }

    /**
     * @brief Load normalized templates for admin display with defaults merged per locale.
     *
     * @param list<string> $activeLocales Active locale codes.
     * @return array<string, array{fromEmail: string|null, fromName: string|null, locales: array<string, array{subject: string, blocks: array<string, string>, labels: array<string, string>}>}>
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function buildAdminViewModel(array $activeLocales): array
    {
        $customization = $this->homeCustomizationService->getOrCreateSingleton();
        $stored = SiteMailTemplatesContract::decodeFromStorage($customization->getMailTemplatesJson());
        $defaults = $this->defaultContentService->buildDefaultsForLocales($activeLocales);

        return $this->mergeWithDefaults($stored, $defaults, $activeLocales);
    }

    /**
     * @brief Apply admin POST mail template payload onto the home customization singleton.
     *
     * @param Request $request Admin site configuration request.
     * @param list<string> $activeLocales Active locale codes.
     * @return void
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function applyFromAdminRequest(Request $request, array $activeLocales): void
    {
        $customization = $this->homeCustomizationService->getOrCreateSingleton();
        $existing = SiteMailTemplatesContract::decodeFromStorage($customization->getMailTemplatesJson());

        /** @var array<string, mixed> $submitted */
        $submitted = $request->request->all('mail_templates');
        $this->assertSubmittedFromEmailsAreValid($submitted);

        $merged = SiteMailTemplatesContract::mergeSubmitted($existing, $submitted, $activeLocales);
        $sanitized = $this->sanitizeTemplates($merged);

        $customization->setMailTemplatesJson(SiteMailTemplatesContract::encodeForStorage($sanitized));
    }

    /**
     * @brief Reset one template type to translation defaults.
     *
     * @param string $type Template type key.
     * @param list<string> $activeLocales Active locale codes.
     * @return void
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function resetTypeToDefaults(string $type, array $activeLocales): void
    {
        if (!in_array($type, SiteMailTemplatesContract::TEMPLATE_TYPES, true)) {
            throw new \InvalidArgumentException('dashboard.configuration_site.mail_templates.flash.invalid_type');
        }

        $customization = $this->homeCustomizationService->getOrCreateSingleton();
        $existing = SiteMailTemplatesContract::decodeFromStorage($customization->getMailTemplatesJson());
        $existing[$type] = $this->defaultContentService->buildTypeDefaults($type, $activeLocales);
        $sanitized = $this->sanitizeTemplates($existing);
        $customization->setMailTemplatesJson(SiteMailTemplatesContract::encodeForStorage($sanitized));
    }

    /**
     * @brief Read normalized templates from the singleton without admin default merge.
     *
     * @param HomeCustomization|null $customization Optional customization row.
     * @return array<string, array{fromEmail: string|null, fromName: string|null, locales: array<string, array{subject: string, blocks: array<string, string>, labels: array<string, string>}>}>
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function readStoredTemplates(?HomeCustomization $customization = null): array
    {
        $customization ??= $this->homeCustomizationService->getOrCreateSingleton();

        return SiteMailTemplatesContract::decodeFromStorage($customization->getMailTemplatesJson());
    }

    /**
     * @brief Sanitize rich HTML blocks in a normalized templates map.
     *
     * @param array<string, array{fromEmail: string|null, fromName: string|null, locales: array<string, array{subject: string, blocks: array<string, string>, labels: array<string, string>}>}> $templates Templates map.
     * @return array<string, array{fromEmail: string|null, fromName: string|null, locales: array<string, array{subject: string, blocks: array<string, string>, labels: array<string, string>}>}>
     * @date 2026-06-16
     * @author Stephane H.
     */
    private function sanitizeTemplates(array $templates): array
    {
        foreach (SiteMailTemplatesContract::TEMPLATE_TYPES as $type) {
            foreach ($templates[$type]['locales'] as $locale => $localeRow) {
                foreach (SiteMailTemplatesContract::blockKeysForType($type) as $blockKey) {
                    $raw = $localeRow['blocks'][$blockKey] ?? '';
                    $templates[$type]['locales'][$locale]['blocks'][$blockKey] = $this->richHtmlSanitizer->sanitize($raw);
                }
            }
        }

        return $templates;
    }

    /**
     * @brief Merge stored templates with translation defaults for admin form display.
     *
     * @param array<string, array{fromEmail: string|null, fromName: string|null, locales: array<string, array{subject: string, blocks: array<string, string>, labels: array<string, string>}>}> $stored Stored templates.
     * @param array<string, array{fromEmail: string|null, fromName: string|null, locales: array<string, array{subject: string, blocks: array<string, string>, labels: array<string, string>}>}> $defaults Default templates.
     * @param list<string> $activeLocales Active locale codes.
     * @return array<string, array{fromEmail: string|null, fromName: string|null, locales: array<string, array{subject: string, blocks: array<string, string>, labels: array<string, string>}>}>
     * @date 2026-06-16
     * @author Stephane H.
     */
    private function mergeWithDefaults(array $stored, array $defaults, array $activeLocales): array
    {
        $view = $defaults;
        foreach (SiteMailTemplatesContract::TEMPLATE_TYPES as $type) {
            $view[$type]['fromEmail'] = $stored[$type]['fromEmail'];
            $view[$type]['fromName'] = $stored[$type]['fromName'];
            if (SiteMailTemplatesContract::supportsToEmail($type)) {
                $view[$type]['toEmail'] = $stored[$type]['toEmail'];
            }

            foreach ($activeLocales as $locale) {
                $storedLocale = $stored[$type]['locales'][$locale] ?? null;
                $defaultLocale = $defaults[$type]['locales'][$locale] ?? [
                    'subject' => '',
                    'blocks' => [],
                    'labels' => [],
                ];
                if (!is_array($storedLocale)) {
                    continue;
                }

                $view[$type]['locales'][$locale]['subject'] = $storedLocale['subject'] !== ''
                    ? $storedLocale['subject']
                    : $defaultLocale['subject'];

                foreach (SiteMailTemplatesContract::blockKeysForType($type) as $blockKey) {
                    $storedBlock = trim($storedLocale['blocks'][$blockKey] ?? '');
                    $view[$type]['locales'][$locale]['blocks'][$blockKey] = $storedBlock !== ''
                        ? $storedBlock
                        : ($defaultLocale['blocks'][$blockKey] ?? '');
                }

                foreach (SiteMailTemplatesContract::labelKeysForType($type) as $labelKey) {
                    $storedLabel = trim($storedLocale['labels'][$labelKey] ?? '');
                    $view[$type]['locales'][$locale]['labels'][$labelKey] = $storedLabel !== ''
                        ? $storedLabel
                        : ($defaultLocale['labels'][$labelKey] ?? '');
                }
            }
        }

        return $view;
    }

    /**
     * @brief Reject non-empty invalid sender addresses from admin POST payload.
     *
     * @param array<string, mixed> $submitted Raw `mail_templates` request map.
     * @return void
     * @date 2026-06-16
     * @author Stephane H.
     */
    private function assertSubmittedFromEmailsAreValid(array $submitted): void
    {
        foreach (SiteMailTemplatesContract::TEMPLATE_TYPES as $type) {
            if (!isset($submitted[$type]) || !is_array($submitted[$type])) {
                continue;
            }

            $rawFromEmail = trim((string) ($submitted[$type]['from_email'] ?? ''));
            if ($rawFromEmail !== '' && !SiteMailTemplatesContract::isValidFromEmail($rawFromEmail)) {
                throw new \InvalidArgumentException('dashboard.configuration_site.mail_templates.flash.invalid_from_email');
            }

            if (SiteMailTemplatesContract::supportsToEmail($type)) {
                $rawToEmail = trim((string) ($submitted[$type]['to_email'] ?? ''));
                if ($rawToEmail !== '' && !SiteMailTemplatesContract::isValidFromEmail($rawToEmail)) {
                    throw new \InvalidArgumentException('dashboard.configuration_site.mail_templates.flash.invalid_to_email');
                }
            }
        }
    }
}
