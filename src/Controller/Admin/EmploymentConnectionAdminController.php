<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Employment\ConnectionKind;
use App\Repository\CvConnectionLogRepository;
use App\Repository\TrackedCompanyRepository;
use App\Service\Employment\EmploymentCountryList;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;

/**
 * Admin list and detail for CV connection logs.
 */
#[IsGranted('ROLE_ADMIN')]
class EmploymentConnectionAdminController
{
    /**
     * @brief Build employment connection admin controller.
     *
     * @param CvConnectionLogRepository $cvConnectionLogRepository Log repository.
     * @param TrackedCompanyRepository $trackedCompanyRepository Company repository.
     * @param EmploymentCountryList $employmentCountryList Country list.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function __construct(
        private readonly CvConnectionLogRepository $cvConnectionLogRepository,
        private readonly TrackedCompanyRepository $trackedCompanyRepository,
        private readonly EmploymentCountryList $employmentCountryList,
    ) {
    }

    /**
     * @brief List connection logs with filters.
     *
     * @param Environment $twig Twig environment.
     * @param Request $request HTTP request.
     * @return Response
     * @date 2026-06-01
     * @author Stephane H.
     */
    #[Route('/admin/employment/connections', name: 'admin_employment_connections_index', methods: ['GET'])]
    public function index(Environment $twig, Request $request): Response
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $filters = [
            'kind' => trim((string) $request->query->get('kind', '')),
            'country' => strtoupper(trim((string) $request->query->get('country', ''))),
            'companyId' => (int) $request->query->get('company_id', 0),
            'format' => trim((string) $request->query->get('format', '')),
            'ip' => trim((string) $request->query->get('ip', '')),
            'gatePassed' => (string) $request->query->get('gate_passed', ''),
            'countable' => (string) $request->query->get('countable', ''),
            'from' => $this->parseDateFilter((string) $request->query->get('from_date', ''), false),
            'to' => $this->parseDateFilter((string) $request->query->get('to_date', ''), true),
        ];

        $result = $this->cvConnectionLogRepository->findForAdminList($filters, $page, 30);

        return new Response($twig->render('admin/employment/connections/index.html.twig', [
            'logs' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'filters' => $filters,
            'fromDateFilter' => (string) $request->query->get('from_date', ''),
            'toDateFilter' => (string) $request->query->get('to_date', ''),
            'connectionKinds' => ConnectionKind::all(),
            'countryCodes' => $this->employmentCountryList->getCountryCodes(),
            'companies' => $this->trackedCompanyRepository->findBy([], ['name' => 'ASC'], 500),
        ]));
    }

    /**
     * @brief Show single connection log detail.
     *
     * @param Environment $twig Twig environment.
     * @param int $id Log id.
     * @return Response
     * @date 2026-06-01
     * @author Stephane H.
     */
    #[Route('/admin/employment/connections/{id}', name: 'admin_employment_connections_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Environment $twig, int $id): Response
    {
        $log = $this->cvConnectionLogRepository->findOneForAdminShow($id);
        if ($log === null) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
        }

        return new Response($twig->render('admin/employment/connections/show.html.twig', [
            'log' => $log,
        ]));
    }

    /**
     * @brief Parse optional date filter from query string.
     *
     * @param string $value Raw date Y-m-d.
     * @param bool $endOfDay When true use 23:59:59.
     * @return DateTimeImmutable|null
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function parseDateFilter(string $value, bool $endOfDay): ?DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if ($parsed === false) {
            return null;
        }

        return $endOfDay ? $parsed->setTime(23, 59, 59) : $parsed->setTime(0, 0, 0);
    }
}
