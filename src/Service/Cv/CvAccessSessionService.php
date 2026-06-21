<?php

declare(strict_types=1);

namespace App\Service\Cv;

use App\Entity\User;
use App\Repository\TrackedCompanyRepository;
use App\Service\Employment\CompanyCodeNormalizer;
use App\Service\Employment\CvConnectionLoggingService;
use App\Service\Employment\VisitorCountryResolver;
use App\Service\Http\RequestSessionResolver;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * CV public access session: antibot grant (30 min) and sticky company format (7 days).
 */
class CvAccessSessionService
{
    public const SESSION_ACCESS_VALID_UNTIL = 'cv_access.valid_until';

    public const SESSION_FORMAT_CODE = 'cv_target_format.code';

    public const SESSION_FORMAT_VALID_UNTIL = 'cv_target_format.valid_until';

    private const ACCESS_TTL_SECONDS = 1800;

    private const FORMAT_TTL_SECONDS = 604800;

    /**
     * @brief Build CV access session helper.
     *
     * @param RequestStack $requestStack Request stack for session access.
     * @param Security $security Security helper for role bypass checks.
     * @param CompanyCodeNormalizer $companyCodeNormalizer Format code normalizer.
     * @param TrackedCompanyRepository $trackedCompanyRepository Company repository.
     * @param CvConnectionLoggingService $cvConnectionLoggingService Connection logger.
     * @param VisitorCountryResolver $visitorCountryResolver Country resolver.
     * @param RequestSessionResolver $requestSessionResolver Safe session accessor.
     * @param LoggerInterface $cvAccessBypassLogger Dedicated logger for role bypass events.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly Security $security,
        private readonly CompanyCodeNormalizer $companyCodeNormalizer,
        private readonly TrackedCompanyRepository $trackedCompanyRepository,
        private readonly CvConnectionLoggingService $cvConnectionLoggingService,
        private readonly VisitorCountryResolver $visitorCountryResolver,
        private readonly RequestSessionResolver $requestSessionResolver,
        private readonly LoggerInterface $cvAccessBypassLogger,
    ) {
    }

    /**
     * @brief Check whether the current user may bypass the CV antibot gate.
     *
     * @param void No input parameter.
     * @return bool True for ROLE_ADMIN or ROLE_CV_CONSULT.
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function isBypassGranted(): bool
    {
        try {
            $granted = $this->security->isGranted('ROLE_ADMIN') || $this->security->isGranted('ROLE_CV_CONSULT');
        } catch (\Throwable) {
            return false;
        }

        if ($granted) {
            $this->logBypassOnce();
        }

        return $granted;
    }

    /**
     * @brief Log CV gate bypass once per request for auditability.
     *
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    private function logBypassOnce(): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null || $request->attributes->getBoolean('_cv_bypass_logged')) {
            return;
        }

        $request->attributes->set('_cv_bypass_logged', true);
        $user = $this->security->getUser();
        $this->cvAccessBypassLogger->info('CV access gate bypass', [
            'user_id' => $user instanceof User ? $user->getId() : null,
            'email' => $user instanceof User ? $user->getEmail() : null,
            'path' => $request->getPathInfo(),
            'roles' => $user instanceof User ? $user->getRoles() : [],
        ]);
    }

    /**
     * @brief Check whether antibot access is currently granted in session.
     *
     * @param void No input parameter.
     * @return bool
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function isAccessGranted(): bool
    {
        if ($this->isBypassGranted()) {
            return true;
        }

        $session = $this->getSession();
        if (!$session instanceof SessionInterface) {
            return false;
        }

        $validUntil = (int) $session->get(self::SESSION_ACCESS_VALID_UNTIL, 0);

        return $validUntil > time();
    }

    /**
     * @brief Grant CV access for the configured TTL.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function grantAccess(): void
    {
        $session = $this->getSession();
        if (!$session instanceof SessionInterface) {
            return;
        }

        $session->set(self::SESSION_ACCESS_VALID_UNTIL, time() + self::ACCESS_TTL_SECONDS);
    }

    /**
     * @brief Capture company format from query when valid active company (sticky for session TTL).
     *
     * @param string $rawFormat Raw format query value.
     * @param Request|null $request Request for invalid-format logging.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function captureTargetFormatFromQuery(string $rawFormat, ?Request $request = null): void
    {
        if ($this->getStoredFormatCode() !== '') {
            return;
        }

        $trimmed = trim($rawFormat);
        if ($trimmed === '') {
            return;
        }

        $normalized = $this->companyCodeNormalizer->normalize($trimmed);
        if ($normalized === '') {
            if ($request instanceof Request) {
                $this->cvConnectionLoggingService->logInvalidFormat(
                    $trimmed,
                    (string) ($request->getClientIp() ?? ''),
                    $this->visitorCountryResolver->resolve($request),
                    (string) $request->headers->get('User-Agent', ''),
                    $request->getPathInfo(),
                    (string) $request->attributes->get('_route', ''),
                );
            }

            return;
        }

        $company = $this->trackedCompanyRepository->findActiveByCode($normalized);
        if ($company === null) {
            if ($request instanceof Request) {
                $this->cvConnectionLoggingService->logInvalidFormat(
                    $trimmed,
                    (string) ($request->getClientIp() ?? ''),
                    $this->visitorCountryResolver->resolve($request),
                    (string) $request->headers->get('User-Agent', ''),
                    $request->getPathInfo(),
                    (string) $request->attributes->get('_route', ''),
                );
            }

            return;
        }

        $session = $this->getSession();
        if (!$session instanceof SessionInterface) {
            return;
        }

        $session->set(self::SESSION_FORMAT_CODE, $normalized);
        $session->set(self::SESSION_FORMAT_VALID_UNTIL, time() + self::FORMAT_TTL_SECONDS);
        $this->activeFormatCodeCache = $normalized;
        $this->activeFormatCodeCacheResolved = true;
    }

    /**
     * @brief Whether the CV antibot gate must run for the current request.
     *
     * Gate applies when an active tracked-company format is present in the query or sticky session.
     * Public CV visits without format context remain open for SEO and organic traffic.
     *
     * @param Request $request Incoming HTTP request.
     * @return bool True when gate enforcement is required.
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function requiresAccessGate(Request $request): bool
    {
        $formatQuery = trim((string) $request->query->get('format', ''));
        if ($formatQuery !== '') {
            $this->captureTargetFormatFromQuery($formatQuery, $request);
        }

        return $this->getActiveFormatCode() !== '';
    }

    /**
     * @brief Resolve effective format code: stored session value first, then optional query fallback.
     *
     * @param string|null $queryFormat Format from current request query when session is empty.
     * @param Request|null $request Request for capture side effects.
     * @return string Normalized format code or empty string.
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function resolveTargetFormatCode(?string $queryFormat = null, ?Request $request = null): string
    {
        $stored = $this->getActiveFormatCode();
        if ($stored !== '') {
            return $stored;
        }

        if ($queryFormat === null || trim($queryFormat) === '') {
            return '';
        }

        $this->captureTargetFormatFromQuery($queryFormat, $request);

        return $this->getActiveFormatCode();
    }

    /**
     * @brief Cached active format code for the current request lifecycle.
     */
    private ?string $activeFormatCodeCache = null;

    /**
     * @brief Whether active format code cache was already resolved this request.
     */
    private bool $activeFormatCodeCacheResolved = false;

    /**
     * @brief Get active sticky format code validated against database.
     *
     * @param void No input parameter.
     * @return string Company code or empty.
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function getActiveFormatCode(): string
    {
        if ($this->activeFormatCodeCacheResolved) {
            return $this->activeFormatCodeCache ?? '';
        }

        $this->activeFormatCodeCacheResolved = true;
        $stored = $this->getStoredFormatCode();
        if ($stored === '') {
            $this->activeFormatCodeCache = '';

            return '';
        }

        $company = $this->trackedCompanyRepository->findActiveByCode($stored);
        if ($company === null) {
            $this->clearFormatSession();
            $this->activeFormatCodeCache = '';

            return '';
        }

        $this->activeFormatCodeCache = $stored;

        return $stored;
    }

    /**
     * @brief Clear sticky format session keys.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function clearFormatSession(): void
    {
        $session = $this->getSession();
        if (!$session instanceof SessionInterface) {
            return;
        }

        $session->remove(self::SESSION_FORMAT_CODE);
        $session->remove(self::SESSION_FORMAT_VALID_UNTIL);
        $this->activeFormatCodeCache = '';
        $this->activeFormatCodeCacheResolved = true;
    }

    /**
     * @brief Read sticky format code when still within TTL.
     *
     * @param void No input parameter.
     * @return string
     * @date 2026-05-19
     * @author Stephane H.
     */
    private function getStoredFormatCode(): string
    {
        $session = $this->getSession();
        if (!$session instanceof SessionInterface) {
            return '';
        }

        $validUntil = (int) $session->get(self::SESSION_FORMAT_VALID_UNTIL, 0);
        if ($validUntil <= time()) {
            $session->remove(self::SESSION_FORMAT_CODE);
            $session->remove(self::SESSION_FORMAT_VALID_UNTIL);

            return '';
        }

        $code = $session->get(self::SESSION_FORMAT_CODE);

        return is_string($code) ? $code : '';
    }

    /**
     * @brief Get current session if available.
     *
     * @param void No input parameter.
     * @return SessionInterface|null
     * @date 2026-05-19
     * @author Stephane H.
     */
    private function getSession(): ?SessionInterface
    {
        return $this->requestSessionResolver->resolve();
    }
}
