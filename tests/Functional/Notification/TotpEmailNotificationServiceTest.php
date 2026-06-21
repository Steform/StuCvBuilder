<?php

namespace App\Tests\Functional\Notification;

use App\Service\Notification\TotpEmailNotificationService;
use App\Service\Site\SiteMailTemplateResolverService;
use App\Site\SiteMailTemplatesContract;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class TotpEmailNotificationServiceTest extends TestCase
{
    /**
     * @brief Ensure service stores outgoing notification payload and uses resolved template.
     * @param void No input parameter.
     * @return void
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function testSendTotpCodeStoresMessage(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())
            ->method('send')
            ->with(self::callback(static function (mixed $email): bool {
                if (!$email instanceof TemplatedEmail) {
                    return false;
                }

                $from = $email->getFrom();
                $firstFrom = $from[0] ?? null;

                return $email->getHtmlTemplate() === 'emails/totp_code.html.twig'
                    && $email->getTextTemplate() === 'emails/totp_code.txt.twig'
                    && $email->getSubject() === 'Translated subject'
                    && $firstFrom instanceof Address
                    && $firstFrom->getAddress() === 'custom@example.test'
                    && $firstFrom->getName() === 'Custom Brand';
            }));

        $resolver = $this->createMock(SiteMailTemplateResolverService::class);
        $resolver->method('resolve')
            ->with(SiteMailTemplatesContract::TYPE_TOTP, null)
            ->willReturn([
                'locale' => 'fr',
                'fromEmail' => 'custom@example.test',
                'fromName' => 'Custom Brand',
                'subject' => 'Translated subject',
                'blocks' => ['title' => '<h2>Title</h2>'],
                'labels' => ['brand' => 'Brand', 'code_label' => 'Code'],
            ]);
        $resolver->method('toPlainText')->willReturnCallback(static fn (string $html): string => trim(strip_tags($html)));

        $service = new TotpEmailNotificationService($mailer, $resolver);
        $service->sendTotpCode('admin@example.com', '123456');

        self::assertCount(1, $service->getMessages());
        self::assertSame('admin@example.com', $service->getMessages()[0]['email']);
        self::assertSame('123456', $service->getMessages()[0]['code']);
    }
}
