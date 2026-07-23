<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Cv\CvAccessRequestStatus;
use App\Entity\CvAccessRequest;
use App\Repository\CvAccessRequestRepository;
use App\Service\Cv\CvAccessRequestService;
use App\Service\Http\FlashMessageHelper;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;

/**
 * @brief Admin review queue for public CV access requests.
 *
 * @date 2026-07-22
 * @author Stephane H.
 */
#[IsGranted('ROLE_CV_EDIT')]
class EmploymentCompanyAccessRequestAdminController
{
    private const CSRF_APPROVE = 'employment_access_request_approve';

    private const CSRF_REJECT = 'employment_access_request_reject';

    /**
     * @brief Build access request admin controller.
     *
     * @param CvAccessRequestRepository $accessRequestRepository Access request repository.
     * @param CvAccessRequestService $accessRequestService Access request workflow service.
     * @param CsrfTokenManagerInterface $csrfTokenManager CSRF token manager.
     * @param UrlGeneratorInterface $urlGenerator URL generator.
     * @return void
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function __construct(
        private readonly CvAccessRequestRepository $accessRequestRepository,
        private readonly CvAccessRequestService $accessRequestService,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @brief Render access request listing filtered by status.
     *
     * @param Environment $twig Twig environment.
     * @param Request $request Current request.
     * @return Response
     * @date 2026-07-22
     * @author Stephane H.
     */
    #[Route('/admin/employment/access-requests', name: 'admin_employment_access_requests_index', methods: ['GET'])]
    public function index(Environment $twig, Request $request): Response
    {
        $statusFilter = trim((string) $request->query->get('status', CvAccessRequestStatus::Pending->value));
        if ($statusFilter !== '' && CvAccessRequestStatus::tryFrom($statusFilter) === null) {
            $statusFilter = CvAccessRequestStatus::Pending->value;
        }

        return new Response($twig->render('admin/employment/access_requests/index.html.twig', [
            'requests' => $this->accessRequestRepository->findForAdminList($statusFilter !== '' ? $statusFilter : null),
            'statusFilter' => $statusFilter,
            'pendingCount' => $this->accessRequestRepository->countPending(),
            'csrfApproveToken' => $this->csrfTokenManager->getToken(self::CSRF_APPROVE)->getValue(),
            'csrfRejectToken' => $this->csrfTokenManager->getToken(self::CSRF_REJECT)->getValue(),
        ]));
    }

    /**
     * @brief Approve a pending access request and create the tracked company.
     *
     * @param Request $request Current request.
     * @param int $id Access request id.
     * @return Response
     * @date 2026-07-22
     * @author Stephane H.
     */
    #[Route('/admin/employment/access-requests/{id}/approve', name: 'admin_employment_access_requests_approve', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function approve(Request $request, int $id): Response
    {
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_APPROVE, (string) $request->request->get('_csrf_token', '')))) {
            FlashMessageHelper::add($request, 'error', 'admin.access_requests.flash.csrf_invalid');

            return $this->redirectToIndex($request);
        }

        $accessRequest = $this->accessRequestRepository->find($id);
        if (!$accessRequest instanceof CvAccessRequest) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $result = $this->accessRequestService->approve(
            $accessRequest,
            (string) $request->request->get('reviewer_note', ''),
        );

        if ($result['errorKey'] !== null || $result['company'] === null) {
            FlashMessageHelper::add($request, 'error', $result['errorKey'] ?? 'admin.access_requests.flash.company_create_failed');

            return $this->redirectToIndex($request);
        }

        $formatUrl = $this->urlGenerator->generate(
            'cv_show',
            ['format' => $result['company']->getCode()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        FlashMessageHelper::add($request, 'success', [
            'message' => 'admin.access_requests.flash.approved',
            'parameters' => [
                '%company%' => $result['company']->getName(),
                '%code%' => $result['company']->getCode(),
                '%url%' => $formatUrl,
            ],
        ]);

        return $this->redirectToIndex($request, CvAccessRequestStatus::Approved->value);
    }

    /**
     * @brief Reject a pending access request.
     *
     * @param Request $request Current request.
     * @param int $id Access request id.
     * @return Response
     * @date 2026-07-22
     * @author Stephane H.
     */
    #[Route('/admin/employment/access-requests/{id}/reject', name: 'admin_employment_access_requests_reject', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function reject(Request $request, int $id): Response
    {
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_REJECT, (string) $request->request->get('_csrf_token', '')))) {
            FlashMessageHelper::add($request, 'error', 'admin.access_requests.flash.csrf_invalid');

            return $this->redirectToIndex($request);
        }

        $accessRequest = $this->accessRequestRepository->find($id);
        if (!$accessRequest instanceof CvAccessRequest) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $error = $this->accessRequestService->reject(
            $accessRequest,
            (string) $request->request->get('reviewer_note', ''),
        );

        if ($error !== null) {
            FlashMessageHelper::add($request, 'error', $error);

            return $this->redirectToIndex($request);
        }

        FlashMessageHelper::add($request, 'success', 'admin.access_requests.flash.rejected');

        return $this->redirectToIndex($request, CvAccessRequestStatus::Rejected->value);
    }

    /**
     * @brief Redirect back to the access-request index with optional status filter.
     *
     * @param Request $request Current request.
     * @param string|null $status Optional status filter override.
     * @return RedirectResponse
     * @date 2026-07-22
     * @author Stephane H.
     */
    private function redirectToIndex(Request $request, ?string $status = null): RedirectResponse
    {
        $resolvedStatus = $status ?? trim((string) $request->query->get('status', CvAccessRequestStatus::Pending->value));
        $params = [];
        if ($resolvedStatus !== '') {
            $params['status'] = $resolvedStatus;
        }

        return new RedirectResponse($this->urlGenerator->generate('admin_employment_access_requests_index', $params));
    }
}
