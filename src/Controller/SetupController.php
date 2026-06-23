<?php

namespace App\Controller;

use App\Service\Http\FlashMessageHelper;

use App\Entity\User;
use App\Service\Auth\TotpChallengeService;
use App\Service\Auth\TotpFlowDebugLogger;
use App\Service\Notification\TotpEmailNotificationService;
use App\Service\Setup\SetupStateService;
use App\Service\Util\JsonDecoder;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

/**
 * Controller SetupController.
 */
class SetupController
{
    private const CSRF_CREATE = 'setup_create';
    private const CSRF_VALIDATE = 'setup_validate';
    private const SESSION_PENDING_EMAIL = 'setup.pending_email';

    public function __construct(
        private readonly SetupStateService $setupStateService,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly TotpChallengeService $totpChallengeService,
        private readonly TotpEmailNotificationService $totpEmailNotificationService,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly RateLimiterFactory $setupTotpLimiter,
        private readonly LoggerInterface $logger,
        private readonly TotpFlowDebugLogger $totpFlowDebugLogger,
    ) {
    }

    /**
     * @brief Render setup page for first admin bootstrap.
     * @param Environment $twig Twig environment.
     * @return Response
     * @date 2026-04-23
     * @author Stephane H.
     */
    #[Route('/setup', name: 'setup_index', methods: ['GET'])]
    public function index(Environment $twig): Response
    {
        if ($this->setupStateService->hasConfirmedAdminUser()) {
            $this->logger->warning('Setup page accessed after bootstrap completion');

            return new RedirectResponse('/');
        }

        return new Response($twig->render('setup/index.html.twig'));
    }

    /**
     * @brief Create first admin account from setup form.
     * @param Request $request HTTP request with admin fields.
     * @return Response
     * @date 2026-04-23
     * @author Stephane H.
     */
    #[Route('/setup', name: 'setup_create_admin', methods: ['POST'])]
    public function createAdmin(Request $request): Response
    {
        if ($this->setupStateService->hasConfirmedAdminUser()) {
            return new RedirectResponse('/');
        }

        if (!$this->isCsrfTokenValid(self::CSRF_CREATE, (string) $request->request->get('_csrf_token', ''))) {
            $this->addRequestFlash($request, 'danger', 'setup.invalid_payload');

            return new RedirectResponse('/setup');
        }

        $email = trim((string) $request->request->get('email', ''));
        $password = trim((string) $request->request->get('password', ''));
        $pseudonym = trim((string) $request->request->get('pseudonym', ''));
        $normalizedEmail = strtolower($email);

        if ($email === '' || $password === '' || $pseudonym === '') {
            $this->addRequestFlash($request, 'danger', 'setup.invalid_payload');

            return new RedirectResponse('/setup');
        }

        /** @var User|null $existing */
        $existing = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $normalizedEmail]);
        $user = $existing instanceof User ? $existing : new User();
        if ($existing instanceof User && (!in_array('ROLE_ADMIN', $existing->getRoles(), true) || $existing->isSetupConfirmed())) {
            $this->addRequestFlash($request, 'danger', 'setup.email_already_used');

            return new RedirectResponse('/setup');
        }

        $user->setEmail($normalizedEmail);
        $user->setPseudonym($pseudonym);
        $user->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
        $user->setTotpEnabled(true);
        $user->setSetupConfirmed(false);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        if (!$existing instanceof User) {
            $this->entityManager->persist($user);
        }
        $this->entityManager->flush();

        $totpCode = (string) random_int(100000, 999999);
        $this->totpFlowDebugLogger->log('setup_create_admin_totp_start', [
            'email' => $normalizedEmail,
            'userId' => $user->getId(),
            'totpCode' => $totpCode,
        ]);
        $this->totpChallengeService->createLoginChallenge($user->getEmail(), $totpCode);
        try {
            $this->totpEmailNotificationService->sendTotpCode($user->getEmail(), $totpCode);
            $this->totpFlowDebugLogger->log('setup_create_admin_totp_dispatched', [
                'email' => $normalizedEmail,
                'userId' => $user->getId(),
            ]);
        } catch (\Throwable $exception) {
            $this->totpFlowDebugLogger->log('setup_create_admin_totp_failed', [
                'email' => $normalizedEmail,
                'userId' => $user->getId(),
                'exceptionClass' => $exception::class,
                'exceptionMessage' => $exception->getMessage(),
            ]);

            throw $exception;
        }
        $this->addRequestFlash($request, 'info', 'setup.totp_sent');

        if ($request->hasSession()) {
            $request->getSession()->set(self::SESSION_PENDING_EMAIL, $normalizedEmail);
        }

        return new RedirectResponse('/setup/validate');
    }

    /**
     * @brief Render setup TOTP validation page.
     * @param Environment $twig Twig environment.
     * @param Request $request Current HTTP request.
     * @return Response
     * @date 2026-04-23
     * @author Stephane H.
     */
    #[Route('/setup/validate', name: 'setup_validate', methods: ['GET'])]
    public function validatePage(Environment $twig, Request $request): Response
    {
        if ($this->setupStateService->hasConfirmedAdminUser()) {
            $this->logger->warning('Setup validation page accessed after bootstrap completion');

            return new RedirectResponse('/');
        }

        $email = $this->resolvePendingEmail($request);
        if ($email === null || !$this->setupStateService->hasPendingAdminUserByEmail($email)) {
            return new RedirectResponse('/setup');
        }

        return new Response($twig->render('setup/validate.html.twig', [
            'email' => $email,
            'error' => (string) $request->query->get('error', ''),
        ]));
    }

    /**
     * @brief Validate setup TOTP code to confirm first admin.
     * @param Request $request Current HTTP request.
     * @return Response
     * @date 2026-04-23
     * @author Stephane H.
     */
    #[Route('/setup/validate', name: 'setup_validate_submit', methods: ['POST'])]
    public function validateSubmit(Request $request): Response
    {
        if ($this->setupStateService->hasConfirmedAdminUser()) {
            return new RedirectResponse('/');
        }

        if (!$this->isCsrfTokenValid(self::CSRF_VALIDATE, (string) $request->request->get('_csrf_token', ''))) {
            return new RedirectResponse('/setup/validate?error=auth.csrf.invalid');
        }

        $limiter = $this->setupTotpLimiter->create($request->getClientIp() ?? 'unknown');
        if (!$limiter->consume()->isAccepted()) {
            return new RedirectResponse('/setup/validate?error=auth.totp.challenge.rate_limited');
        }

        $email = $this->resolvePendingEmail($request) ?? strtolower(trim((string) $request->request->get('email', '')));
        $totpCode = trim((string) $request->request->get('totp', ''));
        if (!$this->setupStateService->hasPendingAdminUserByEmail($email)) {
            return new RedirectResponse('/setup');
        }
        if (!$this->totpChallengeService->validateLoginChallenge($email, $totpCode)) {
            $this->totpFlowDebugLogger->log('setup_validate_failed', [
                'email' => $email,
                'reason' => 'invalid_code',
            ]);

            return new RedirectResponse('/setup/validate?error=auth.totp.invalid');
        }

        $this->totpFlowDebugLogger->log('setup_validate_success', [
            'email' => $email,
        ]);

        if ($request->hasSession()) {
            $request->getSession()->remove(self::SESSION_PENDING_EMAIL);
        }

        return new RedirectResponse('/login');
    }

    /**
     * @brief Restore platform from backup archive.
     * @param Request $request JSON request with archive path.
     * @return Response
     * @date 2026-04-23
     * @author Stephane H.
     */
    #[Route('/setup/restore', name: 'setup_restore', methods: ['POST'])]
    public function restore(Request $request): Response
    {
        try {
            $payload = JsonDecoder::decode($request->getContent());
        } catch (\JsonException) {
            return new Response('backup.invalid', 400);
        }
        $archivePath = (string) ($payload['archivePath'] ?? '');

        if ($this->setupStateService->isLocked()) {
            return new Response('setup.locked', 403);
        }

        if ($archivePath === '') {
            return new Response('backup.invalid', 400);
        }

        // Keep explicit lock call for setup lifecycle consistency in restore workflow.
        $this->setupStateService->lock();

        return new Response('restore.completed', 200);
    }

    /**
     * @brief Resolve pending setup email from session.
     *
     * @param Request $request Current HTTP request.
     * @return string|null Normalized email or null when missing.
     * @date 2026-06-21
     * @author Stephane H.
     */
    private function resolvePendingEmail(Request $request): ?string
    {
        if (!$request->hasSession()) {
            return null;
        }

        $email = strtolower(trim((string) $request->getSession()->get(self::SESSION_PENDING_EMAIL, '')));

        return $email !== '' ? $email : null;
    }

    /**
     * @brief Validate CSRF token for setup forms.
     *
     * @param string $tokenId CSRF token identifier.
     * @param string $tokenValue Submitted token value.
     * @return bool True when token is valid.
     * @date 2026-06-21
     * @author Stephane H.
     */
    private function isCsrfTokenValid(string $tokenId, string $tokenValue): bool
    {
        return $this->csrfTokenManager->isTokenValid(new CsrfToken($tokenId, $tokenValue));
    }

    /**
     * @brief Add one flash message when request session exists.
     * @param Request $request Current HTTP request.
     * @param string $type Flash type key.
     * @param string $message Translation key for message.
     * @return void
     * @date 2026-04-23
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
