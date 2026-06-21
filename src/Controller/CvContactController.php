<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Cv\CvContactSubmissionService;
use App\Service\Notification\CvContactEmailNotificationService;
use App\Service\Security\CaptchaService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Handles public CV contact form submissions.
 */
class CvContactController extends AbstractController
{
    /**
     * @brief Build CV contact controller.
     *
     * @param CvContactSubmissionService $submissionService Contact field validator.
     * @param CvContactEmailNotificationService $contactEmailService Outbound mail service.
     * @param CaptchaService $captchaService Session captcha verifier.
     * @param RateLimiterFactory $cvContactLimiter Rate limiter for contact POST.
     * @return void
     * @date 2026-05-23
     * @author Stephane H.
     */
    public function __construct(
        private readonly CvContactSubmissionService $submissionService,
        private readonly CvContactEmailNotificationService $contactEmailService,
        private readonly CaptchaService $captchaService,
        private readonly RateLimiterFactory $cvContactLimiter,
    ) {
    }

    /**
     * @brief Process contact modal form POST and redirect back to the CV page.
     *
     * @param Request $request HTTP request with contact fields and captcha.
     * @return Response Redirect to CV show route with flash feedback.
     * @date 2026-05-23
     * @author Stephane H.
     */
    #[Route('/cv/contact', name: 'cv_contact_submit', methods: ['POST'])]
    public function submit(Request $request): Response
    {
        $redirectUrl = $this->generateUrl('cv_show', [], UrlGeneratorInterface::ABSOLUTE_PATH).'#about';

        if (!$this->isCsrfTokenValid('cv_contact', (string) $request->request->get('_csrf_token', ''))) {
            $this->addFlash('error', 'cv.contact.flash.invalid_csrf');

            return $this->redirect($redirectUrl);
        }

        $limiter = $this->cvContactLimiter->create($request->getClientIp() ?? 'unknown');
        if (!$limiter->consume(1)->isAccepted()) {
            $this->addFlash('error', 'cv.contact.flash.rate_limited');

            return $this->redirect($redirectUrl);
        }

        if (!$this->captchaService->verifyCaptcha($request)) {
            $this->addFlash('error', 'cv.contact.flash.captcha_invalid');

            return $this->redirect($redirectUrl);
        }

        $validation = $this->submissionService->validate($request->request->all());
        if ($validation['errorKey'] !== null) {
            $this->addFlash('error', $validation['errorKey']);

            return $this->redirect($redirectUrl);
        }

        $locale = (string) $request->getLocale();
        $sent = $this->contactEmailService->sendContactMessage($validation['data'], $locale);
        $this->captchaService->removeCaptcha();

        if (!$sent) {
            $this->addFlash('error', 'cv.contact.flash.mail_failed');

            return $this->redirect($redirectUrl);
        }

        $this->addFlash('success', 'cv.contact.flash.success');

        return $this->redirect($redirectUrl);
    }
}
