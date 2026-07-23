<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Cv\CvAccessRequestService;
use App\Service\Notification\CvAccessRequestEmailNotificationService;
use App\Service\Security\CaptchaService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @brief Handles public CV access-request form submissions.
 *
 * @date 2026-07-22
 * @author Stephane H.
 */
class CvAccessRequestController extends AbstractController
{
    /**
     * @brief Build CV access request controller.
     *
     * @param CvAccessRequestService $accessRequestService Access request processor.
     * @param CvAccessRequestEmailNotificationService $emailNotificationService Admin mail notifier.
     * @param CaptchaService $captchaService Session captcha verifier.
     * @param RateLimiterFactory $cvAccessRequestLimiter Rate limiter for access-request POST.
     * @return void
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function __construct(
        private readonly CvAccessRequestService $accessRequestService,
        private readonly CvAccessRequestEmailNotificationService $emailNotificationService,
        private readonly CaptchaService $captchaService,
        private readonly RateLimiterFactory $cvAccessRequestLimiter,
    ) {
    }

    /**
     * @brief Process access-request form POST and redirect back to the denied CV page.
     *
     * @param Request $request HTTP request with access-request fields and captcha.
     * @return Response Redirect to CV show route with flash feedback.
     * @date 2026-07-22
     * @author Stephane H.
     */
    #[Route('/cv/access-request', name: 'cv_access_request_submit', methods: ['POST'])]
    public function submit(Request $request): Response
    {
        $redirectUrl = $this->generateUrl('cv_show', [], UrlGeneratorInterface::ABSOLUTE_PATH);

        if (!$this->isCsrfTokenValid('cv_access_request', (string) $request->request->get('_csrf_token', ''))) {
            $this->addFlash('error', 'cv.access_request.flash.invalid_csrf');

            return $this->redirect($redirectUrl);
        }

        $limiter = $this->cvAccessRequestLimiter->create($request->getClientIp() ?? 'unknown');
        if (!$limiter->consume(1)->isAccepted()) {
            $this->addFlash('error', 'cv.access_request.flash.rate_limited');

            return $this->redirect($redirectUrl);
        }

        if (!$this->captchaService->verifyCaptcha($request)) {
            $this->addFlash('error', 'cv.access_request.flash.captcha_invalid');

            return $this->redirect($redirectUrl);
        }

        $result = $this->accessRequestService->submit(
            $request->request->all(),
            $request->getClientIp(),
            (string) $request->getLocale(),
        );

        if ($result['errorKey'] !== null || $result['request'] === null) {
            $this->addFlash('error', $result['errorKey'] ?? 'cv.access_request.flash.submit_failed');

            return $this->redirect($redirectUrl);
        }

        $adminUrl = $this->generateUrl(
            'admin_employment_access_requests_index',
            ['status' => 'pending'],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
        $sent = $this->emailNotificationService->sendAccessRequestNotification(
            $result['request'],
            (string) $request->getLocale(),
            $adminUrl,
        );
        $this->captchaService->removeCaptcha();

        if (!$sent) {
            $this->addFlash('warning', 'cv.access_request.flash.mail_failed');
        } else {
            $this->addFlash('success', 'cv.access_request.flash.success');
        }

        return $this->redirect($redirectUrl);
    }
}
