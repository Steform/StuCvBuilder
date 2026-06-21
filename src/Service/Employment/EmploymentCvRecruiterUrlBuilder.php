<?php

declare(strict_types=1);

namespace App\Service\Employment;

use App\Entity\TrackedCompany;
use App\Employment\EmploymentDocumentKind;
use App\Repository\TrackedCompanyRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Builds absolute recruiter URLs encoded inside employment CV/LM PDF QR codes.
 */
final class EmploymentCvRecruiterUrlBuilder
{
    /**
     * @brief Build recruiter URL builder.
     *
     * @param UrlGeneratorInterface $urlGenerator Symfony URL generator.
     * @param TrackedCompanyRepository $trackedCompanyRepository Active company repository.
     * @return void
     * @date 2026-06-12
     * @author Stephane H.
     */
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TrackedCompanyRepository $trackedCompanyRepository,
    ) {
    }

    /**
     * @brief Build absolute public URL with optional company format query for a document kind.
     *
     * @param string $formatCode Company format code or empty for default document URL.
     * @param string $kind cv or lm (selects cv_show vs cv_lm_pdf route).
     * @return string Absolute recruiter URL.
     * @date 2026-06-12
     * @author Stephane H.
     */
    public function build(string $formatCode, string $kind = EmploymentDocumentKind::CV): string
    {
        $formatCode = trim($formatCode);
        $parameters = [];
        $routeName = $this->routeNameForKind($kind);

        if ($formatCode !== '' && $this->trackedCompanyRepository->findActiveByCode($formatCode) instanceof TrackedCompany) {
            $parameters['format'] = $formatCode;
        }

        return $this->urlGenerator->generate($routeName, $parameters, UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /**
     * @brief Map employment document kind to the public Symfony route encoded in QR codes.
     *
     * @param string $kind cv or lm.
     * @return string Route name.
     * @date 2026-06-12
     * @author Stephane H.
     */
    private function routeNameForKind(string $kind): string
    {
        return $kind === EmploymentDocumentKind::LM ? 'cv_lm_pdf' : 'cv_show';
    }
}
