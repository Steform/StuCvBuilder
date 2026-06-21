<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Cv\CvAccessSessionService;
use App\Service\Cv\CvAccessTargetResolver;
use App\Service\Cv\CvBotSignalEvaluator;
use App\Service\Employment\CompanyCvVisitService;
use App\Service\Security\CaptchaService;
use App\Service\Security\CvBotAttestationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * CV public access gate (captcha and client signal verification).
 */
class CvAccessController extends AbstractController
{
    private const PHASE_CHECKING = 'checking';

    private const PHASE_CAPTCHA = 'captcha';

    private const SESSION_DEV_TECHNICAL_SCORE = 'cv_access.dev_technical_score';

    private const SESSION_DEV_THRESHOLD = 'cv_access.dev_threshold';

    /**
     * @brief Render or process CV access gate with phased UX (checking, success redirect, captcha).
     *
     * @param Request $request HTTP request (GET: target, format, phase; POST: signals and optional captcha).
     * @param CvAccessSessionService $cvAccessSessionService Session access helper.
     * @param CvAccessTargetResolver $cvAccessTargetResolver Safe redirect resolver.
     * @param CvBotSignalEvaluator $cvBotSignalEvaluator Bot signal scorer.
     * @param CaptchaService $captchaService Captcha verifier.
     * @param CvBotAttestationService $cvBotAttestation Signed session attestation helper.
     * @param CompanyCvVisitService $companyCvVisitService Company visit tracking service.
     * @return Response Redirect on grant, or gate HTML with accessPhase for GET.
     * @date 2026-05-19
     * @author Stephane H.
     */
    #[Route('/cv/access', name: 'cv_access', methods: ['GET', 'POST'])]
    public function access(
        Request $request,
        CvAccessSessionService $cvAccessSessionService,
        CvAccessTargetResolver $cvAccessTargetResolver,
        CvBotSignalEvaluator $cvBotSignalEvaluator,
        CaptchaService $captchaService,
        CvBotAttestationService $cvBotAttestation,
        CompanyCvVisitService $companyCvVisitService,
    ): Response {
        $rawFormat = (string) $request->query->get('format', $request->request->get('format', ''));
        $cvAccessSessionService->captureTargetFormatFromQuery($rawFormat, $request);

        if (!$cvAccessSessionService->isBypassGranted() && !$cvAccessSessionService->isAccessGranted()) {
            $companyCvVisitService->recordGateNotPassedAttempt($request);
        }

        $target = $cvAccessTargetResolver->resolveSafeTarget(
            (string) $request->query->get('target', $request->request->get('target', ''))
        );

        if ($cvAccessSessionService->isBypassGranted() || $cvAccessSessionService->isAccessGranted()) {
            return $this->redirect($target);
        }

        if ($request->isMethod('POST')) {
            return $this->handleAccessPost(
                $request,
                $cvAccessSessionService,
                $cvBotSignalEvaluator,
                $captchaService,
                $cvBotAttestation,
                $target,
                $rawFormat,
            );
        }

        $accessPhase = $this->resolveAccessPhase($request);

        return $this->render('cv/access.html.twig', [
            'target' => $target,
            'formatCode' => $cvAccessSessionService->resolveTargetFormatCode($rawFormat !== '' ? $rawFormat : null),
            'currentLocale' => $request->getLocale(),
            'accessPhase' => $accessPhase,
            'devAccessScore' => $this->resolveDevAccessScoreForTemplate($request, $accessPhase),
        ]);
    }

    /**
     * @brief Process gate POST: captcha, behavioural score, or redirect to captcha phase.
     *
     * @param Request $request HTTP POST request.
     * @param CvAccessSessionService $cvAccessSessionService Session access helper.
     * @param CvBotSignalEvaluator $cvBotSignalEvaluator Bot signal scorer.
     * @param CaptchaService $captchaService Captcha verifier.
     * @param CvBotAttestationService $cvBotAttestation Signed session attestation helper.
     * @param string $target Safe redirect path after grant.
     * @param string $rawFormat Raw format query value.
     * @return Response Redirect to CV, captcha phase, or access gate with flash.
     * @date 2026-05-19
     * @author Stephane H.
     */
    private function handleAccessPost(
        Request $request,
        CvAccessSessionService $cvAccessSessionService,
        CvBotSignalEvaluator $cvBotSignalEvaluator,
        CaptchaService $captchaService,
        CvBotAttestationService $cvBotAttestation,
        string $target,
        string $rawFormat,
    ): Response {
        $csrfToken = (string) $request->request->get('_csrf_token', '');
        if (!$this->isCsrfTokenValid('cv_access', $csrfToken)) {
            $this->addFlash('warning', 'cv.access.flash.invalid_csrf');

            return $this->redirectToCaptchaPhase($target, $rawFormat);
        }

        if ($captchaService->verifyCaptcha($request)) {
            $captchaService->removeCaptcha();
            $cvBotAttestation->issueFromCaptcha();
            $cvAccessSessionService->grantAccess();

            return $this->redirect($target);
        }

        $scoreResult = $cvBotSignalEvaluator->evaluateRequest($request);
        if ($scoreResult['eligibleForCounting'] ?? false) {
            $cvBotAttestation->issueFromSignals((int) ($scoreResult['technicalScore'] ?? 0));
            $cvAccessSessionService->grantAccess();

            return $this->redirect($target);
        }

        $this->addFlash('warning', 'cv.access.flash.denied');
        $this->storeDevAccessScoreInSession($request, $scoreResult);

        return $this->redirectToCaptchaPhase($target, $rawFormat, $scoreResult);
    }

    /**
     * @brief Resolve initial UI phase for GET /cv/access.
     *
     * @param Request $request HTTP GET request.
     * @return string checking or captcha.
     * @date 2026-05-19
     * @author Stephane H.
     */
    private function resolveAccessPhase(Request $request): string
    {
        if ($request->query->getString('phase') === self::PHASE_CAPTCHA) {
            return self::PHASE_CAPTCHA;
        }

        foreach ($request->getSession()->getFlashBag()->peek('warning') as $flash) {
            if ($flash === 'cv.access.flash.denied' || $flash === 'cv.access.flash.invalid_csrf') {
                return self::PHASE_CAPTCHA;
            }
        }

        return self::PHASE_CHECKING;
    }

    /**
     * @brief Redirect back to gate with captcha phase query parameter.
     *
     * @param string $target Safe redirect target preserved in query.
     * @param string $rawFormat Raw format query value.
     * @param array{technicalScore?: int, threshold?: int}|null $scoreResult Optional evaluator output for dev query params.
     * @return Response 302 to cv_access with phase=captcha.
     * @date 2026-05-19
     * @author Stephane H.
     */
    private function redirectToCaptchaPhase(string $target, string $rawFormat, ?array $scoreResult = null): Response
    {
        $params = [
            'target' => $target,
            'phase' => self::PHASE_CAPTCHA,
        ];
        if ($rawFormat !== '') {
            $params['format'] = $rawFormat;
        }

        if ($this->getParameter('kernel.debug') && $scoreResult !== null) {
            $params['dev_score'] = (int) ($scoreResult['technicalScore'] ?? 0);
            $params['dev_threshold'] = (int) ($scoreResult['threshold'] ?? 0);
        }

        return $this->redirectToRoute('cv_access', $params);
    }

    /**
     * @brief Persist last technical score in session for dev-only captcha phase display.
     *
     * @param Request $request HTTP request with session.
     * @param array{technicalScore?: int, threshold?: int} $scoreResult Evaluator output.
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    private function storeDevAccessScoreInSession(Request $request, array $scoreResult): void
    {
        $session = $request->getSession();
        $session->set(self::SESSION_DEV_TECHNICAL_SCORE, (int) ($scoreResult['technicalScore'] ?? 0));
        $session->set(self::SESSION_DEV_THRESHOLD, (int) ($scoreResult['threshold'] ?? 0));
    }

    /**
     * @brief Build dev score payload for Twig when captcha phase is shown in development.
     *
     * @param Request $request HTTP GET request.
     * @param string $accessPhase Resolved UI phase (checking or captcha).
     * @return array{score: int, threshold: int}|null Null outside dev or when no score stored.
     * @date 2026-05-19
     * @author Stephane H.
     */
    private function resolveDevAccessScoreForTemplate(Request $request, string $accessPhase): ?array
    {
        if (!$this->getParameter('kernel.debug') || $accessPhase !== self::PHASE_CAPTCHA) {
            return null;
        }

        if ($request->query->has('dev_score')) {
            return [
                'score' => $request->query->getInt('dev_score'),
                'threshold' => $request->query->getInt('dev_threshold'),
            ];
        }

        $session = $request->getSession();
        if (!$session->has(self::SESSION_DEV_TECHNICAL_SCORE)) {
            return null;
        }

        return [
            'score' => (int) $session->get(self::SESSION_DEV_TECHNICAL_SCORE, 0),
            'threshold' => (int) $session->get(self::SESSION_DEV_THRESHOLD, 0),
        ];
    }
}
