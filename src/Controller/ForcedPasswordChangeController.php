<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\Auth\AuthenticatedLandingResolver;
use App\Service\Auth\PasswordResetService;
use App\Service\Http\FlashMessageHelper;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;

/**
 * Controller ForcedPasswordChangeController.
 */
#[IsGranted('ROLE_USER')]
class ForcedPasswordChangeController
{
    private const CSRF_FORCED_PASSWORD_CHANGE = 'forced_password_change_submit';

    /**
     * @brief Build forced password change controller.
     * @param PasswordResetService $passwordResetService Password reset service.
     * @param AuthenticatedLandingResolver $authenticatedLandingResolver Landing path resolver.
     * @param Security $security Security helper.
     * @param CsrfTokenManagerInterface $csrfTokenManager CSRF token manager.
     * @param RateLimiterFactory $forcedPasswordChangeLimiter Forced password change limiter.
     * @return void
     * @date 2026-07-27
     * @author Stephane H.
     */
    public function __construct(
        private readonly PasswordResetService $passwordResetService,
        private readonly AuthenticatedLandingResolver $authenticatedLandingResolver,
        private readonly Security $security,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly RateLimiterFactory $forcedPasswordChangeLimiter
    ) {
    }

    /**
     * @brief Render forced password change form.
     * @param Environment $twig Twig environment.
     * @return Response
     * @date 2026-07-27
     * @author Stephane H.
     */
    #[Route('/change-password-required', name: 'forced_password_change', methods: ['GET'])]
    public function index(Environment $twig): Response
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return new RedirectResponse('/login');
        }
        if (!$user->isPasswordResetRequired()) {
            return new RedirectResponse($this->authenticatedLandingResolver->resolveLandingPath());
        }

        return new Response($twig->render('security/forced_password_change.html.twig', [
            'csrfForcedPasswordChange' => self::CSRF_FORCED_PASSWORD_CHANGE,
        ]));
    }

    /**
     * @brief Submit forced password change.
     * @param Request $request HTTP request payload.
     * @return Response
     * @date 2026-07-27
     * @author Stephane H.
     */
    #[Route('/change-password-required', name: 'forced_password_change_submit', methods: ['POST'])]
    public function submit(Request $request): Response
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return new RedirectResponse('/login');
        }
        if (!$user->isPasswordResetRequired()) {
            return new RedirectResponse($this->authenticatedLandingResolver->resolveLandingPath());
        }

        $tokenValue = (string) $request->request->get('_csrf_token', '');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_FORCED_PASSWORD_CHANGE, $tokenValue))) {
            $this->addRequestFlash($request, 'danger', 'security.forced_password_change.invalid_payload');

            return new RedirectResponse(AuthenticatedLandingResolver::FORCED_PASSWORD_CHANGE_PATH);
        }

        $limiter = $this->forcedPasswordChangeLimiter->create(
            ($request->getClientIp() ?? 'unknown').'|'.(string) $user->getId()
        );
        if (!$limiter->consume()->isAccepted()) {
            $this->addRequestFlash($request, 'danger', 'security.forced_password_change.rate_limited');

            return new RedirectResponse(AuthenticatedLandingResolver::FORCED_PASSWORD_CHANGE_PATH);
        }

        $newPassword = trim((string) $request->request->get('password', ''));
        $passwordConfirm = trim((string) $request->request->get('password_confirm', ''));
        if ($newPassword === '') {
            $this->addRequestFlash($request, 'danger', 'security.forced_password_change.password_required');

            return new RedirectResponse(AuthenticatedLandingResolver::FORCED_PASSWORD_CHANGE_PATH);
        }
        if ($newPassword !== $passwordConfirm) {
            $this->addRequestFlash($request, 'danger', 'security.forced_password_change.password_mismatch');

            return new RedirectResponse(AuthenticatedLandingResolver::FORCED_PASSWORD_CHANGE_PATH);
        }

        $errorKey = $this->passwordResetService->completeForcedPasswordChange($user, $newPassword);
        if (is_string($errorKey)) {
            $this->addRequestFlash($request, 'danger', $errorKey);

            return new RedirectResponse(AuthenticatedLandingResolver::FORCED_PASSWORD_CHANGE_PATH);
        }

        if ($request->hasSession()) {
            $request->getSession()->set('auth.session_version', $user->getSessionVersion());
        }
        $this->addRequestFlash($request, 'success', 'security.forced_password_change.success');

        return new RedirectResponse($this->authenticatedLandingResolver->resolveLandingPath());
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
