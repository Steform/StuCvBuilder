<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Service\Site\SiteMailTemplateResolverService;
use App\Site\SiteMailTemplatesContract;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * Sends CV public contact form messages to the site owner inbox.
 */
class CvContactEmailNotificationService
{
    /**
     * @brief Build CV contact email notification service.
     *
     * @param MailerInterface|null $mailer Mailer transport service.
     * @param SiteMailTemplateResolverService|null $mailTemplateResolver Mail template resolver.
     * @param string $toEmail Recipient address for contact messages.
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
        private readonly string $toEmail = '',
        private readonly array $supportedLocales = ['fr', 'en', 'de', 'lt', 'no'],
        private readonly string $defaultLocale = 'en',
        private readonly string $fallbackLocale = 'fr',
    ) {
    }

    /**
     * @brief Send contact form notification email.
     *
     * @param array{name: string, email: string, subject: string, message: string} $submission Validated submission.
     * @param string|null $locale Visitor locale for email copy.
     * @return bool True when message was handed to the mailer.
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function sendContactMessage(array $submission, ?string $locale = null): bool
    {
        if (!$this->mailer instanceof MailerInterface || !$this->mailTemplateResolver instanceof SiteMailTemplateResolverService) {
            return false;
        }

        $recipient = trim($this->toEmail);
        if ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $resolvedLocale = $this->resolveSupportedLocale($locale);
        $visitorEmail = $submission['email'];
        $visitorName = $submission['name'];
        $subjectLine = $submission['subject'];

        $resolved = $this->mailTemplateResolver->resolve(
            SiteMailTemplatesContract::TYPE_CV_CONTACT,
            $resolvedLocale,
            ['%subject%' => $subjectLine]
        );
        $plainBlocks = $this->buildPlainBlocks($resolved['blocks']);

        $emailMessage = (new TemplatedEmail())
            ->from(new Address($resolved['fromEmail'], $resolved['fromName']))
            ->to(new Address($recipient))
            ->replyTo(new Address($visitorEmail, $visitorName))
            ->subject($resolved['subject'])
            ->htmlTemplate('emails/cv_contact.html.twig')
            ->textTemplate('emails/cv_contact.txt.twig')
            ->context([
                'locale' => $resolved['locale'],
                'visitorName' => $visitorName,
                'visitorEmail' => $visitorEmail,
                'subjectLine' => $subjectLine,
                'messageBody' => $submission['message'],
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
     * @date 2026-05-23
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
