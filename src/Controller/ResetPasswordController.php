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
 * Controller ResetPasswordController.
 */
class ResetPasswordController
{
    private const CSRF_RESET_PASSWORD = 'reset_password_submit';

    /**
     * @brief Build reset password controller.
     * @param PasswordResetService $passwordResetService Password reset service.
     * @param CsrfTokenManagerInterface $csrfTokenManager CSRF token manager.
     * @param RateLimiterFactory $passwordResetLimiter Password reset rate limiter factory.
     * @return void
     * @date 2026-07-27
     * @author Stephane H.
     */
    public function __construct(
        private readonly PasswordResetService $passwordResetService,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly RateLimiterFactory $passwordResetLimiter
    ) {
    }

    /**
     * @brief Render reset password form when token is valid.
     * @param string $token Plain reset token.
     * @param Environment $twig Twig environment.
     * @return Response
     * @date 2026-07-27
     * @author Stephane H.
     */
    #[Route('/reset-password/{token}', name: 'reset_password', methods: ['GET'])]
    public function index(string $token, Environment $twig): Response
    {
        return new Response($twig->render('security/reset_password.html.twig', [
            'token' => $token,
            'tokenValid' => $this->passwordResetService->isTokenValid($token),
            'csrfResetPassword' => self::CSRF_RESET_PASSWORD,
        ]));
    }

    /**
     * @brief Finalize password reset from token.
     * @param string $token Plain reset token.
     * @param Request $request HTTP request payload.
     * @return Response
     * @date 2026-07-27
     * @author Stephane H.
     */
    #[Route('/reset-password/{token}', name: 'reset_password_submit', methods: ['POST'])]
    public function submit(string $token, Request $request): Response
    {
        $tokenValue = (string) $request->request->get('_csrf_token', '');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_RESET_PASSWORD, $tokenValue))) {
            $this->addRequestFlash($request, 'danger', 'security.reset_password.invalid_payload');

            return new RedirectResponse('/reset-password/'.$token);
        }

        $limiter = $this->passwordResetLimiter->create($request->getClientIp() ?? 'unknown');
        if (!$limiter->consume()->isAccepted()) {
            $this->addRequestFlash($request, 'danger', 'security.reset_password.rate_limited');

            return new RedirectResponse('/reset-password/'.$token);
        }

        $newPassword = trim((string) $request->request->get('password', ''));
        $passwordConfirm = trim((string) $request->request->get('password_confirm', ''));
        if ($newPassword === '') {
            $this->addRequestFlash($request, 'danger', 'security.reset_password.password_required');

            return new RedirectResponse('/reset-password/'.$token);
        }
        if ($newPassword !== $passwordConfirm) {
            $this->addRequestFlash($request, 'danger', 'security.reset_password.password_mismatch');

            return new RedirectResponse('/reset-password/'.$token);
        }

        if (!$this->passwordResetService->resetPassword($token, $newPassword)) {
            $this->addRequestFlash($request, 'danger', 'security.reset_password.invalid_or_expired');

            return new RedirectResponse('/reset-password/'.$token);
        }

        $this->addRequestFlash($request, 'success', 'security.reset_password.success');

        return new RedirectResponse('/login');
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
