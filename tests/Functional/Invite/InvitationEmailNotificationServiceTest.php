<?php

namespace App\Tests\Functional\Invite;

use App\Service\Notification\InvitationEmailNotificationService;
use App\Service\Site\SiteMailTemplateResolverService;
use App\Site\SiteMailTemplatesContract;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;

class InvitationEmailNotificationServiceTest extends TestCase
{
    /**
     * @brief Ensure invitation subject uses requested locale through resolver.
     * @param void No input parameter.
     * @return void
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function testSendInvitationUsesRequestedLocale(): void
    {
        $capturedMessage = null;
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())
            ->method('send')
            ->with(self::callback(static function (RawMessage $message) use (&$capturedMessage): bool {
                $capturedMessage = $message;

                return $message instanceof TemplatedEmail;
            }));

        $resolver = $this->createMock(SiteMailTemplateResolverService::class);
        $resolver->method('resolve')
            ->with(SiteMailTemplatesContract::TYPE_INVITATION, 'de')
            ->willReturn($this->resolvedPayload('de', 'subject-de', 'brand-de', 'invite-custom@example.test'));
        $resolver->method('toPlainText')->willReturn('plain');

        $service = new InvitationEmailNotificationService(
            $mailer,
            $resolver,
            ['fr', 'en', 'de', 'lt', 'no'],
            'en',
            'fr'
        );
        $service->sendInvitation('invite@example.com', 'https://example.test/invite/token', 'de');

        self::assertInstanceOf(TemplatedEmail::class, $capturedMessage);
        self::assertSame('subject-de', $capturedMessage->getSubject());
    }

    /**
     * @brief Ensure invalid locale falls back to configured default locale.
     * @param void No input parameter.
     * @return void
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function testSendInvitationFallsBackToDefaultLocaleWhenInvalid(): void
    {
        $capturedMessage = null;
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())
            ->method('send')
            ->with(self::callback(static function (RawMessage $message) use (&$capturedMessage): bool {
                $capturedMessage = $message;

                return $message instanceof TemplatedEmail;
            }));

        $resolver = $this->createMock(SiteMailTemplateResolverService::class);
        $resolver->method('resolve')
            ->with(SiteMailTemplatesContract::TYPE_INVITATION, 'en')
            ->willReturn($this->resolvedPayload('en', 'subject-en', 'brand-en', 'invite-custom@example.test'));
        $resolver->method('toPlainText')->willReturn('plain');

        $service = new InvitationEmailNotificationService(
            $mailer,
            $resolver,
            ['fr', 'en', 'de', 'lt', 'no'],
            'en',
            'fr'
        );
        $service->sendInvitation('invite@example.com', 'https://example.test/invite/token', 'es');

        self::assertInstanceOf(TemplatedEmail::class, $capturedMessage);
        self::assertSame('subject-en', $capturedMessage->getSubject());
    }

    /**
     * @brief Ensure missing locale falls back to configured default locale.
     * @param void No input parameter.
     * @return void
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function testSendInvitationFallsBackToDefaultLocaleWhenMissing(): void
    {
        $capturedMessage = null;
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())
            ->method('send')
            ->with(self::callback(static function (RawMessage $message) use (&$capturedMessage): bool {
                $capturedMessage = $message;

                return $message instanceof TemplatedEmail;
            }));

        $resolver = $this->createMock(SiteMailTemplateResolverService::class);
        $resolver->method('resolve')
            ->with(SiteMailTemplatesContract::TYPE_INVITATION, 'en')
            ->willReturn($this->resolvedPayload('en', 'subject-en', 'brand-en', 'invite-custom@example.test'));
        $resolver->method('toPlainText')->willReturn('plain');

        $service = new InvitationEmailNotificationService(
            $mailer,
            $resolver,
            ['fr', 'en', 'de', 'lt', 'no'],
            'en',
            'fr'
        );
        $service->sendInvitation('invite@example.com', 'https://example.test/invite/token', null);

        self::assertInstanceOf(TemplatedEmail::class, $capturedMessage);
        self::assertSame('subject-en', $capturedMessage->getSubject());
    }

    /**
     * @brief Build resolver payload for invitation tests.
     *
     * @param string $locale Resolved locale.
     * @param string $subject Resolved subject.
     * @param string $fromName Resolved sender name.
     * @param string $fromEmail Resolved sender email.
     * @return array{
     *     locale: string,
     *     fromEmail: string,
     *     fromName: string,
     *     subject: string,
     *     blocks: array<string, string>,
     *     labels: array<string, string>
     * }
     * @date 2026-06-16
     * @author Stephane H.
     */
    private function resolvedPayload(string $locale, string $subject, string $fromName, string $fromEmail): array
    {
        return [
            'locale' => $locale,
            'fromEmail' => $fromEmail,
            'fromName' => $fromName,
            'subject' => $subject,
            'blocks' => [
                'title' => '<h2>Title</h2>',
                'intro' => '<p>Intro</p>',
                'expiry_hint' => '<p>Expiry</p>',
                'security_hint' => '<p>Security</p>',
                'footer' => '<p>Footer</p>',
            ],
            'labels' => [
                'cta' => 'Activate',
            ],
        ];
    }
}
