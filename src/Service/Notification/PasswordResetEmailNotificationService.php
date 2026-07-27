<?php

namespace App\Service\Notification;

use App\Service\Site\SiteMailTemplateResolverService;
use App\Site\SiteMailTemplatesContract;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * Service PasswordResetEmailNotificationService.
 */
class PasswordResetEmailNotificationService
{
    /**
     * @var list<array{email: string, resetUrl: string}>
     */
    private array $messages = [];

    /**
     * @brief Build password reset email notification service.
     * @param MailerInterface|null $mailer Mailer transport service.
     * @param SiteMailTemplateResolverService|null $mailTemplateResolver Mail template resolver.
     * @param list<string> $supportedLocales Supported locale codes.
     * @param string $defaultLocale Default locale fallback.
     * @param string $fallbackLocale Secondary locale fallback.
     * @return void
     * @date 2026-07-27
     * @author Stephane H.
     */
    public function __construct(
        private readonly ?MailerInterface $mailer = null,
        private readonly ?SiteMailTemplateResolverService $mailTemplateResolver = null,
        private readonly array $supportedLocales = ['fr', 'en', 'de', 'lt', 'nb'],
        private readonly string $defaultLocale = 'en',
        private readonly string $fallbackLocale = 'fr'
    ) {
    }

    /**
     * @brief Register and send password reset email payload.
     * @param string $email Target email.
     * @param string $resetUrl Password reset URL.
     * @param string|null $locale Preferred recipient locale.
     * @return void
     * @date 2026-07-27
     * @author Stephane H.
     */
    public function sendPasswordReset(string $email, string $resetUrl, ?string $locale = null): void
    {
        $normalizedEmail = strtolower(trim($email));
        $normalizedResetUrl = trim($resetUrl);
        if ($normalizedEmail === '' || $normalizedResetUrl === '') {
            return;
        }
        $resolvedLocale = $this->resolveSupportedLocale($locale);

        $this->messages[] = [
            'email' => $normalizedEmail,
            'resetUrl' => $normalizedResetUrl,
        ];

        if (!$this->mailer instanceof MailerInterface || !$this->mailTemplateResolver instanceof SiteMailTemplateResolverService) {
            return;
        }

        $resolved = $this->mailTemplateResolver->resolve(SiteMailTemplatesContract::TYPE_PASSWORD_RESET, $resolvedLocale);
        $plainBlocks = $this->buildPlainBlocks($resolved['blocks']);

        $emailMessage = (new TemplatedEmail())
            ->from(new Address($resolved['fromEmail'], $resolved['fromName']))
            ->to(new Address($normalizedEmail))
            ->subject($resolved['subject'])
            ->htmlTemplate('emails/password_reset.html.twig')
            ->textTemplate('emails/password_reset.txt.twig')
            ->context([
                'resetUrl' => $normalizedResetUrl,
                'locale' => $resolved['locale'],
                'blocks' => $resolved['blocks'],
                'labels' => $resolved['labels'],
                'plainBlocks' => $plainBlocks,
            ]);
        $this->mailer->send($emailMessage);
    }

    /**
     * @brief Resolve a supported locale with fallback strategy.
     * @param string|null $locale Preferred locale candidate.
     * @return string
     * @date 2026-07-27
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
     * @brief Return all queued password reset messages.
     * @return list<array{email: string, resetUrl: string}>
     * @date 2026-07-27
     * @author Stephane H.
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * @brief Convert resolved HTML blocks to plain text for text templates.
     *
     * @param array<string, string> $blocks Resolved HTML blocks.
     * @return array<string, string> Plain-text blocks.
     * @date 2026-07-27
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
