<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\TrackedCompany;
use App\Repository\TrackedCompanyRepository;
use App\Service\Cv\CvSkillsAdminTreeRenderer;
use App\Service\Employment\CompanyCvSkillsCustomizationService;
use App\Service\Locale\LocaleConfigurationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @brief JSON admin API for per-company CV skills catalog CRUD.
 */
#[IsGranted('ROLE_ADMIN')]
final class EmploymentCompanyCvSkillsCatalogAdminController extends AbstractController
{
    /**
     * @brief Wire company skills catalog admin API.
     *
     * @param TrackedCompanyRepository $trackedCompanyRepository Company repository.
     * @param CompanyCvSkillsCustomizationService $companyCvSkillsCustomizationService Company skills service.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function __construct(
        private readonly TrackedCompanyRepository $trackedCompanyRepository,
        private readonly CompanyCvSkillsCustomizationService $companyCvSkillsCustomizationService,
        private readonly LocaleConfigurationService $localeConfigurationService,
        private readonly CvSkillsAdminTreeRenderer $cvSkillsAdminTreeRenderer,
    ) {
    }

    /**
     * @brief Create or update a category for a company skills override.
     *
     * @param Request $request HTTP request.
     * @param int $id Tracked company id.
     * @return JsonResponse Success or validation error payload.
     * @date 2026-06-01
     * @author Stephane H.
     */
    #[Route('/admin/employment/companies/{id}/cv-customization/skills-catalog/category', name: 'admin_employment_companies_cv_skills_catalog_category_save', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function saveCategory(Request $request, int $id): JsonResponse
    {
        $company = $this->resolveCompany($id);
        if (!$this->isCsrfTokenValid(CompanyCvSkillsCustomizationService::CSRF_SKILLS, (string) $request->request->get('_csrf_token', ''))) {
            return $this->jsonError('employment.companies.flash.csrf_invalid', Response::HTTP_FORBIDDEN);
        }

        try {
            $catalog = $this->companyCvSkillsCustomizationService->saveCategoryForCompany(
                $company,
                $this->decodeRequestPayload($request),
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonError($exception->getMessage());
        }

        return $this->jsonSuccess($catalog, $request);
    }

    /**
     * @brief Delete a category from a company skills override.
     *
     * @param Request $request HTTP request.
     * @param int $id Tracked company id.
     * @return JsonResponse Success or validation error payload.
     * @date 2026-06-01
     * @author Stephane H.
     */
    #[Route('/admin/employment/companies/{id}/cv-customization/skills-catalog/category/delete', name: 'admin_employment_companies_cv_skills_catalog_category_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteCategory(Request $request, int $id): JsonResponse
    {
        $company = $this->resolveCompany($id);
        if (!$this->isCsrfTokenValid(CompanyCvSkillsCustomizationService::CSRF_SKILLS, (string) $request->request->get('_csrf_token', ''))) {
            return $this->jsonError('employment.companies.flash.csrf_invalid', Response::HTTP_FORBIDDEN);
        }

        $payload = $this->decodeRequestPayload($request);
        $level = (int) ($payload['level'] ?? 0);
        $nodeId = (string) ($payload['id'] ?? '');
        $categoryId = (string) ($payload['categoryId'] ?? '');
        $subcategoryId = isset($payload['subcategoryId']) ? (string) $payload['subcategoryId'] : null;

        if ($nodeId === '' || $level < 1 || $level > 3) {
            return $this->jsonError('dashboard.customization_cv.skills.flash_invalid');
        }

        try {
            $catalog = $this->companyCvSkillsCustomizationService->deleteCategoryForCompany(
                $company,
                $level,
                $nodeId,
                $categoryId,
                $subcategoryId,
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonError($exception->getMessage());
        }

        return $this->jsonSuccess($catalog, $request);
    }

    /**
     * @brief Move a category node within a company skills override catalog.
     *
     * @param Request $request HTTP request.
     * @param int $id Tracked company id.
     * @return JsonResponse Success or validation error payload.
     * @date 2026-06-11
     * @author Stephane H.
     */
    #[Route('/admin/employment/companies/{id}/cv-customization/skills-catalog/category/move', name: 'admin_employment_companies_cv_skills_catalog_category_move', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function moveCategory(Request $request, int $id): JsonResponse
    {
        $company = $this->resolveCompany($id);
        if (!$this->isCsrfTokenValid(CompanyCvSkillsCustomizationService::CSRF_SKILLS, (string) $request->request->get('_csrf_token', ''))) {
            return $this->jsonError('employment.companies.flash.csrf_invalid', Response::HTTP_FORBIDDEN);
        }

        $payload = $this->decodeRequestPayload($request);
        $level = (int) ($payload['level'] ?? 0);
        $nodeId = (string) ($payload['id'] ?? '');
        $categoryId = (string) ($payload['categoryId'] ?? '');
        $subcategoryId = isset($payload['subcategoryId']) ? (string) $payload['subcategoryId'] : null;
        $direction = (string) ($payload['direction'] ?? '');

        if ($nodeId === '' || $level < 1 || $level > 3 || $direction === '') {
            return $this->jsonError('dashboard.customization_cv.skills.flash_invalid');
        }

        try {
            $catalog = $this->companyCvSkillsCustomizationService->moveCategoryForCompany(
                $company,
                $level,
                $nodeId,
                $categoryId,
                $subcategoryId,
                $direction,
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonError($exception->getMessage());
        }

        return $this->jsonSuccess($catalog, $request);
    }

    /**
     * @brief Create or update a skill for a company skills override.
     *
     * @param Request $request HTTP request.
     * @param int $id Tracked company id.
     * @return JsonResponse Success or validation error payload.
     * @date 2026-06-01
     * @author Stephane H.
     */
    #[Route('/admin/employment/companies/{id}/cv-customization/skills-catalog/skill', name: 'admin_employment_companies_cv_skills_catalog_skill_save', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function saveSkill(Request $request, int $id): JsonResponse
    {
        $company = $this->resolveCompany($id);
        if (!$this->isCsrfTokenValid(CompanyCvSkillsCustomizationService::CSRF_SKILLS, (string) $request->request->get('_csrf_token', ''))) {
            return $this->jsonError('employment.companies.flash.csrf_invalid', Response::HTTP_FORBIDDEN);
        }

        $iconUpload = $request->files->get('iconFile');
        $uploadedFile = $iconUpload instanceof UploadedFile ? $iconUpload : null;

        try {
            $catalog = $this->companyCvSkillsCustomizationService->saveSkillForCompany(
                $company,
                $this->decodeRequestPayload($request),
                $uploadedFile,
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonError($exception->getMessage());
        } catch (\RuntimeException $exception) {
            return $this->jsonError($exception->getMessage());
        }

        return $this->jsonSuccess($catalog, $request);
    }

    /**
     * @brief Delete a skill from a company skills override.
     *
     * @param Request $request HTTP request.
     * @param int $id Tracked company id.
     * @return JsonResponse Success or validation error payload.
     * @date 2026-06-01
     * @author Stephane H.
     */
    #[Route('/admin/employment/companies/{id}/cv-customization/skills-catalog/skill/delete', name: 'admin_employment_companies_cv_skills_catalog_skill_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteSkill(Request $request, int $id): JsonResponse
    {
        $company = $this->resolveCompany($id);
        if (!$this->isCsrfTokenValid(CompanyCvSkillsCustomizationService::CSRF_SKILLS, (string) $request->request->get('_csrf_token', ''))) {
            return $this->jsonError('employment.companies.flash.csrf_invalid', Response::HTTP_FORBIDDEN);
        }

        $payload = $this->decodeRequestPayload($request);
        $skillId = (string) ($payload['id'] ?? '');
        if ($skillId === '') {
            return $this->jsonError('dashboard.customization_cv.skills.flash_invalid');
        }

        try {
            $catalog = $this->companyCvSkillsCustomizationService->deleteSkillForCompany($company, $skillId);
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonError($exception->getMessage());
        }

        return $this->jsonSuccess($catalog, $request);
    }

    /**
     * @brief Resolve active locales and default locale from site configuration.
     *
     * @return array{0: list<string>, 1: string}
     * @date 2026-06-11
     * @author Stephane H.
     */
    private function resolveLocales(): array
    {
        $localeConfig = $this->localeConfigurationService->getConfiguration();

        return [$localeConfig['activeLocales'], $localeConfig['defaultLocale']];
    }

    /**
     * @brief Resolve tracked company or throw 404.
     *
     * @param int $id Company id.
     * @return TrackedCompany
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function resolveCompany(int $id): TrackedCompany
    {
        $company = $this->trackedCompanyRepository->find($id);
        if (!$company instanceof TrackedCompany) {
            throw $this->createNotFoundException();
        }

        return $company;
    }

    /**
     * @brief Decode JSON or form request payload for skills catalog API calls.
     *
     * @param Request $request HTTP request.
     * @return array<string, mixed>
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function decodeRequestPayload(Request $request): array
    {
        $contentType = (string) $request->headers->get('Content-Type', '');
        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode((string) $request->getContent(), true);

            return is_array($decoded) ? $decoded : [];
        }

        $labelsRaw = $request->request->all('labelsByLocale');
        $payload = $request->request->all();
        if (is_array($labelsRaw)) {
            $payload['labelsByLocale'] = $labelsRaw;
        }

        return $payload;
    }

    /**
     * @brief Build JSON success response with updated catalog and admin tree HTML.
     *
     * @param array{categories: list<array<string, mixed>>} $catalog Updated catalog.
     * @param Request $request HTTP request (admin locale).
     * @return JsonResponse
     * @date 2026-06-11
     * @author Stephane H.
     */
    private function jsonSuccess(array $catalog, Request $request): JsonResponse
    {
        [$activeLocales, $defaultLocale] = $this->resolveLocales();

        return new JsonResponse([
            'status' => 'ok',
            'catalog' => $catalog,
            'treeHtml' => $this->cvSkillsAdminTreeRenderer->render(
                $catalog,
                $activeLocales,
                $defaultLocale,
                $request->getLocale(),
            ),
        ]);
    }

    /**
     * @brief Build JSON error response with translation key.
     *
     * @param string $messageKey Translation key for the error message.
     * @param int $status HTTP status code.
     * @return JsonResponse
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function jsonError(string $messageKey, int $status = Response::HTTP_BAD_REQUEST): JsonResponse
    {
        return new JsonResponse([
            'status' => 'error',
            'messageKey' => $messageKey,
        ], $status);
    }
}
