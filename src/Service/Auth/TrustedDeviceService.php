<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\TrustedDevice;
use App\EventSubscriber\TrustedDeviceCookieSubscriber;
use App\Repository\TrustedDeviceRepository;
use DateInterval;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Service TrustedDeviceService.
 */
class TrustedDeviceService
{
    private const COOKIE_NAME = 'stu_trusted_device';

    /**
     * @brief Build trusted device service.
     * @param TrustedDeviceRepository $trustedDeviceRepository Trusted device repository.
     * @param EntityManagerInterface $entityManager Doctrine entity manager.
     * @param string $kernelSecret Application secret for cookie signing.
     * @return void
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function __construct(
        private readonly TrustedDeviceRepository $trustedDeviceRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $kernelSecret,
    ) {
    }

    /**
     * @brief Compute trusted device expiration date.
     * @param DateTimeImmutable $fromDate Trust start date.
     * @return DateTimeImmutable
     * @date 2026-04-22
     * @author Stephane H.
     */
    public function computeExpiration(DateTimeImmutable $fromDate): DateTimeImmutable
    {
        return $fromDate->add(new DateInterval('P6M'));
    }

    /**
     * @brief Check if trust window is still valid.
     * @param DateTimeImmutable $trustedUntil Trust expiration.
     * @param DateTimeImmutable $now Current date.
     * @return bool
     * @date 2026-04-22
     * @author Stephane H.
     */
    public function isStillTrusted(DateTimeImmutable $trustedUntil, DateTimeImmutable $now): bool
    {
        return $trustedUntil >= $now;
    }

    /**
     * @brief Check if current request device is trusted for user.
     * @param int $userId User identifier.
     * @param Request $request Current HTTP request.
     * @return bool
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function isTrustedDevice(int $userId, Request $request): bool
    {
        $fingerprint = $this->computeFingerprint($request);
        if ($this->isSignedCookieValid($request, $userId, $fingerprint)) {
            return true;
        }

        $trustedDevice = $this->trustedDeviceRepository->findActiveByUserAndFingerprint($userId, $fingerprint, new DateTimeImmutable());

        return $trustedDevice instanceof TrustedDevice;
    }

    /**
     * @brief Mark current request device as trusted for user.
     * @param int $userId User identifier.
     * @param Request $request Current HTTP request.
     * @return void
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function trustDevice(int $userId, Request $request): void
    {
        $now = new DateTimeImmutable();
        $trustedUntil = $this->computeExpiration($now);
        $fingerprint = $this->computeFingerprint($request);
        $existing = $this->trustedDeviceRepository->findByUserAndFingerprint($userId, $fingerprint);

        if ($existing instanceof TrustedDevice) {
            $existing->renew($trustedUntil);
            $this->entityManager->flush();
        } else {
            $trustedDevice = new TrustedDevice($userId, $fingerprint, $trustedUntil);
            $this->entityManager->persist($trustedDevice);
            $this->entityManager->flush();
        }

        $request->attributes->set(
            TrustedDeviceCookieSubscriber::REQUEST_ATTRIBUTE,
            $this->buildSignedCookieValue($userId, $fingerprint, $trustedUntil)
        );
    }

    /**
     * @brief Compute stable request fingerprint for trust checks.
     * @param Request $request Current HTTP request.
     * @return string
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function computeFingerprint(Request $request): string
    {
        $userAgent = (string) $request->headers->get('User-Agent', '');
        $acceptLanguage = (string) $request->headers->get('Accept-Language', '');
        $clientIp = (string) $request->getClientIp();

        return hash('sha256', implode('|', [$userAgent, $acceptLanguage, $clientIp]));
    }

    /**
     * @brief Validate signed trusted-device cookie for current request.
     *
     * @param Request $request Current HTTP request.
     * @param int $userId Expected user identifier.
     * @param string $fingerprint Current request fingerprint.
     * @return bool True when cookie is valid and matches user/fingerprint.
     * @date 2026-06-21
     * @author Stephane H.
     */
    private function isSignedCookieValid(Request $request, int $userId, string $fingerprint): bool
    {
        $rawCookie = (string) $request->cookies->get(self::COOKIE_NAME, '');
        if ($rawCookie === '') {
            return false;
        }

        $parts = explode('|', $rawCookie, 4);
        if (count($parts) !== 4) {
            return false;
        }

        [$cookieUserId, $expiresAt, $cookieFingerprint, $signature] = $parts;
        if ((int) $cookieUserId !== $userId || $cookieFingerprint !== $fingerprint) {
            return false;
        }

        if ((int) $expiresAt < time()) {
            return false;
        }

        $expected = hash_hmac('sha256', implode('|', [$cookieUserId, $expiresAt, $cookieFingerprint]), $this->kernelSecret);

        return hash_equals($expected, $signature);
    }

    /**
     * @brief Build signed cookie payload for trusted device persistence.
     *
     * @param int $userId User identifier.
     * @param string $fingerprint Device fingerprint hash.
     * @param DateTimeImmutable $trustedUntil Trust expiration timestamp.
     * @return string Cookie value.
     * @date 2026-06-21
     * @author Stephane H.
     */
    private function buildSignedCookieValue(int $userId, string $fingerprint, DateTimeImmutable $trustedUntil): string
    {
        $expiresAt = (string) $trustedUntil->getTimestamp();
        $payload = implode('|', [(string) $userId, $expiresAt, $fingerprint]);
        $signature = hash_hmac('sha256', $payload, $this->kernelSecret);

        return $payload.'|'.$signature;
    }
}
