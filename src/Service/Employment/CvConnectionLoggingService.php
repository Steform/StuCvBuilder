<?php

declare(strict_types=1);

namespace App\Service\Employment;

use App\Entity\CvConnectionLog;
use App\Employment\ConnectionKind;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Persists CV connection log rows.
 */
class CvConnectionLoggingService
{
    /**
     * @brief Build CV connection logging service.
     *
     * @param EntityManagerInterface $entityManager ORM entity manager.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @brief Persist a connection log from context.
     *
     * @param ConnectionLogContext $context Log payload.
     * @return CvConnectionLog Persisted log entity.
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function log(ConnectionLogContext $context): CvConnectionLog
    {
        $entry = new CvConnectionLog($context->connectionKind, new DateTimeImmutable());
        $entry->setFormatRaw($context->formatRaw)
            ->setCompany($context->countableForCompany ? $context->company : null)
            ->setIpAddress($context->ipAddress)
            ->setCountryCode($context->countryCode)
            ->setUserAgent($context->userAgent)
            ->setGatePassed($context->gatePassed)
            ->setAttestationMethod($context->attestationMethod)
            ->setTechnicalScore($context->technicalScore)
            ->setCountableForCompany($context->countableForCompany)
            ->setIsAdminBypass($context->isAdminBypass)
            ->setRequestPath($context->requestPath)
            ->setRequestRoute($context->requestRoute)
            ->setVisit($context->visit);

        if ($context->company !== null) {
            $entry->setCompanyCodeSnapshot($context->company->getCode());
            $entry->setCompanyNameSnapshot($context->company->getName());
        }

        $this->entityManager->persist($entry);
        $this->entityManager->flush();

        return $entry;
    }

    /**
     * @brief Log invalid format attempt.
     *
     * @param string $formatRaw Raw format value.
     * @param string|null $ipAddress Client IP.
     * @param string|null $countryCode Country code.
     * @param string|null $userAgent User agent.
     * @param string|null $requestPath Request path.
     * @param string|null $requestRoute Route name.
     * @return CvConnectionLog
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function logInvalidFormat(
        string $formatRaw,
        ?string $ipAddress,
        ?string $countryCode,
        ?string $userAgent,
        ?string $requestPath,
        ?string $requestRoute,
    ): CvConnectionLog {
        return $this->log(new ConnectionLogContext(
            connectionKind: ConnectionKind::TECHNICAL_INVALID_FORMAT,
            formatRaw: $formatRaw,
            ipAddress: $ipAddress,
            countryCode: $countryCode,
            userAgent: $userAgent,
            requestPath: $requestPath,
            requestRoute: $requestRoute,
        ));
    }
}
