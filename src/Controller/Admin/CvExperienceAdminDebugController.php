<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\Cv\CvExperienceAdminLogger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @brief Dev-only endpoint receiving browser audit events for experience admin debugging.
 */
final class CvExperienceAdminDebugController extends AbstractController
{
    /**
     * @brief Wire debug audit log controller.
     *
     * @param CvExperienceAdminLogger $experienceAdminLogger Structured experience audit logger.
     * @param bool $debug Kernel debug flag (dev only).
     * @return void
     * @date 2026-06-04
     * @author Stephane H.
     */
    public function __construct(
        private readonly CvExperienceAdminLogger $experienceAdminLogger,
        private readonly bool $debug,
    ) {
    }

    /**
     * @brief Accept JSON audit payloads from cv-experience-admin.js (dev only).
     *
     * @param Request $request HTTP request with JSON body.
     * @return JsonResponse Empty 204 when disabled, otherwise `{ok: true}`.
     * @date 2026-06-04
     * @author Stephane H.
     */
    #[Route('/admin/cv/experience-audit-log', name: 'admin_cv_experience_audit_log', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function clientAuditLog(Request $request): JsonResponse
    {
        if (!$this->debug) {
            return new JsonResponse(null, 204);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return new JsonResponse(['ok' => false], 400);
        }

        $this->experienceAdminLogger->logClientEvent($payload);

        return new JsonResponse(['ok' => true]);
    }
}
