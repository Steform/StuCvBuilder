<?php

namespace App\Controller;

use App\Service\Cv\CvAccessSessionService;
use App\Service\Cv\CvAccessTargetResolver;
use App\Service\Security\CaptchaService;
use App\Service\Security\CvBotAccessService;
use App\Service\Security\CvBotAttestationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Captcha attestation page when behavioural score is below threshold.
 */
class CvAttestationController extends AbstractController
{
    public function __construct(
        private readonly CvAccessTargetResolver $cvAccessTargetResolver,
    ) {
    }

    /**
     * @brief Show captcha challenge for CV access attestation.
     *
     * @param Request $request HTTP request (return_to query).
     * @param CvBotAccessService $cvBotAccess Access policy service.
     * @return Response Attestation form or redirect when already allowed.
     * @date 2026-05-19
     * @author Stephane H.
     */
    #[Route('/cv/attestation', name: 'cv_attestation', methods: ['GET'])]
    public function show(Request $request, CvBotAccessService $cvBotAccess): Response
    {
        if ($cvBotAccess->isBypassAllowed() || $cvBotAccess->canViewCv()) {
            return $this->redirect($this->resolveReturnTo($request));
        }

        return $this->render('cv/attestation.html.twig', [
            'returnTo' => $this->resolveReturnTo($request),
            'currentLocale' => $request->getLocale(),
        ]);
    }

    /**
     * @brief Verify captcha and issue session attestation on success.
     *
     * @param Request $request POST with captcha_code.
     * @param CaptchaService $captchaService Captcha verification service.
     * @param CvBotAccessService $cvBotAccess Access policy service.
     * @param CvBotAttestationService $cvBotAttestation Attestation session service.
     * @param CvAccessSessionService $cvAccessSessionService Session access helper.
     * @return Response Redirect to CV or re-render form with error.
     * @date 2026-05-19
     * @author Stephane H.
     */
    #[Route('/cv/attestation', name: 'cv_attestation_submit', methods: ['POST'])]
    public function submit(
        Request $request,
        CaptchaService $captchaService,
        CvBotAccessService $cvBotAccess,
        CvBotAttestationService $cvBotAttestation,
        CvAccessSessionService $cvAccessSessionService,
        CsrfTokenManagerInterface $csrfTokenManager,
    ): Response {
        if ($cvBotAccess->isBypassAllowed()) {
            return $this->redirect($this->resolveReturnTo($request));
        }

        $submittedToken = (string) $request->request->get('_csrf_token', '');
        if (!$csrfTokenManager->isTokenValid(new CsrfToken('cv_attestation', $submittedToken))) {
            throw $this->createAccessDeniedException();
        }

        if (!$captchaService->verifyCaptcha($request)) {
            $this->addFlash('error', 'cv.antibot.captcha_invalid');

            return $this->render('cv/attestation.html.twig', [
                'returnTo' => $this->resolveReturnTo($request),
                'currentLocale' => $request->getLocale(),
            ]);
        }

        $captchaService->removeCaptcha();
        $cvBotAttestation->issueFromCaptcha();
        $cvAccessSessionService->grantAccess();

        return $this->redirect($this->resolveReturnTo($request));
    }

    /**
     * @brief Resolve safe internal return path.
     *
     * @param Request $request HTTP request.
     * @return string Path starting with /.
     * @date 2026-05-19
     * @author Stephane H.
     */
    private function resolveReturnTo(Request $request): string
    {
        $candidate = (string) $request->request->get('return_to', $request->query->get('return_to', ''));

        return $this->cvAccessTargetResolver->resolveSafeTarget($candidate);
    }
}
