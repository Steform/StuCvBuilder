<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Notification;

use App\Entity\CompanyCvVisit;
use App\Entity\TrackedCompany;
use App\Service\Notification\RecruiterVisitEmailNotificationService;
use App\Service\Site\SiteMailTemplateResolverService;
use App\Site\SiteMailTemplatesContract;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * @brief Unit tests for {@see RecruiterVisitEmailNotificationService}.
 */
final class RecruiterVisitEmailNotificationServiceTest extends TestCase
{
    /**
     * @brief Service sends templated recruiter visit notification synchronously.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function testSendOfficialVisitNotificationUsesResolvedTemplate(): void
    {
        $company = new TrackedCompany('Ab3xY9kLm2Qp', 'Acme Corp');
        $visit = new CompanyCvVisit(
            $company,
            new DateTimeImmutable('2026-06-16'),
            'visitor-key',
            new DateTimeImmutable('2026-06-16 10:00:00'),
            '127.0.0.1',
            'FR',
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
                $from = $email->getFrom();
                $firstFrom = $from[0] ?? null;

                return $email->getHtmlTemplate() === 'emails/recruiter_visit.html.twig'
                    && $email->getTextTemplate() === 'emails/recruiter_visit.txt.twig'
                    && $email->getSubject() === 'Recruiter visit subject'
                    && $firstTo instanceof Address
                    && $firstTo->getAddress() === 'alerts@example.test'
                    && $firstFrom instanceof Address
                    && $firstFrom->getAddress() === 'from@example.test';
            }));

        $resolver = $this->createMock(SiteMailTemplateResolverService::class);
        $resolver->method('resolve')
            ->with(
                SiteMailTemplatesContract::TYPE_RECRUITER_VISIT,
                'fr',
                self::callback(static fn (array $params): bool => $params['%company_name%'] === 'Acme Corp'),
            )
            ->willReturn([
                'locale' => 'fr',
                'fromEmail' => 'from@example.test',
                'fromName' => 'CV',
                'toEmail' => 'alerts@example.test',
                'subject' => 'Recruiter visit subject',
                'blocks' => ['intro' => '<p>Intro</p>'],
                'labels' => ['field_company' => 'Company'],
            ]);
        $resolver->method('toPlainText')->willReturn('Intro');

        $service = new RecruiterVisitEmailNotificationService($mailer, $resolver);

        self::assertTrue($service->sendOfficialVisitNotification(
            $company,
            $visit,
            'fr',
            'https://example.test/admin/visits',
        ));
    }
}
