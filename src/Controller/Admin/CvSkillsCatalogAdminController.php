<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\TrackedCompany;
use App\Repository\TrackedCompanyRepository;
use App\Service\Cv\CvSkillsAdminTreeRenderer;
use App\Service\Cv\CvSkillsCatalogAdminService;
use App\Service\Cv\GlobalSkillsCatalogPersistence;
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
 * @brief JSON admin API for global CV skills catalog CRUD (categories and skills).
 *
 * @date 2026-06-01
 * @author Stephane H.
 */
#[IsGranted('ROLE_ADMIN')]
final class CvSkillsCatalogAdminController extends AbstractController
{
    /**
     * @brief Wire global skills catalog admin API.
     *
     * @param CvSkillsCatalogAdminService $cvSkillsCatalogAdminService Skills catalog CRUD service.
     * @param GlobalSkillsCatalogPersistence $globalSkillsCatalogPersistence Global persistence backend.
     * @param LocaleConfigurationService $localeConfigurationService Locale configuration.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function __construct(
        private readonly CvSkillsCatalogAdminService $cvSkillsCatalogAdminService,
        private readonly GlobalSkillsCatalogPersistence $globalSkillsCatalogPersistence,
        private readonly LocaleConfigurationService $localeConfigurationService,
        private readonly CvSkillsAdminTreeRenderer $cvSkillsAdminTreeRenderer,
    ) {
    }

    /**
     * @brief Create or update a category (levels 1–3).
     *
     * @param Request $request HTTP request (JSON or form fields).
     * @return JsonResponse Success or validation error payload.
     * @date 2026-06-01
     * @author Stephane H.
     */
    #[Route('/admin/cv/skills-catalog/category', name: 'admin_cv_skills_catalog_category_save', methods: ['POST'])]
    public function saveCategory(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('admin_cv_skills_catalog', (string) $request->request->get('_csrf_token', ''))) {
            return $this->jsonError('dashboard.customization_cv.flash.invalid_csrf', Response::HTTP_FORBIDDEN);
        }

        [$activeLocales, $defaultLocale] = $this->resolveLocales();
        $payload = $this->decodeRequestPayload($request);

        try {
            $catalog = $this->cvSkillsCatalogAdminService->saveCategory(
                $payload,
                $activeLocales,
                $defaultLocale,
                $this->globalSkillsCatalogPersistence,
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonError($exception->getMessage());
        }

        return $this->jsonSuccess($catalog, $request);
    }

    /**
     * @brief Delete a category when it has no blocking children.
     *
     * @param Request $request HTTP request.
     * @return JsonResponse Success or validation error payload.
     * @date 2026-06-01
     * @author Stephane H.
     */
    #[Route('/admin/cv/skills-catalog/category/delete', name: 'admin_cv_skills_catalog_category_delete', methods: ['POST'])]
    public function deleteCategory(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('admin_cv_skills_catalog', (string) $request->request->get('_csrf_token', ''))) {
            return $this->jsonError('dashboard.customization_cv.flash.invalid_csrf', Response::HTTP_FORBIDDEN);
        }

        [$activeLocales, $defaultLocale] = $this->resolveLocales();
        $payload = $this->decodeRequestPayload($request);
        $level = (int) ($payload['level'] ?? 0);
        $nodeId = (string) ($payload['id'] ?? '');
        $categoryId = (string) ($payload['categoryId'] ?? '');
        $subcategoryId = isset($payload['subcategoryId']) ? (string) $payload['subcategoryId'] : null;

        if ($nodeId === '' || $level < 1 || $level > 3) {
            return $this->jsonError('dashboard.customization_cv.skills.flash_invalid');
        }

        try {
            $catalog = $this->cvSkillsCatalogAdminService->deleteCategory(
                $level,
                $nodeId,
                $categoryId,
                $subcategoryId,
                $activeLocales,
                $defaultLocale,
                $this->globalSkillsCatalogPersistence,
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonError($exception->getMessage());
        }

        return $this->jsonSuccess($catalog, $request);
    }

    /**
     * @brief Move a category node up or down among its siblings.
     *
     * @param Request $request HTTP request.
     * @return JsonResponse Success or validation error payload.
     * @date 2026-06-11
     * @author Stephane H.
     */
    #[Route('/admin/cv/skills-catalog/category/move', name: 'admin_cv_skills_catalog_category_move', methods: ['POST'])]
    public function moveCategory(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('admin_cv_skills_catalog', (string) $request->request->get('_csrf_token', ''))) {
            return $this->jsonError('dashboard.customization_cv.flash.invalid_csrf', Response::HTTP_FORBIDDEN);
        }

        [$activeLocales, $defaultLocale] = $this->resolveLocales();
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
            $catalog = $this->cvSkillsCatalogAdminService->moveCategory(
                $level,
                $nodeId,
                $categoryId,
                $subcategoryId,
                $direction,
                $activeLocales,
                $defaultLocale,
                $this->globalSkillsCatalogPersistence,
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonError($exception->getMessage());
        }

        return $this->jsonSuccess($catalog, $request);
    }

    /**
     * @brief Create or update a skill with optional icon upload.
     *
     * @param Request $request HTTP request (multipart when uploading icon).
     * @return JsonResponse Success or validation error payload.
     * @date 2026-06-01
     * @author Stephane H.
     */
    #[Route('/admin/cv/skills-catalog/skill', name: 'admin_cv_skills_catalog_skill_save', methods: ['POST'])]
    public function saveSkill(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('admin_cv_skills_catalog', (string) $request->request->get('_csrf_token', ''))) {
            return $this->jsonError('dashboard.customization_cv.flash.invalid_csrf', Response::HTTP_FORBIDDEN);
        }

        [$activeLocales, $defaultLocale] = $this->resolveLocales();
        $payload = $this->decodeRequestPayload($request);
        $iconUpload = $request->files->get('iconFile');
        $uploadedFile = $iconUpload instanceof UploadedFile ? $iconUpload : null;

        try {
            $catalog = $this->cvSkillsCatalogAdminService->saveSkill(
                $payload,
                $uploadedFile,
                $activeLocales,
                $defaultLocale,
                $this->globalSkillsCatalogPersistence,
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonError($exception->getMessage());
        } catch (\RuntimeException $exception) {
            return $this->jsonError($exception->getMessage());
        }

        return $this->jsonSuccess($catalog, $request);
    }

    /**
     * @brief Delete a skill and its stored icon when applicable.
     *
     * @param Request $request HTTP request.
     * @return JsonResponse Success or validation error payload.
     * @date 2026-06-01
     * @author Stephane H.
     */
    #[Route('/admin/cv/skills-catalog/skill/delete', name: 'admin_cv_skills_catalog_skill_delete', methods: ['POST'])]
    public function deleteSkill(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('admin_cv_skills_catalog', (string) $request->request->get('_csrf_token', ''))) {
            return $this->jsonError('dashboard.customization_cv.flash.invalid_csrf', Response::HTTP_FORBIDDEN);
        }

        [$activeLocales, $defaultLocale] = $this->resolveLocales();
        $payload = $this->decodeRequestPayload($request);
        $skillId = (string) ($payload['id'] ?? '');
        if ($skillId === '') {
            return $this->jsonError('dashboard.customization_cv.skills.flash_invalid');
        }

        try {
            $catalog = $this->cvSkillsCatalogAdminService->deleteSkill(
                $skillId,
                $activeLocales,
                $defaultLocale,
                $this->globalSkillsCatalogPersistence,
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonError($exception->getMessage());
        }

        return $this->jsonSuccess($catalog, $request);
    }

    /**
     * @brief Resolve active locales and default locale from site configuration.
     *
     * @return array{0: list<string>, 1: string}
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function resolveLocales(): array
    {
        $localeConfig = $this->localeConfigurationService->getConfiguration();

        return [$localeConfig['activeLocales'], $localeConfig['defaultLocale']];
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
