<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\CompanyCvVisit;
use App\Entity\TrackedCompany;
use App\Service\Site\SiteMailTemplateResolverService;
use App\Site\SiteMailTemplatesContract;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * Sends synchronous recruiter visit notifications to the site owner inbox.
 */
final class RecruiterVisitEmailNotificationService
{
    /**
     * @brief Build recruiter visit email notification service.
     *
     * @param MailerInterface|null $mailer Mailer transport service.
     * @param SiteMailTemplateResolverService|null $mailTemplateResolver Mail template resolver.
     * @param string $fallbackToEmail Fallback recipient when template toEmail is empty.
     * @param list<string> $supportedLocales Supported UI locales.
     * @param string $defaultLocale Default locale fallback.
     * @param string $fallbackLocale Secondary locale fallback.
     * @return void
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function __construct(
        private readonly ?MailerInterface $mailer = null,
        private readonly ?SiteMailTemplateResolverService $mailTemplateResolver = null,
        private readonly string $fallbackToEmail = '',
        private readonly array $supportedLocales = ['fr', 'en', 'de', 'lt', 'no'],
        private readonly string $defaultLocale = 'en',
        private readonly string $fallbackLocale = 'fr',
    ) {
    }

    /**
     * @brief Send official recruiter visit notification email synchronously.
     *
     * @param TrackedCompany $company Tracked company.
     * @param CompanyCvVisit $visit Official visit row.
     * @param string|null $locale Preferred email locale.
     * @param string $adminVisitsUrl Absolute admin visits page URL.
     * @return bool True when message was handed to the mailer.
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function sendOfficialVisitNotification(
        TrackedCompany $company,
        CompanyCvVisit $visit,
        ?string $locale,
        string $adminVisitsUrl,
    ): bool {
        if (!$this->mailer instanceof MailerInterface || !$this->mailTemplateResolver instanceof SiteMailTemplateResolverService) {
            return false;
        }

        $resolvedLocale = $this->resolveSupportedLocale($locale);
        $companyName = $company->getName();
        $companyCode = $company->getCode();
        $visitDate = $visit->getVisitDate()->format('Y-m-d');
        $countryCode = $visit->getCountryCode() ?? '';

        $parameters = [
            '%company_name%' => $companyName,
            '%company_code%' => $companyCode,
            '%visit_date%' => $visitDate,
            '%country_code%' => $countryCode,
        ];

        $resolved = $this->mailTemplateResolver->resolve(
            SiteMailTemplatesContract::TYPE_RECRUITER_VISIT,
            $resolvedLocale,
            $parameters,
        );

        $recipient = trim((string) ($resolved['toEmail'] ?? ''));
        if ($recipient === '') {
            $recipient = trim($this->fallbackToEmail);
        }
        if ($recipient === '' || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        $plainBlocks = $this->buildPlainBlocks($resolved['blocks']);

        $emailMessage = (new TemplatedEmail())
            ->from(new Address($resolved['fromEmail'], $resolved['fromName']))
            ->to(new Address($recipient))
            ->subject($resolved['subject'])
            ->htmlTemplate('emails/recruiter_visit.html.twig')
            ->textTemplate('emails/recruiter_visit.txt.twig')
            ->context([
                'locale' => $resolved['locale'],
                'subject' => $resolved['subject'],
                'companyName' => $companyName,
                'companyCode' => $companyCode,
                'visitDate' => $visitDate,
                'countryCode' => $countryCode,
                'adminVisitsUrl' => $adminVisitsUrl,
                'blocks' => $resolved['blocks'],
                'labels' => $resolved['labels'],
                'plainBlocks' => $plainBlocks,
            ]);

        $this->mailer->send($emailMessage);

        return true;
    }

    /**
     * @brief Resolve a supported locale with fallback strategy.
     *
     * @param string|null $locale Preferred locale candidate.
     * @return string Resolved locale code.
     * @date 2026-06-16
     * @author Stephane H.
     */
    private function resolveSupportedLocale(?string $locale): string
    {
        $normalizedLocale = strtolower(trim((string) $locale));
        if (in_array($normalizedLocale, $this->supportedLocales, true)) {
            return $normalizedLocale;
        }
        if (in_array($this->defaultLocale, $this->supportedLocales, true)) {
            return $this->defaultLocale;
        }
        if (in_array($this->fallbackLocale, $this->supportedLocales, true)) {
            return $this->fallbackLocale;
        }

        return $this->supportedLocales[0] ?? 'en';
    }

    /**
     * @brief Convert resolved HTML blocks to plain text for text templates.
     *
     * @param array<string, string> $blocks Resolved HTML blocks.
     * @return array<string, string> Plain-text blocks.
     * @date 2026-06-16
     * @author Stephane H.
     */
    private function buildPlainBlocks(array $blocks): array
    {
        $plainBlocks = [];
        foreach ($blocks as $key => $html) {
            $plainBlocks[$key] = $this->mailTemplateResolver->toPlainText($html);
        }

        return $plainBlocks;
    }
}
