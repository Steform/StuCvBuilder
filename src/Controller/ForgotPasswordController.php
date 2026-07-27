<?php

namespace App\Controller;

use App\Service\Auth\PasswordResetService;
use App\Service\Http\FlashMessageHelper;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

/**
 * Controller ForgotPasswordController.
 */
class ForgotPasswordController
{
    private const CSRF_FORGOT_PASSWORD = 'forgot_password_submit';

    /**
     * @brief Build forgot password controller.
     * @param PasswordResetService $passwordResetService Password reset service.
     * @param CsrfTokenManagerInterface $csrfTokenManager CSRF token manager.
     * @param RateLimiterFactory $forgotPasswordLimiter Forgot password rate limiter factory.
     * @return void
     * @date 2026-07-27
     * @author Stephane H.
     */
    public function __construct(
        private readonly PasswordResetService $passwordResetService,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly RateLimiterFactory $forgotPasswordLimiter
    ) {
    }

    /**
     * @brief Render forgot password form.
     * @param Environment $twig Twig environment.
     * @return Response
     * @date 2026-07-27
     * @author Stephane H.
     */
    #[Route('/forgot-password', name: 'forgot_password', methods: ['GET'])]
    public function index(Environment $twig): Response
    {
        return new Response($twig->render('security/forgot_password.html.twig', [
            'csrfForgotPassword' => self::CSRF_FORGOT_PASSWORD,
        ]));
    }

    /**
     * @brief Submit forgot password request.
     * @param Request $request HTTP request payload.
     * @return Response
     * @date 2026-07-27
     * @author Stephane H.
     */
    #[Route('/forgot-password', name: 'forgot_password_submit', methods: ['POST'])]
    public function submit(Request $request): Response
    {
        $tokenValue = (string) $request->request->get('_csrf_token', '');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_FORGOT_PASSWORD, $tokenValue))) {
            $this->addRequestFlash($request, 'danger', 'security.forgot_password.invalid_payload');

            return new RedirectResponse('/forgot-password');
        }

        $limiter = $this->forgotPasswordLimiter->create($request->getClientIp() ?? 'unknown');
        if (!$limiter->consume()->isAccepted()) {
            $this->addRequestFlash($request, 'danger', 'security.forgot_password.rate_limited');

            return new RedirectResponse('/forgot-password');
        }

        $email = trim((string) $request->request->get('email', ''));
        $this->passwordResetService->requestReset($email, $request->getLocale());
        $this->addRequestFlash($request, 'success', 'security.forgot_password.success');

        return new RedirectResponse('/forgot-password');
    }

    /**
     * @brief Add one flash message when request session exists.
     * @param Request $request Current HTTP request.
     * @param string $type Flash type key.
     * @param string $message Translation key for message.
     * @return void
     * @date 2026-07-27
     * @author Stephane H.
     */
    private function addRequestFlash(Request $request, string $type, string $message): void
    {
        if (!$request->hasSession()) {
            return;
        }

        FlashMessageHelper::add($request, $type, $message);
    }
}
