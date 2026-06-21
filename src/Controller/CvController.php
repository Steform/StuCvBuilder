<?php

namespace App\Controller;

use App\Service\Cv\CvAccessSessionService;
use App\Service\Cv\CvResolverService;
use App\Exception\Employment\EmploymentDocumentPdfStampException;
use App\Service\Employment\CompanyCvVisitService;
use App\Service\Employment\EmploymentDocumentPdfDeliveryService;
use App\Service\Employment\EmploymentPublicDocumentPdfResolver;
use App\Service\Security\CvBotAccessService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller CvController.
 */
class CvController extends AbstractController
{
    public function __construct(
        private readonly CvResolverService $cvResolverService,
        private readonly CvAccessSessionService $cvAccessSessionService,
        private readonly CompanyCvVisitService $companyCvVisitService,
        private readonly CvBotAccessService $cvBotAccess,
        private readonly EmploymentPublicDocumentPdfResolver $employmentPublicDocumentPdfResolver,
        private readonly EmploymentDocumentPdfDeliveryService $employmentDocumentPdfDeliveryService,
    ) {
    }

    /**
     * @brief Render the public CV HTML page (access enforced by gate subscriber).
     *
     * @param Request $request HTTP request.
     * @return Response HTML 200 on success.
     * @date 2026-05-19
     * @author Stephane H.
     */
    #[Route('/cv/', name: 'cv_show', methods: ['GET'])]
    public function show(Request $request): Response
    {
        $formatCode = $this->cvAccessSessionService->resolveTargetFormatCode(
            (string) $request->query->get('format', ''),
            $request,
        );
        $isBypassAllowed = $this->cvAccessSessionService->isBypassGranted();

        $resolvedCv = $this->cvResolverService->resolve($formatCode, $request->getLocale());
        $this->companyCvVisitService->recordOfficialVisitOnCvShow($request);
        $countableVisit = $this->cvBotAccess->isEligibleForCompanyVisit() && ($resolvedCv['companyResolved'] ?? false);

        $request->attributes->set('cv_technical_score', $this->cvBotAccess->getTechnicalScoreForDisplay());

        return $this->render('cv/show.html.twig', [
            'cv' => $resolvedCv,
            'currentLocale' => $request->getLocale(),
            'countableVisit' => $countableVisit,
            'isAdminBypassAllowed' => $isBypassAllowed,
        ]);
    }

    /**
     * @brief Render the Situation detail page linked from About `[[cv.learn_more]]`.
     *
     * @param Request $request HTTP request.
     * @return Response HTML 200 on success.
     * @date 2026-06-01
     * @author Stephane H.
     */
    #[Route('/cv/situation', name: 'cv_situation', methods: ['GET'])]
    public function situation(Request $request): Response
    {
        $formatCode = $this->cvAccessSessionService->resolveTargetFormatCode(
            (string) $request->query->get('format', ''),
            $request,
        );
        $resolvedCv = $this->cvResolverService->resolve($formatCode, $request->getLocale());

        $request->attributes->set('cv_technical_score', $this->cvBotAccess->getTechnicalScoreForDisplay());

        return $this->render('cv/situation_full.html.twig', [
            'cv' => $resolvedCv,
            'currentLocale' => $request->getLocale(),
        ]);
    }

    /**
     * @brief Render the full professional experience timeline.
     *
     * @param Request $request HTTP request.
     * @return Response HTML 200 on success.
     * @date 2026-05-19
     * @author Stephane H.
     */
    #[Route('/cv/experience', name: 'cv_experience_full', methods: ['GET'])]
    public function experienceFull(Request $request): Response
    {
        $formatCode = $this->cvAccessSessionService->resolveTargetFormatCode(
            (string) $request->query->get('format', ''),
            $request,
        );
        $resolvedCv = $this->cvResolverService->resolve($formatCode, $request->getLocale());

        if (($resolvedCv['payload']['experienceHasSecondaryVisible'] ?? false) !== true) {
            return $this->redirectToRoute('cv_show', array_filter([
                'format' => $formatCode !== '' ? $formatCode : null,
                '_fragment' => 'experience',
            ]));
        }

        $request->attributes->set('cv_technical_score', $this->cvBotAccess->getTechnicalScoreForDisplay());

        $experienceEntriesFull = $resolvedCv['payload']['experienceEntriesFull'] ?? [];
        if (!is_array($experienceEntriesFull)) {
            $experienceEntriesFull = [];
        }

        return $this->render('cv/experience_full.html.twig', [
            'cv' => $resolvedCv,
            'experienceEntriesFull' => $experienceEntriesFull,
            'currentLocale' => $request->getLocale(),
        ]);
    }

    /**
     * @brief Render the full education timeline.
     *
     * @param Request $request HTTP request.
     * @return Response HTML 200 on success.
     * @date 2026-05-31
     * @author Stephane H.
     */
    #[Route('/cv/education', name: 'cv_education_full', methods: ['GET'])]
    public function educationFull(Request $request): Response
    {
        $formatCode = $this->cvAccessSessionService->resolveTargetFormatCode(
            (string) $request->query->get('format', ''),
            $request,
        );
        $resolvedCv = $this->cvResolverService->resolve($formatCode, $request->getLocale());

        if (($resolvedCv['payload']['educationHasSecondaryVisible'] ?? false) !== true) {
            return $this->redirectToRoute('cv_show', array_filter([
                'format' => $formatCode !== '' ? $formatCode : null,
                '_fragment' => 'education',
            ]));
        }

        $request->attributes->set('cv_technical_score', $this->cvBotAccess->getTechnicalScoreForDisplay());

        $educationEntriesFull = $resolvedCv['payload']['educationEntriesFull'] ?? [];
        if (!is_array($educationEntriesFull)) {
            $educationEntriesFull = [];
        }

        return $this->render('cv/education_full.html.twig', [
            'cv' => $resolvedCv,
            'educationEntriesFull' => $educationEntriesFull,
            'currentLocale' => $request->getLocale(),
        ]);
    }

    /**
     * @brief Render the full certification list with hidden-on-primary highlights.
     *
     * @param Request $request HTTP request.
     * @return Response HTML 200 on success.
     * @date 2026-05-31
     * @author Stephane H.
     */
    #[Route('/cv/certifications', name: 'cv_certifications_full', methods: ['GET'])]
    public function certificationsFull(Request $request): Response
    {
        $formatCode = $this->cvAccessSessionService->resolveTargetFormatCode(
            (string) $request->query->get('format', ''),
            $request,
        );
        $resolvedCv = $this->cvResolverService->resolve($formatCode, $request->getLocale());

        if (($resolvedCv['payload']['certificationHasSecondaryVisible'] ?? false) !== true) {
            return $this->redirectToRoute('cv_show', array_filter([
                'format' => $formatCode !== '' ? $formatCode : null,
                '_fragment' => 'certification',
            ]));
        }

        $request->attributes->set('cv_technical_score', $this->cvBotAccess->getTechnicalScoreForDisplay());

        $certificationEntriesFull = $resolvedCv['payload']['certificationEntriesFull'] ?? [];
        if (!is_array($certificationEntriesFull)) {
            $certificationEntriesFull = [];
        }

        return $this->render('cv/certifications_full.html.twig', [
            'cv' => $resolvedCv,
            'certificationEntriesFull' => $certificationEntriesFull,
            'currentLocale' => $request->getLocale(),
        ]);
    }

    /**
     * @brief Render the full skills catalog with primary view content plus hidden-on-primary highlights.
     *
     * @param Request $request HTTP request.
     * @return Response HTML 200 on success.
     * @date 2026-05-31
     * @author Stephane H.
     */
    #[Route('/cv/skills', name: 'cv_skills_full', methods: ['GET'])]
    public function skillsFull(Request $request): Response
    {
        $formatCode = $this->cvAccessSessionService->resolveTargetFormatCode(
            (string) $request->query->get('format', ''),
            $request,
        );
        $resolvedCv = $this->cvResolverService->resolve($formatCode, $request->getLocale());

        if (($resolvedCv['payload']['skillsHasSecondaryVisible'] ?? false) !== true) {
            return $this->redirectToRoute('cv_show', array_filter([
                'format' => $formatCode !== '' ? $formatCode : null,
                '_fragment' => 'skills',
            ]));
        }

        $request->attributes->set('cv_technical_score', $this->cvBotAccess->getTechnicalScoreForDisplay());

        $skillsTreeFull = $resolvedCv['payload']['skillsTreeFull'] ?? ['categories' => []];
        if (!is_array($skillsTreeFull)) {
            $skillsTreeFull = ['categories' => []];
        }

        return $this->render('cv/skills_full.html.twig', [
            'cv' => $resolvedCv,
            'skillsTreeFull' => $skillsTreeFull,
            'currentLocale' => $request->getLocale(),
        ]);
    }

    /**
     * @brief Render the full flagship projects grid with hidden-on-primary highlights.
     *
     * @param Request $request HTTP request.
     * @return Response HTML 200 on success.
     * @date 2026-05-31
     * @author Stephane H.
     */
    #[Route('/cv/projects', name: 'cv_projects_full', methods: ['GET'])]
    public function projectsFull(Request $request): Response
    {
        $formatCode = $this->cvAccessSessionService->resolveTargetFormatCode(
            (string) $request->query->get('format', ''),
            $request,
        );
        $resolvedCv = $this->cvResolverService->resolve($formatCode, $request->getLocale());

        if (($resolvedCv['payload']['flagshipProjectsHasSecondaryVisible'] ?? false) !== true) {
            return $this->redirectToRoute('cv_show', array_filter([
                'format' => $formatCode !== '' ? $formatCode : null,
                '_fragment' => 'projects',
            ]));
        }

        $request->attributes->set('cv_technical_score', $this->cvBotAccess->getTechnicalScoreForDisplay());

        $flagshipProjectsFull = $resolvedCv['payload']['flagshipProjectsFull'] ?? [];
        if (!is_array($flagshipProjectsFull)) {
            $flagshipProjectsFull = [];
        }

        return $this->render('cv/projects_full.html.twig', [
            'cv' => $resolvedCv,
            'flagshipProjectsFull' => $flagshipProjectsFull,
            'currentLocale' => $request->getLocale(),
        ]);
    }

    /**
     * @brief Stream employment CV PDF with QR overlay for company format or default variant.
     *
     * @param Request $request HTTP request.
     * @return Response PDF file download or HTML not-available page.
     * @date 2026-06-12
     * @author Stephane H.
     */
    #[Route('/cv/pdf', name: 'cv_pdf', methods: ['GET'])]
    public function pdf(Request $request): Response
    {
        $formatCode = $this->cvAccessSessionService->resolveTargetFormatCode(
            (string) $request->query->get('format', ''),
            $request,
        );

        $resolved = $this->employmentPublicDocumentPdfResolver->resolveCv($formatCode, $request->getLocale());

        return $this->buildStampedPdfResponse(
            $resolved,
            $formatCode,
            'cv/pdf_not_implemented.html.twig',
            $request->getLocale(),
        );
    }

    /**
     * @brief Stream employment cover-letter PDF with QR overlay for company format or default LM variant.
     *
     * @param Request $request HTTP request.
     * @return Response PDF file download or HTML not-available page.
     * @date 2026-06-12
     * @author Stephane H.
     */
    #[Route('/cv/lm-pdf', name: 'cv_lm_pdf', methods: ['GET'])]
    public function lmPdf(Request $request): Response
    {
        $formatCode = $this->cvAccessSessionService->resolveTargetFormatCode(
            (string) $request->query->get('format', ''),
            $request,
        );

        $resolved = $this->employmentPublicDocumentPdfResolver->resolveLm($formatCode, $request->getLocale());

        return $this->buildStampedPdfResponse(
            $resolved,
            $formatCode,
            'cv/lm_pdf_not_implemented.html.twig',
            $request->getLocale(),
        );
    }

    /**
     * @brief Build stamped PDF download response or fallback HTML page.
     *
     * @param array{absolutePath: string, downloadFilename: string, variant: \App\Entity\EmploymentDocumentVariant, localeAsset: \App\Entity\EmploymentDocumentLocaleAsset, kind: string, locale: string}|null $resolved Resolved PDF metadata.
     * @param string $formatCode Company format code for QR URL.
     * @param string $fallbackTemplate Twig template when PDF is unavailable.
     * @param string $currentLocale Active request locale.
     * @return Response
     * @date 2026-06-12
     * @author Stephane H.
     */
    private function buildStampedPdfResponse(
        ?array $resolved,
        string $formatCode,
        string $fallbackTemplate,
        string $currentLocale,
    ): Response {
        if ($resolved === null) {
            return new Response(
                $this->renderView($fallbackTemplate, [
                    'currentLocale' => $currentLocale,
                ]),
                Response::HTTP_NOT_IMPLEMENTED,
                ['Content-Type' => 'text/html; charset=UTF-8'],
            );
        }

        try {
            $delivered = $this->employmentDocumentPdfDeliveryService->deliver(
                $resolved['kind'],
                $resolved['locale'],
                $resolved['absolutePath'],
                $resolved['variant'],
                $formatCode,
                $resolved['downloadFilename'],
            );
        } catch (EmploymentDocumentPdfStampException) {
            return new Response(
                $this->renderView($fallbackTemplate, [
                    'currentLocale' => $currentLocale,
                ]),
                Response::HTTP_NOT_IMPLEMENTED,
                ['Content-Type' => 'text/html; charset=UTF-8'],
            );
        }

        $response = new BinaryFileResponse($delivered['absolutePath']);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $delivered['downloadFilename'],
        );
        if ($delivered['isTemporary']) {
            $response->deleteFileAfterSend(true);
        }

        return $response;
    }
}
