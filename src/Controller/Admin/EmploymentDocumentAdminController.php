<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Employment\EmploymentDocumentKind;
use App\Entity\EmploymentDocumentLocaleAsset;
use App\Entity\EmploymentDocumentVariant;
use App\Entity\EmploymentPrintPlacement;
use App\Exception\Employment\EmploymentDocumentPdfStampException;
use App\Repository\EmploymentDocumentVariantRepository;
use App\Repository\EmploymentPrintPlacementRepository;
use App\Repository\TrackedCompanyRepository;
use App\Service\Employment\EmploymentDocumentLocaleAssetInput;
use App\Service\Employment\EmploymentDocumentPdfDeliveryService;
use App\Service\Employment\EmploymentDocumentStorageService;
use App\Service\Employment\EmploymentDocumentVariantManagementService;
use App\Service\Employment\EmploymentCountryPresentationLocaleResolver;
use App\Service\Employment\EmploymentPrintPlacementManagementService;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;

/**
 * Admin CRUD for employment CV and cover-letter document variants.
 */
#[IsGranted('ROLE_ADMIN')]
final class EmploymentDocumentAdminController
{
    private const CSRF_CREATE_CV = 'employment_cv_document_create';

    private const CSRF_EDIT_CV = 'employment_cv_document_edit';

    private const CSRF_ARCHIVE_CV = 'employment_cv_document_archive';

    private const CSRF_UNARCHIVE_CV = 'employment_cv_document_unarchive';

    private const CSRF_CREATE_LM = 'employment_lm_document_create';

    private const CSRF_EDIT_LM = 'employment_lm_document_edit';

    private const CSRF_ARCHIVE_LM = 'employment_lm_document_archive';

    private const CSRF_UNARCHIVE_LM = 'employment_lm_document_unarchive';

    private const CSRF_PLACEMENT_CV = 'employment_cv_document_placement';

    private const CSRF_PLACEMENT_LM = 'employment_lm_document_placement';

    /**
     * @brief Build employment document admin controller.
     *
     * @param EmploymentDocumentVariantRepository $variantRepository Variant repository.
     * @param EmploymentPrintPlacementRepository $placementRepository Placement repository.
     * @param EmploymentDocumentVariantManagementService $managementService Management service.
     * @param EmploymentPrintPlacementManagementService $placementManagementService Placement management service.
     * @param EmploymentDocumentStorageService $storageService File storage service.
     * @param EmploymentDocumentPdfDeliveryService $pdfDeliveryService Stamped PDF delivery service.
     * @param TrackedCompanyRepository $trackedCompanyRepository Company repository.
     * @param EmploymentCountryPresentationLocaleResolver $presentationLocaleResolver Active locales helper.
     * @param CsrfTokenManagerInterface $csrfTokenManager CSRF manager.
     * @param UrlGeneratorInterface $urlGenerator URL generator.
     * @param TranslatorInterface $translator Translator for stamp errors.
     * @return void
     * @date 2026-06-12
     * @author Stephane H.
     */
    public function __construct(
        private readonly EmploymentDocumentVariantRepository $variantRepository,
        private readonly EmploymentPrintPlacementRepository $placementRepository,
        private readonly EmploymentDocumentVariantManagementService $managementService,
        private readonly EmploymentPrintPlacementManagementService $placementManagementService,
        private readonly EmploymentDocumentStorageService $storageService,
        private readonly EmploymentDocumentPdfDeliveryService $pdfDeliveryService,
        private readonly TrackedCompanyRepository $trackedCompanyRepository,
        private readonly EmploymentCountryPresentationLocaleResolver $presentationLocaleResolver,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @brief List CV document variants.
     *
     * @param Environment $twig Twig environment.
     * @param Request $request HTTP request.
     * @return Response
     * @date 2026-06-01
     * @author Stephane H.
     */
    #[Route('/admin/employment/cv-documents', name: 'admin_employment_cv_documents_index', methods: ['GET'])]
    public function cvIndex(Environment $twig, Request $request): Response
    {
        return $this->renderIndex($twig, $request, EmploymentDocumentKind::CV);
    }

    /**
     * @brief List cover-letter document variants.
     *
     * @param Environment $twig Twig environment.
     * @param Request $request HTTP request.
     * @return Response
     * @date 2026-06-01
     * @author Stephane H.
     */
    #[Route('/admin/employment/lm-documents', name: 'admin_employment_lm_documents_index', methods: ['GET'])]
    public function lmIndex(Environment $twig, Request $request): Response
    {
        return $this->renderIndex($twig, $request, EmploymentDocumentKind::LM);
    }

    /**
     * @brief Create CV document variant.
     *
     * @param Request $request HTTP request.
     * @return RedirectResponse
     * @date 2026-06-01
     * @author Stephane H.
     */
    #[Route('/admin/employment/cv-documents/create', name: 'admin_employment_cv_documents_create', methods: ['POST'])]
    public function cvCreate(Request $request): RedirectResponse
    {
        return $this->handleCreate($request, EmploymentDocumentKind::CV, self::CSRF_CREATE_CV);
    }

    /**
     * @brief Create LM document variant.
     *
     * @param Request $request HTTP request.
     * @return RedirectResponse
     * @date 2026-06-01
     * @author Stephane H.
     */
    #[Route('/admin/employment/lm-documents/create', name: 'admin_employment_lm_documents_create', methods: ['POST'])]
    public function lmCreate(Request $request): RedirectResponse
    {
        return $this->handleCreate($request, EmploymentDocumentKind::LM, self::CSRF_CREATE_LM);
    }

    /**
     * @brief Save global CV QR link placement coordinates.
     *
     * @param Request $request HTTP request.
     * @return RedirectResponse
     * @date 2026-06-01
     * @author Stephane H.
     */
    #[Route('/admin/employment/cv-documents/placement', name: 'admin_employment_cv_documents_placement', methods: ['POST'])]
    public function cvPlacementSave(Request $request): RedirectResponse
    {
        return $this->handlePlacementSave($request, EmploymentDocumentKind::CV, self::CSRF_PLACEMENT_CV);
    }

    /**
     * @brief Save global LM QR link placement coordinates.
     *
     * @param Request $request HTTP request.
     * @return RedirectResponse
     * @date 2026-06-01
     * @author Stephane H.
     */
    #[Route('/admin/employment/lm-documents/placement', name: 'admin_employment_lm_documents_placement', methods: ['POST'])]
    public function lmPlacementSave(Request $request): RedirectResponse
    {
        return $this->handlePlacementSave($request, EmploymentDocumentKind::LM, self::CSRF_PLACEMENT_LM);
    }

    /**
     * @brief Update CV document variant.
     *
     * @param Request $request HTTP request.
     * @param int $id Variant id.
     * @return RedirectResponse
     * @date 2026-06-01
     * @author Stephane H.
     */
    #[Route('/admin/employment/cv-documents/{id}/edit', name: 'admin_employment_cv_documents_edit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cvEdit(Request $request, int $id): RedirectResponse
    {
        return $this->handleEdit($request, $id, EmploymentDocumentKind::CV, self::CSRF_EDIT_CV);
    }

    /**
     * @brief Update LM document variant.
     *
     * @param Request $request HTTP request.
     * @param int $id Variant id.
     * @return RedirectResponse
     * @date 2026-06-01
     * @author Stephane H.
     */
    #[Route('/admin/employment/lm-documents/{id}/edit', name: 'admin_employment_lm_documents_edit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function lmEdit(Request $request, int $id): RedirectResponse
    {
        return $this->handleEdit($request, $id, EmploymentDocumentKind::LM, self::CSRF_EDIT_LM);
    }

    /**
     * @brief Archive CV document variant.
     *
     * @param Request $request HTTP request.
     * @param int $id Variant id.
     * @return RedirectResponse
     * @date 2026-06-01
     * @author Stephane H.
     */
    #[Route('/admin/employment/cv-documents/{id}/archive', name: 'admin_employment_cv_documents_archive', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cvArchive(Request $request, int $id): RedirectResponse
    {
        return $this->handleArchive($request, $id, EmploymentDocumentKind::CV, self::CSRF_ARCHIVE_CV);
    }

    /**
     * @brief Archive LM document variant.
     *
     * @param Request $request HTTP request.
     * @param int $id Variant id.
     * @return RedirectResponse
     * @date 2026-06-01
     * @author Stephane H.
     */
    #[Route('/admin/employment/lm-documents/{id}/archive', name: 'admin_employment_lm_documents_archive', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function lmArchive(Request $request, int $id): RedirectResponse
    {
        return $this->handleArchive($request, $id, EmploymentDocumentKind::LM, self::CSRF_ARCHIVE_LM);
    }

    /**
     * @brief Unarchive CV document variant.
     *
     * @param Request $request HTTP request.
     * @param int $id Variant id.
     * @return RedirectResponse
     * @date 2026-06-16
     * @author Stephane H.
     */
    #[Route('/admin/employment/cv-documents/{id}/unarchive', name: 'admin_employment_cv_documents_unarchive', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cvUnarchive(Request $request, int $id): RedirectResponse
    {
        return $this->handleUnarchive($request, $id, EmploymentDocumentKind::CV, self::CSRF_UNARCHIVE_CV);
    }

    /**
     * @brief Unarchive LM document variant.
     *
     * @param Request $request HTTP request.
     * @param int $id Variant id.
     * @return RedirectResponse
     * @date 2026-06-16
     * @author Stephane H.
     */
    #[Route('/admin/employment/lm-documents/{id}/unarchive', name: 'admin_employment_lm_documents_unarchive', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function lmUnarchive(Request $request, int $id): RedirectResponse
    {
        return $this->handleUnarchive($request, $id, EmploymentDocumentKind::LM, self::CSRF_UNARCHIVE_LM);
    }

    /**
     * @brief Download CV template or PDF file for a locale.
     *
     * @param int $id Variant id.
     * @param string $locale Locale code.
     * @param string $role template or pdf.
     * @return Response
     * @date 2026-06-01
     * @author Stephane H.
     */
    #[Route('/admin/employment/cv-documents/{id}/locale/{locale}/{role}', name: 'admin_employment_cv_documents_download', requirements: ['id' => '\d+', 'role' => 'template|pdf'], methods: ['GET'])]
    public function cvDownload(int $id, string $locale, string $role): Response
    {
        return $this->handleDownload($id, EmploymentDocumentKind::CV, $locale, $role);
    }

    /**
     * @brief Download LM template or PDF file for a locale.
     *
     * @param int $id Variant id.
     * @param string $locale Locale code.
     * @param string $role template or pdf.
     * @return Response
     * @date 2026-06-01
     * @author Stephane H.
     */
    #[Route('/admin/employment/lm-documents/{id}/locale/{locale}/{role}', name: 'admin_employment_lm_documents_download', requirements: ['id' => '\d+', 'role' => 'template|pdf'], methods: ['GET'])]
    public function lmDownload(int $id, string $locale, string $role): Response
    {
        return $this->handleDownload($id, EmploymentDocumentKind::LM, $locale, $role);
    }

    /**
     * @brief Preview stamped CV PDF with QR overlay for a locale.
     *
     * @param Request $request HTTP request.
     * @param int $id Variant id.
     * @param string $locale Locale code.
     * @return Response
     * @date 2026-06-12
     * @author Stephane H.
     */
    #[Route('/admin/employment/cv-documents/{id}/locale/{locale}/preview-pdf', name: 'admin_employment_cv_documents_preview_pdf', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function cvPreviewPdf(Request $request, int $id, string $locale): Response
    {
        return $this->handlePreviewPdf($request, $id, EmploymentDocumentKind::CV, $locale);
    }

    /**
     * @brief Preview stamped LM PDF with QR overlay for a locale.
     *
     * @param Request $request HTTP request.
     * @param int $id Variant id.
     * @param string $locale Locale code.
     * @return Response
     * @date 2026-06-12
     * @author Stephane H.
     */
    #[Route('/admin/employment/lm-documents/{id}/locale/{locale}/preview-pdf', name: 'admin_employment_lm_documents_preview_pdf', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function lmPreviewPdf(Request $request, int $id, string $locale): Response
    {
        return $this->handlePreviewPdf($request, $id, EmploymentDocumentKind::LM, $locale);
    }

    /**
     * @brief Render index for a document kind.
     *
     * @param Environment $twig Twig environment.
     * @param Request $request HTTP request.
     * @param string $kind cv or lm.
     * @return Response
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function renderIndex(Environment $twig, Request $request, string $kind): Response
    {
        $this->managementService->ensurePrintPlacementsExist();

        $page = max(1, (int) $request->query->get('page', 1));
        $search = $this->managementService->normalizeSearchQuery((string) $request->query->get('q', ''));
        $includeArchived = (string) $request->query->get('archived', '') === '1';
        $createdAfter = $this->managementService->normalizeDateFilter((string) $request->query->get('created_after', ''));
        $createdBefore = $this->managementService->normalizeDateFilter((string) $request->query->get('created_before', ''));
        $updatedAfter = $this->managementService->normalizeDateFilter((string) $request->query->get('updated_after', ''));
        $updatedBefore = $this->managementService->normalizeDateFilter((string) $request->query->get('updated_before', ''));

        $allowedSorts = ['name', 'created', 'updated'];
        $sortParam = $request->query->get('sort');
        $sort = is_string($sortParam) && in_array($sortParam, $allowedSorts, true) ? $sortParam : 'created';
        $dirParam = $request->query->get('dir');
        $sortDir = is_string($dirParam) && in_array(strtolower($dirParam), ['asc', 'desc'], true)
            ? strtolower($dirParam)
            : 'desc';

        $result = $this->variantRepository->findForAdminList(
            $kind,
            $search,
            $includeArchived,
            $createdAfter,
            $createdBefore,
            $updatedAfter,
            $updatedBefore,
            $sort,
            $sortDir,
            $page,
            20,
        );

        $placement = $this->placementRepository->findOneByKind($kind);

        $archiveBlockReasons = [];
        foreach ($result['items'] as $variant) {
            if (!$variant->isArchived()) {
                $reason = $this->managementService->getArchiveBlockReason($variant);
                if ($reason !== null) {
                    $variantId = $variant->getId();
                    if ($variantId !== null) {
                        $archiveBlockReasons[$variantId] = $reason;
                    }
                }
            }
        }

        $activeKindCount = $this->variantRepository->countActiveByKind($kind);

        $listingQuery = array_filter([
            'q' => $search !== '' ? (string) $request->query->get('q', '') : null,
            'archived' => $includeArchived ? '1' : null,
            'created_after' => $createdAfter,
            'created_before' => $createdBefore,
            'updated_after' => $updatedAfter,
            'updated_before' => $updatedBefore,
            'sort' => $sort,
            'dir' => $sortDir,
            'page' => $page > 1 ? $page : null,
        ], static fn ($value): bool => $value !== null && $value !== '');

        return new Response($twig->render('admin/employment/documents/index.html.twig', [
            'documentKind' => $kind,
            'variants' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'search' => (string) $request->query->get('q', ''),
            'includeArchived' => $includeArchived,
            'createdAfter' => $createdAfter ?? '',
            'createdBefore' => $createdBefore ?? '',
            'updatedAfter' => $updatedAfter ?? '',
            'updatedBefore' => $updatedBefore ?? '',
            'sort' => $sort,
            'sortDir' => $sortDir,
            'listingQuery' => $listingQuery,
            'activeLocales' => $this->presentationLocaleResolver->getActiveLocales(),
            'printPlacement' => $placement instanceof EmploymentPrintPlacement ? $placement : null,
            'indexRoute' => $this->indexRouteForKind($kind),
            'createRoute' => $this->createRouteForKind($kind),
            'editRoute' => $this->editRouteForKind($kind),
            'archiveRoute' => $this->archiveRouteForKind($kind),
            'unarchiveRoute' => $this->unarchiveRouteForKind($kind),
            'downloadRoute' => $this->downloadRouteForKind($kind),
            'previewRoute' => $this->previewRouteForKind($kind),
            'csrfCreateToken' => $this->csrfTokenManager->getToken($this->csrfCreateIdForKind($kind))->getValue(),
            'csrfEditToken' => $this->csrfTokenManager->getToken($this->csrfEditIdForKind($kind))->getValue(),
            'csrfArchiveToken' => $this->csrfTokenManager->getToken($this->csrfArchiveIdForKind($kind))->getValue(),
            'csrfUnarchiveToken' => $this->csrfTokenManager->getToken($this->csrfUnarchiveIdForKind($kind))->getValue(),
            'placementSaveRoute' => $this->placementSaveRouteForKind($kind),
            'csrfPlacementToken' => $this->csrfTokenManager->getToken($this->csrfPlacementIdForKind($kind))->getValue(),
            'archiveBlockReasons' => $archiveBlockReasons,
            'soleActiveDefaultEnforced' => $activeKindCount <= 1,
        ]));
    }

    /**
     * @brief Handle placement settings POST for a kind.
     *
     * @param Request $request HTTP request.
     * @param string $kind cv or lm.
     * @param string $csrfId CSRF token id.
     * @return RedirectResponse
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function handlePlacementSave(Request $request, string $kind, string $csrfId): RedirectResponse
    {
        $indexRoute = $this->indexRouteForKind($kind);

        $token = (string) $request->request->get('_token', '');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken($csrfId, $token))) {
            $request->getSession()->getFlashBag()->add('error', 'employment.documents.flash.csrf_invalid');

            return new RedirectResponse($this->urlGenerator->generate($indexRoute));
        }

        $error = $this->placementManagementService->update(
            $kind,
            (string) $request->request->get('link_x', ''),
            (string) $request->request->get('link_y', ''),
            (string) $request->request->get('square_size_cm', ''),
        );

        if ($error !== null) {
            $request->getSession()->getFlashBag()->add('error', $error);
        } else {
            $request->getSession()->getFlashBag()->add('success', 'employment.documents.placement.flash.saved');
        }

        return new RedirectResponse($this->urlGenerator->generate($indexRoute));
    }

    /**
     * @brief Handle create POST for a kind.
     *
     * @param Request $request HTTP request.
     * @param string $kind cv or lm.
     * @param string $csrfId CSRF token id.
     * @return RedirectResponse
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function handleCreate(Request $request, string $kind, string $csrfId): RedirectResponse
    {
        $indexRoute = $this->indexRouteForKind($kind);

        $token = (string) $request->request->get('_token', '');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken($csrfId, $token))) {
            $request->getSession()->getFlashBag()->add('error', 'employment.documents.flash.csrf_invalid');

            return new RedirectResponse($this->urlGenerator->generate($indexRoute));
        }

        $result = $this->managementService->create(
            $kind,
            (string) $request->request->get('name', ''),
            $this->localeInputsFromRequest($request),
            (string) $request->request->get('link_x', ''),
            (string) $request->request->get('link_y', ''),
            (string) $request->request->get('square_size_cm', ''),
            $this->defaultFlagFromRequest($request, $kind),
        );

        if ($result['error'] !== null) {
            $request->getSession()->getFlashBag()->add('error', $result['error']);
        } else {
            $request->getSession()->getFlashBag()->add('success', 'employment.documents.flash.created');
        }

        return new RedirectResponse($this->urlGenerator->generate($indexRoute));
    }

    /**
     * @brief Handle edit POST for a kind.
     *
     * @param Request $request HTTP request.
     * @param int $id Variant id.
     * @param string $kind cv or lm.
     * @param string $csrfId CSRF token id.
     * @return RedirectResponse
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function handleEdit(Request $request, int $id, string $kind, string $csrfId): RedirectResponse
    {
        $indexRoute = $this->indexRouteForKind($kind);

        $token = (string) $request->request->get('_token', '');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken($csrfId, $token))) {
            $request->getSession()->getFlashBag()->add('error', 'employment.documents.flash.csrf_invalid');

            return new RedirectResponse($this->urlGenerator->generate($indexRoute));
        }

        $variant = $this->findVariantOrNull($id, $kind);
        if (!$variant instanceof EmploymentDocumentVariant) {
            throw new NotFoundHttpException();
        }

        $error = $this->managementService->update(
            $variant,
            (string) $request->request->get('name', ''),
            $this->localeInputsFromRequest($request),
            (string) $request->request->get('link_x', ''),
            (string) $request->request->get('link_y', ''),
            (string) $request->request->get('square_size_cm', ''),
            $this->defaultFlagFromRequest($request, $kind),
        );

        if ($error !== null) {
            $request->getSession()->getFlashBag()->add('error', $error);
        } else {
            $request->getSession()->getFlashBag()->add('success', 'employment.documents.flash.updated');
        }

        return new RedirectResponse($this->urlGenerator->generate($indexRoute));
    }

    /**
     * @brief Handle archive POST for a kind.
     *
     * @param Request $request HTTP request.
     * @param int $id Variant id.
     * @param string $kind cv or lm.
     * @param string $csrfId CSRF token id.
     * @return RedirectResponse
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function handleArchive(Request $request, int $id, string $kind, string $csrfId): RedirectResponse
    {
        $indexRoute = $this->indexRouteForKind($kind);

        $token = (string) $request->request->get('_token', '');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken($csrfId, $token))) {
            $request->getSession()->getFlashBag()->add('error', 'employment.documents.flash.csrf_invalid');

            return new RedirectResponse($this->urlGenerator->generate($indexRoute));
        }

        $variant = $this->findVariantOrNull($id, $kind);
        if ($variant instanceof EmploymentDocumentVariant) {
            $error = $this->managementService->archive($variant);
            if ($error !== null) {
                $request->getSession()->getFlashBag()->add('error', $error);
            } else {
                $request->getSession()->getFlashBag()->add('success', 'employment.documents.flash.archived');
            }
        }

        return new RedirectResponse($this->urlGenerator->generate($indexRoute));
    }

    /**
     * @brief Handle unarchive POST for a kind.
     *
     * @param Request $request HTTP request.
     * @param int $id Variant id.
     * @param string $kind cv or lm.
     * @param string $csrfId CSRF token id.
     * @return RedirectResponse
     * @date 2026-06-16
     * @author Stephane H.
     */
    private function handleUnarchive(Request $request, int $id, string $kind, string $csrfId): RedirectResponse
    {
        $indexRoute = $this->indexRouteForKind($kind);

        $token = (string) $request->request->get('_token', '');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken($csrfId, $token))) {
            $request->getSession()->getFlashBag()->add('error', 'employment.documents.flash.csrf_invalid');

            return new RedirectResponse($this->urlGenerator->generate($indexRoute));
        }

        $variant = $this->findVariantOrNull($id, $kind);
        if ($variant instanceof EmploymentDocumentVariant) {
            $this->managementService->unarchive($variant);
            $request->getSession()->getFlashBag()->add('success', 'employment.documents.flash.unarchived');
        }

        return new RedirectResponse($this->urlGenerator->generate($indexRoute));
    }

    /**
     * @brief Stream stored file to admin user.
     *
     * @param int $id Variant id.
     * @param string $kind cv or lm.
     * @param string $locale Locale code.
     * @param string $role template or pdf.
     * @return Response
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function handleDownload(int $id, string $kind, string $locale, string $role): Response
    {
        $variant = $this->findVariantOrNull($id, $kind);
        if (!$variant instanceof EmploymentDocumentVariant) {
            throw new NotFoundHttpException();
        }

        $asset = $variant->findLocaleAsset($locale);
        if (!$asset instanceof EmploymentDocumentLocaleAsset) {
            throw new NotFoundHttpException();
        }

        $relativePath = $role === 'pdf' ? $asset->getPdfStoragePath() : $asset->getTemplateStoragePath();
        $originalName = $role === 'pdf' ? $asset->getPdfOriginalFilename() : $asset->getTemplateOriginalFilename();
        if ($relativePath === null || $relativePath === '') {
            throw new NotFoundHttpException();
        }

        $absolute = $this->storageService->resolveAbsolutePath($relativePath);
        if ($absolute === null) {
            throw new NotFoundHttpException();
        }

        $response = new BinaryFileResponse($absolute);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $originalName ?? basename($absolute),
        );

        return $response;
    }

    /**
     * @brief Stream stamped PDF inline for admin preview.
     *
     * @param Request $request HTTP request.
     * @param int $id Variant id.
     * @param string $kind cv or lm.
     * @param string $locale Locale code.
     * @return Response
     * @date 2026-06-12
     * @author Stephane H.
     */
    private function handlePreviewPdf(Request $request, int $id, string $kind, string $locale): Response
    {
        $variant = $this->findVariantOrNull($id, $kind);
        if (!$variant instanceof EmploymentDocumentVariant) {
            throw new NotFoundHttpException();
        }

        $asset = $variant->findLocaleAsset($locale);
        if (!$asset instanceof EmploymentDocumentLocaleAsset) {
            throw new NotFoundHttpException();
        }

        $relativePath = $asset->getPdfStoragePath();
        if ($relativePath === null || $relativePath === '') {
            throw new NotFoundHttpException();
        }

        $absolute = $this->storageService->resolveAbsolutePath($relativePath);
        if ($absolute === null) {
            throw new NotFoundHttpException();
        }

        $downloadName = $asset->getPdfOriginalFilename();
        if ($downloadName === null || trim($downloadName) === '') {
            $downloadName = sprintf('%s-%s.pdf', $kind, $locale);
        }

        $formatCode = trim((string) $request->query->get('format', ''));
        if ($formatCode === '') {
            $variantId = $variant->getId();
            if ($variantId !== null && $variantId > 0) {
                $formatCode = $this->trackedCompanyRepository->findFirstActiveCodeReferencingVariant($variantId, $kind) ?? '';
            }
        }

        try {
            $delivered = $this->pdfDeliveryService->deliver(
                $kind,
                $locale,
                $absolute,
                $variant,
                $formatCode,
                $downloadName,
            );
        } catch (EmploymentDocumentPdfStampException $exception) {
            throw new NotFoundHttpException($this->translator->trans($exception->getTranslationKey(), [], 'messages'));
        }

        $response = new BinaryFileResponse($delivered['absolutePath']);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $delivered['downloadFilename'],
        );
        if ($delivered['isTemporary']) {
            $response->deleteFileAfterSend(true);
        }

        return $response;
    }

    /**
     * @brief Read default document checkbox from request for cv or lm kind.
     *
     * @param Request $request HTTP request.
     * @param string $kind cv or lm.
     * @return bool True when admin checked default for this kind.
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function defaultFlagFromRequest(Request $request, string $kind): bool
    {
        return match ($kind) {
            EmploymentDocumentKind::CV => $request->request->getBoolean('is_default_cv'),
            EmploymentDocumentKind::LM => $request->request->getBoolean('is_default_lm'),
            default => false,
        };
    }

    /**
     * @brief Build per-locale upload inputs from multipart request.
     *
     * @param Request $request HTTP request.
     * @return list<EmploymentDocumentLocaleAssetInput>
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function localeInputsFromRequest(Request $request): array
    {
        $inputs = [];
        foreach ($this->presentationLocaleResolver->getActiveLocales() as $locale) {
            $templateKey = 'template_'.$locale;
            $pdfKey = 'pdf_'.$locale;
            $templateFile = $request->files->get($templateKey);
            $pdfFile = $request->files->get($pdfKey);

            $inputs[] = new EmploymentDocumentLocaleAssetInput(
                locale: $locale,
                templateFile: $templateFile instanceof \Symfony\Component\HttpFoundation\File\UploadedFile ? $templateFile : null,
                pdfFile: $pdfFile instanceof \Symfony\Component\HttpFoundation\File\UploadedFile ? $pdfFile : null,
                removeTemplate: $request->request->getBoolean('remove_template_'.$locale),
                removePdf: $request->request->getBoolean('remove_pdf_'.$locale),
            );
        }

        return $inputs;
    }

    /**
     * @brief Find variant matching kind or null.
     *
     * @param int $id Variant id.
     * @param string $kind cv or lm.
     * @return EmploymentDocumentVariant|null
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function findVariantOrNull(int $id, string $kind): ?EmploymentDocumentVariant
    {
        $variant = $this->variantRepository->find($id);
        if (!$variant instanceof EmploymentDocumentVariant || $variant->getKind() !== $kind) {
            return null;
        }

        return $variant;
    }

    private function indexRouteForKind(string $kind): string
    {
        return $kind === EmploymentDocumentKind::LM
            ? 'admin_employment_lm_documents_index'
            : 'admin_employment_cv_documents_index';
    }

    private function createRouteForKind(string $kind): string
    {
        return $kind === EmploymentDocumentKind::LM
            ? 'admin_employment_lm_documents_create'
            : 'admin_employment_cv_documents_create';
    }

    private function editRouteForKind(string $kind): string
    {
        return $kind === EmploymentDocumentKind::LM
            ? 'admin_employment_lm_documents_edit'
            : 'admin_employment_cv_documents_edit';
    }

    private function archiveRouteForKind(string $kind): string
    {
        return $kind === EmploymentDocumentKind::LM
            ? 'admin_employment_lm_documents_archive'
            : 'admin_employment_cv_documents_archive';
    }

    /**
     * @brief Resolve unarchive route name for document kind.
     *
     * @param string $kind cv or lm.
     * @return string
     * @date 2026-06-16
     * @author Stephane H.
     */
    private function unarchiveRouteForKind(string $kind): string
    {
        return $kind === EmploymentDocumentKind::LM
            ? 'admin_employment_lm_documents_unarchive'
            : 'admin_employment_cv_documents_unarchive';
    }

    private function downloadRouteForKind(string $kind): string
    {
        return $kind === EmploymentDocumentKind::LM
            ? 'admin_employment_lm_documents_download'
            : 'admin_employment_cv_documents_download';
    }

    private function previewRouteForKind(string $kind): string
    {
        return $kind === EmploymentDocumentKind::LM
            ? 'admin_employment_lm_documents_preview_pdf'
            : 'admin_employment_cv_documents_preview_pdf';
    }

    private function csrfCreateIdForKind(string $kind): string
    {
        return $kind === EmploymentDocumentKind::LM ? self::CSRF_CREATE_LM : self::CSRF_CREATE_CV;
    }

    private function csrfEditIdForKind(string $kind): string
    {
        return $kind === EmploymentDocumentKind::LM ? self::CSRF_EDIT_LM : self::CSRF_EDIT_CV;
    }

    private function csrfArchiveIdForKind(string $kind): string
    {
        return $kind === EmploymentDocumentKind::LM ? self::CSRF_ARCHIVE_LM : self::CSRF_ARCHIVE_CV;
    }

    /**
     * @brief Resolve unarchive CSRF id for document kind.
     *
     * @param string $kind cv or lm.
     * @return string
     * @date 2026-06-16
     * @author Stephane H.
     */
    private function csrfUnarchiveIdForKind(string $kind): string
    {
        return $kind === EmploymentDocumentKind::LM ? self::CSRF_UNARCHIVE_LM : self::CSRF_UNARCHIVE_CV;
    }

    private function placementSaveRouteForKind(string $kind): string
    {
        return $kind === EmploymentDocumentKind::LM
            ? 'admin_employment_lm_documents_placement'
            : 'admin_employment_cv_documents_placement';
    }

    private function csrfPlacementIdForKind(string $kind): string
    {
        return $kind === EmploymentDocumentKind::LM ? self::CSRF_PLACEMENT_LM : self::CSRF_PLACEMENT_CV;
    }
}
