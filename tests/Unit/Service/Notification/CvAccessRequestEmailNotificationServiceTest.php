<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Notification;

use App\Entity\CvAccessRequest;
use App\Service\Notification\CvAccessRequestEmailNotificationService;
use App\Service\Site\SiteMailTemplateResolverService;
use App\Site\SiteMailTemplatesContract;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * @brief Unit tests for {@see CvAccessRequestEmailNotificationService}.
 */
final class CvAccessRequestEmailNotificationServiceTest extends TestCase
{
    /**
     * @brief Service sends templated access-request notification synchronously.
     *
     * @return void
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function testSendAccessRequestNotificationUsesResolvedTemplate(): void
    {
        $request = new CvAccessRequest(
            'Acme Corp',
            'Jane Doe',
            'jane@example.com',
            'FR',
            'Please review',
        );

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())
            ->method('send')
            ->with(self::callback(static function (mixed $email): bool {
                if (!$email instanceof TemplatedEmail) {
                    return false;
                }

                $to = $email->getTo();
                $firstTo = $to[0] ?? null;
                $replyTo = $email->getReplyTo();
                $firstReply = $replyTo[0] ?? null;

                return $email->getHtmlTemplate() === 'emails/cv_access_request.html.twig'
                    && $email->getTextTemplate() === 'emails/cv_access_request.txt.twig'
                    && $email->getSubject() === 'Access request subject'
                    && $firstTo instanceof Address
                    && $firstTo->getAddress() === 'alerts@example.test'
                    && $firstReply instanceof Address
                    && $firstReply->getAddress() === 'jane@example.com';
            }));

        $resolver = $this->createMock(SiteMailTemplateResolverService::class);
        $resolver->method('resolve')
            ->with(
                SiteMailTemplatesContract::TYPE_CV_ACCESS_REQUEST,
                'fr',
                self::callback(static fn (array $params): bool => $params['%company_name%'] === 'Acme Corp'),
            )
            ->willReturn([
                'locale' => 'fr',
                'fromEmail' => 'from@example.test',
                'fromName' => 'CV',
                'toEmail' => 'alerts@example.test',
                'subject' => 'Access request subject',
                'blocks' => ['intro' => '<p>Intro</p>'],
                'labels' => ['field_company' => 'Company'],
            ]);
        $resolver->method('toPlainText')->willReturn('Intro');

        $service = new CvAccessRequestEmailNotificationService($mailer, $resolver);

        self::assertTrue($service->sendAccessRequestNotification(
            $request,
            'fr',
            'https://example.test/admin/employment/access-requests',
        ));
    }
}
