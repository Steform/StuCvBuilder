<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\Http\FlashMessageHelper;

use App\Cv\CompanyCvCustomizationSectionKey;
use App\Employment\CompanyArchivedFilter;
use App\Entity\TrackedCompany;
use App\Repository\CompanyCvVisitRepository;
use App\Repository\EmploymentDocumentVariantRepository;
use App\Repository\TrackedCompanyRepository;
use App\Service\Employment\EmploymentCountryList;
use App\Service\Employment\EmploymentCountryPresentationLocaleResolver;
use App\Service\Locale\LocaleConfigurationService;
use App\Service\Employment\CompanyCvAboutCustomizationService;
use App\Service\Employment\CompanyCvCustomizationShellService;
use App\Service\Employment\CompanyCvCertificationCustomizationService;
use App\Service\Employment\CompanyCvEducationCustomizationService;
use App\Service\Employment\CompanyCvInterestsCustomizationService;
use App\Service\Employment\CompanyCvLanguagesCustomizationService;
use App\Service\Employment\CompanyCvReferencesCustomizationService;
use App\Service\Employment\CompanyCvWebProfilesCustomizationService;
use App\Service\Cv\ExperienceContract;
use App\Service\Employment\CompanyCvExperienceCustomizationService;
use App\Service\Employment\CompanyCvFlagshipProjectsCustomizationService;
use App\Service\Employment\CompanyCvSkillsCustomizationService;
use App\Service\Employment\CompanyCvSituationCustomizationService;
use App\Service\Employment\TrackedCompanyContactInput;
use App\Service\Employment\TrackedCompanyDocumentInput;
use App\Service\Employment\TrackedCompanyManagementService;
use App\Employment\EmploymentDocumentKind;
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
 * Admin CRUD for tracked employment companies.
 */
#[IsGranted('ROLE_CV_EDIT')]
class EmploymentCompanyAdminController
{
    private const CSRF_ARCHIVE = 'employment_company_archive';

    private const CSRF_UNARCHIVE = 'employment_company_unarchive';

    private const CSRF_DELETE = 'employment_company_delete';

    private const CSRF_EDIT = 'employment_company_edit';

    private const CSRF_CREATE = 'employment_company_create';

    /**
     * @brief Build employment company admin controller.
     *
     * @param TrackedCompanyRepository $trackedCompanyRepository Company repository.
     * @param TrackedCompanyManagementService $managementService Management service.
     * @param CompanyCvCustomizationShellService $companyCvCustomizationShellService Per-company CV customization shell.
     * @param CompanyCvAboutCustomizationService $companyCvAboutCustomizationService Per-company About customization.
     * @param CompanyCvSituationCustomizationService $companyCvSituationCustomizationService Per-company Situation customization.
     * @param CompanyCvExperienceCustomizationService $companyCvExperienceCustomizationService Per-company Experience customization.
     * @param CompanyCvEducationCustomizationService $companyCvEducationCustomizationService Per-company Education customization.
     * @param CompanyCvCertificationCustomizationService $companyCvCertificationCustomizationService Per-company Certification customization.
     * @param CompanyCvSkillsCustomizationService $companyCvSkillsCustomizationService Per-company Skills customization.
     * @param CompanyCvFlagshipProjectsCustomizationService $companyCvFlagshipProjectsCustomizationService Per-company Flagship projects customization.
     * @param CompanyCvLanguagesCustomizationService $companyCvLanguagesCustomizationService Per-company Languages customization.
     * @param CompanyCvInterestsCustomizationService $companyCvInterestsCustomizationService Per-company Interests customization.
     * @param CompanyCvWebProfilesCustomizationService $companyCvWebProfilesCustomizationService Per-company Web profiles customization.
     * @param CompanyCvReferencesCustomizationService $companyCvReferencesCustomizationService Per-company References customization.
     * @param EmploymentCountryList $employmentCountryList Country list.
     * @param EmploymentCountryPresentationLocaleResolver $presentationLocaleResolver Presentation locale helper.
     * @param LocaleConfigurationService $localeConfigurationService Site locale configuration.
     * @param CsrfTokenManagerInterface $csrfTokenManager CSRF manager.
     * @param EmploymentDocumentVariantRepository $documentVariantRepository Document variant repository.
     * @param CompanyCvVisitRepository $companyCvVisitRepository Official visit repository.
     * @param UrlGeneratorInterface $urlGenerator URL generator.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function __construct(
        private readonly TrackedCompanyRepository $trackedCompanyRepository,
        private readonly EmploymentDocumentVariantRepository $documentVariantRepository,
        private readonly CompanyCvVisitRepository $companyCvVisitRepository,
        private readonly TrackedCompanyManagementService $managementService,
        private readonly CompanyCvCustomizationShellService $companyCvCustomizationShellService,
        private readonly CompanyCvAboutCustomizationService $companyCvAboutCustomizationService,
        private readonly CompanyCvSituationCustomizationService $companyCvSituationCustomizationService,
        private readonly CompanyCvExperienceCustomizationService $companyCvExperienceCustomizationService,
        private readonly CompanyCvEducationCustomizationService $companyCvEducationCustomizationService,
        private readonly CompanyCvCertificationCustomizationService $companyCvCertificationCustomizationService,
        private readonly CompanyCvSkillsCustomizationService $companyCvSkillsCustomizationService,
        private readonly CompanyCvFlagshipProjectsCustomizationService $companyCvFlagshipProjectsCustomizationService,
        private readonly CompanyCvLanguagesCustomizationService $companyCvLanguagesCustomizationService,
        private readonly CompanyCvInterestsCustomizationService $companyCvInterestsCustomizationService,
        private readonly CompanyCvWebProfilesCustomizationService $companyCvWebProfilesCustomizationService,
        private readonly CompanyCvReferencesCustomizationService $companyCvReferencesCustomizationService,
        private readonly EmploymentCountryList $employmentCountryList,
        private readonly EmploymentCountryPresentationLocaleResolver $presentationLocaleResolver,
        private readonly LocaleConfigurationService $localeConfigurationService,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @brief List tracked companies with column sort via sort and dir query params.
     *
     * @param Environment $twig Twig environment.
     * @param Request $request HTTP request.
     * @return Response Rendered list with sortable column headers.
     * @date 2026-06-17
     * @author Stephane H.
     */
    #[Route('/admin/employment/companies', name: 'admin_employment_companies_index', methods: ['GET'])]
    public function index(Environment $twig, Request $request): Response
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $search = $this->managementService->normalizeSearchQuery((string) $request->query->get('q', ''));
        $country = strtoupper(trim((string) $request->query->get('country', '')));
        $archivedFilter = CompanyArchivedFilter::normalize(
            is_string($request->query->get('archived_filter')) ? (string) $request->query->get('archived_filter') : null,
            (string) $request->query->get('archived', '') === '1',
        );
        $allowedSorts = ['name', 'code', 'country', 'last_visit', 'created'];
        $sortParam = $request->query->get('sort');
        $sort = is_string($sortParam) && $sortParam !== '' && in_array($sortParam, $allowedSorts, true)
            ? $sortParam
            : 'created';
        $dirParam = $request->query->get('dir');
        $sortDir = is_string($dirParam) && in_array(strtolower($dirParam), ['asc', 'desc'], true)
            ? strtolower($dirParam)
            : 'desc';

        $result = $this->trackedCompanyRepository->findForAdminList(
            $search,
            $country,
            $archivedFilter,
            $sort,
            $sortDir,
            $page,
            20,
        );

        $ids = array_map(static fn (TrackedCompany $c): int => (int) $c->getId(), $result['items']);
        $lastVisits = $this->trackedCompanyRepository->findLastVisitAtByCompanyIds($ids);
        $consultationLevels = $this->trackedCompanyRepository->findConsultationLevelByCompanyIds($ids);

        return new Response($twig->render('admin/employment/companies/index.html.twig', [
            'companies' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'search' => (string) $request->query->get('q', ''),
            'countryFilter' => $country,
            'archivedFilter' => $archivedFilter,
            'sort' => $sort,
            'sortDir' => $sortDir,
            'listingQuery' => array_filter([
                'q' => $search !== '' ? $search : null,
                'country' => $country !== '' ? $country : null,
                'archived_filter' => $archivedFilter !== CompanyArchivedFilter::ACTIVE ? $archivedFilter : null,
                'sort' => $sort,
                'dir' => $sortDir,
                'page' => $page > 1 ? $page : null,
            ], static fn ($value): bool => $value !== null && $value !== ''),
            'lastVisits' => $lastVisits,
            'consultationLevels' => $consultationLevels,
            ...$this->buildCountryModalViewData(),
            ...$this->buildDocumentVariantViewData(),
            'csrfArchiveToken' => $this->csrfTokenManager->getToken(self::CSRF_ARCHIVE)->getValue(),
            'csrfUnarchiveToken' => $this->csrfTokenManager->getToken(self::CSRF_UNARCHIVE)->getValue(),
            'csrfDeleteToken' => $this->csrfTokenManager->getToken(self::CSRF_DELETE)->getValue(),
            'csrfEditToken' => $this->csrfTokenManager->getToken(self::CSRF_EDIT)->getValue(),
            'csrfCreateToken' => $this->csrfTokenManager->getToken(self::CSRF_CREATE)->getValue(),
        ]));
    }

    /**
     * @brief Create company from modal form POST.
     *
     * @param Request $request HTTP request.
     * @return RedirectResponse
     * @date 2026-06-01
     * @author Stephane H.
     */
    #[Route('/admin/employment/companies/create', name: 'admin_employment_companies_create', methods: ['POST'])]
    public function create(Request $request): RedirectResponse
    {
        $token = (string) $request->request->get('_token', '');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_CREATE, $token))) {
            FlashMessageHelper::add($request, 'error', 'employment.companies.flash.csrf_invalid');

            return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_index'));
        }

        $result = $this->managementService->create(
            (string) $request->request->get('name', ''),
            (string) $request->request->get('country_code', '') ?: null,
            $this->contactInputFromRequest($request),
            $this->documentInputFromRequest($request),
        );
        if ($result['error'] !== null) {
            FlashMessageHelper::add($request, 'error', $result['error']);
        } else {
            FlashMessageHelper::add($request, 'success', 'employment.companies.flash.created');
        }

        return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_index'));
    }

    /**
     * @brief Update company from modal form POST.
     *
     * @param Request $request HTTP request.
     * @param int $id Company id.
     * @return RedirectResponse
     * @date 2026-06-01
     * @author Stephane H.
     */
    #[Route('/admin/employment/companies/{id}/edit', name: 'admin_employment_companies_edit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function edit(Request $request, int $id): RedirectResponse
    {
        $token = (string) $request->request->get('_token', '');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_EDIT, $token))) {
            FlashMessageHelper::add($request, 'error', 'employment.companies.flash.csrf_invalid');

            return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_index'));
        }

        $company = $this->trackedCompanyRepository->find($id);
        if (!$company instanceof TrackedCompany) {
            throw $this->createNotFoundException();
        }

        $error = $this->managementService->update(
            $company,
            (string) $request->request->get('name', ''),
            (string) $request->request->get('country_code', '') ?: null,
            $this->contactInputFromRequest($request),
            $this->documentInputFromRequest($request),
        );
        if ($error !== null) {
            FlashMessageHelper::add($request, 'error', $error);
        } else {
            FlashMessageHelper::add($request, 'success', 'employment.companies.flash.updated');
        }

        return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_index'));
    }

    /**
     * @brief Archive company.
     *
     * @param Request $request HTTP request.
     * @param int $id Company id.
     * @return RedirectResponse
     * @date 2026-06-01
     * @author Stephane H.
     */
    #[Route('/admin/employment/companies/{id}/archive', name: 'admin_employment_companies_archive', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function archive(Request $request, int $id): RedirectResponse
    {
        $token = (string) $request->request->get('_token', '');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_ARCHIVE, $token))) {
            FlashMessageHelper::add($request, 'error', 'employment.companies.flash.csrf_invalid');

            return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_index'));
        }

        $company = $this->trackedCompanyRepository->find($id);
        if ($company instanceof TrackedCompany) {
            $this->managementService->archive($company);
            FlashMessageHelper::add($request, 'success', 'employment.companies.flash.archived');
        }

        return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_index'));
    }

    /**
     * @brief Unarchive company.
     *
     * @param Request $request HTTP request.
     * @param int $id Company id.
     * @return RedirectResponse
     * @date 2026-06-17
     * @author Stephane H.
     */
    #[Route('/admin/employment/companies/{id}/unarchive', name: 'admin_employment_companies_unarchive', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function unarchive(Request $request, int $id): RedirectResponse
    {
        $token = (string) $request->request->get('_token', '');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_UNARCHIVE, $token))) {
            FlashMessageHelper::add($request, 'error', 'employment.companies.flash.csrf_invalid');

            return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_index'));
        }

        $company = $this->trackedCompanyRepository->find($id);
        if ($company instanceof TrackedCompany) {
            $this->managementService->unarchive($company);
            FlashMessageHelper::add($request, 'success', 'employment.companies.flash.unarchived');
        }

        return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_index'));
    }

    /**
     * @brief Delete company permanently.
     *
     * @param Request $request HTTP request.
     * @param int $id Company id.
     * @return RedirectResponse
     * @date 2026-06-17
     * @author Stephane H.
     */
    #[Route('/admin/employment/companies/{id}/delete', name: 'admin_employment_companies_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, int $id): RedirectResponse
    {
        $token = (string) $request->request->get('_token', '');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_DELETE, $token))) {
            FlashMessageHelper::add($request, 'error', 'employment.companies.flash.csrf_invalid');

            return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_index'));
        }

        $company = $this->trackedCompanyRepository->find($id);
        if ($company instanceof TrackedCompany) {
            $this->managementService->delete($company);
            FlashMessageHelper::add($request, 'success', 'employment.companies.flash.deleted');
        }

        return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_index'));
    }

    /**
     * @brief Redirect legacy company detail URLs to the list (modal-only workflow).
     *
     * @param int $id Company id (ignored).
     * @return RedirectResponse
     * @date 2026-06-01
     * @author Stephane H.
     */
    #[Route('/admin/employment/companies/{id}', name: 'admin_employment_companies_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function showLegacy(int $id): RedirectResponse
    {
        unset($id);

        return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_index'));
    }

    /**
     * @brief List official recruiter visits for one tracked company.
     *
     * @param Environment $twig Twig environment.
     * @param int $id Company id.
     * @return Response
     * @date 2026-06-16
     * @author Stephane H.
     */
    #[Route('/admin/employment/companies/{id}/visits', name: 'admin_employment_companies_visits', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function visits(Environment $twig, int $id): Response
    {
        $company = $this->trackedCompanyRepository->find($id);
        if (!$company instanceof TrackedCompany) {
            throw $this->createNotFoundException();
        }

        return new Response($twig->render('admin/employment/companies/visits.html.twig', [
            'company' => $company,
            'visits' => $this->companyCvVisitRepository->findForCompanyShow($company),
            'countryLabelsByCode' => $this->employmentCountryList->getLabelsByCode(),
        ]));
    }

    /**
     * @brief Per-company CV web customization shell (section nav, inheritance badges, placeholders).
     *
     * @param Environment $twig Twig environment.
     * @param Request $request HTTP request.
     * @param int $id Company id.
     * @return Response
     * @date 2026-06-01
     * @author Stephane H.
     */
    #[Route('/admin/employment/companies/{id}/cv-customization', name: 'admin_employment_companies_cv_customization', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function cvCustomization(Environment $twig, Request $request, int $id): Response
    {
        $company = $this->trackedCompanyRepository->find($id);
        if (!$company instanceof TrackedCompany) {
            throw $this->createNotFoundException();
        }

        if ($request->isMethod('POST')) {
            $redirect = $this->handleCvCustomizationPost($request, $company);
            if ($redirect !== null) {
                return $redirect;
            }
        }

        $sectionParam = $request->query->get('section');
        $requestedSection = is_string($sectionParam) ? $sectionParam : null;
        $shell = $this->companyCvCustomizationShellService->buildShellViewData($company, $requestedSection);

        $recruiterPreviewUrl = $this->urlGenerator->generate(
            'cv_show',
            ['format' => $company->getCode()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $viewData = [
            'company' => $company,
            'recruiterPreviewUrl' => $recruiterPreviewUrl,
            'cvCustomizationSections' => $shell['sections'],
            'cvCustomizationActiveSection' => $shell['activeSection'],
            'cvCustomizationCustomizedCount' => $shell['customizedCount'],
            'cvCustomizationTotalSections' => $shell['totalSections'],
            'loadAboutEditorAssets' => false,
            'loadExperienceEditorAssets' => false,
            'loadSkillsEditorAssets' => false,
            'loadFlagshipEditorAssets' => false,
            'loadEducationEditorAssets' => false,
            'loadCertificationEditorAssets' => false,
        ];

        if ($shell['activeSection'] === CompanyCvCustomizationSectionKey::ABOUT) {
            $viewData = array_merge($viewData, $this->companyCvAboutCustomizationService->buildAboutAdminViewData($company, $request));
            $viewData['cvAboutFormAction'] = $this->urlGenerator->generate('admin_employment_companies_cv_customization', ['id' => $id]);
            $viewData['loadAboutEditorAssets'] = (bool) ($viewData['cvAboutCustomizationEnabled'] ?? false);
        }

        if ($shell['activeSection'] === CompanyCvCustomizationSectionKey::SITUATION) {
            $viewData = array_merge($viewData, $this->companyCvSituationCustomizationService->buildSituationAdminViewData($company, $request));
            $viewData['cvSituationFormAction'] = $this->urlGenerator->generate('admin_employment_companies_cv_customization', ['id' => $id]);
        }

        if ($shell['activeSection'] === CompanyCvCustomizationSectionKey::EXPERIENCE) {
            $viewData = array_merge($viewData, $this->companyCvExperienceCustomizationService->buildExperienceAdminViewData($company, $request));
            $viewData['cvExperienceFormAction'] = $this->urlGenerator->generate('admin_employment_companies_cv_customization', ['id' => $id]);
            $viewData['loadExperienceEditorAssets'] = (bool) ($viewData['cvExperienceCustomizationEnabled'] ?? false);
        }

        if ($shell['activeSection'] === CompanyCvCustomizationSectionKey::SKILLS) {
            $viewData = array_merge($viewData, $this->companyCvSkillsCustomizationService->buildSkillsAdminViewData($company, $request));
            $viewData['loadSkillsEditorAssets'] = (bool) ($viewData['cvSkillsCustomizationEnabled'] ?? false);
        }

        if ($shell['activeSection'] === CompanyCvCustomizationSectionKey::FLAGSHIP_PROJECTS) {
            $viewData = array_merge($viewData, $this->companyCvFlagshipProjectsCustomizationService->buildFlagshipProjectsAdminViewData($company, $request));
            $viewData['cvFlagshipProjectsFormAction'] = $this->urlGenerator->generate('admin_employment_companies_cv_customization', ['id' => $id]);
            $viewData['loadFlagshipEditorAssets'] = (bool) ($viewData['cvFlagshipProjectsCustomizationEnabled'] ?? false);
        }

        if ($shell['activeSection'] === CompanyCvCustomizationSectionKey::EDUCATION) {
            $viewData = array_merge($viewData, $this->companyCvEducationCustomizationService->buildEducationAdminViewData($company, $request));
            $viewData['cvEducationFormAction'] = $this->urlGenerator->generate('admin_employment_companies_cv_customization', ['id' => $id]);
            $viewData['loadEducationEditorAssets'] = (bool) ($viewData['cvEducationCustomizationEnabled'] ?? false);
        }

        if ($shell['activeSection'] === CompanyCvCustomizationSectionKey::CERTIFICATION) {
            $viewData = array_merge($viewData, $this->companyCvCertificationCustomizationService->buildCertificationAdminViewData($company, $request));
            $viewData['cvCertificationFormAction'] = $this->urlGenerator->generate('admin_employment_companies_cv_customization', ['id' => $id]);
            $viewData['loadCertificationEditorAssets'] = (bool) ($viewData['cvCertificationCustomizationEnabled'] ?? false);
        }

        if ($shell['activeSection'] === CompanyCvCustomizationSectionKey::LANGUAGES) {
            $viewData = array_merge($viewData, $this->companyCvLanguagesCustomizationService->buildLanguagesAdminViewData($company, $request));
            $viewData['cvLanguagesFormAction'] = $this->urlGenerator->generate('admin_employment_companies_cv_customization', ['id' => $id]);
            $viewData['loadLanguagesEditorAssets'] = (bool) ($viewData['cvLanguagesCustomizationEnabled'] ?? false);
        }

        if ($shell['activeSection'] === CompanyCvCustomizationSectionKey::INTERESTS) {
            $viewData = array_merge($viewData, $this->companyCvInterestsCustomizationService->buildInterestsAdminViewData($company, $request));
            $viewData['cvInterestsFormAction'] = $this->urlGenerator->generate('admin_employment_companies_cv_customization', ['id' => $id]);
            $viewData['loadInterestsEditorAssets'] = (bool) ($viewData['cvInterestsCustomizationEnabled'] ?? false);
        }

        if ($shell['activeSection'] === CompanyCvCustomizationSectionKey::WEB_PROFILES) {
            $viewData = array_merge($viewData, $this->companyCvWebProfilesCustomizationService->buildWebProfilesAdminViewData($company, $request));
            $viewData['cvWebProfilesFormAction'] = $this->urlGenerator->generate('admin_employment_companies_cv_customization', ['id' => $id]);
            $viewData['loadWebProfilesEditorAssets'] = (bool) ($viewData['cvWebProfilesCustomizationEnabled'] ?? false);
        }

        if ($shell['activeSection'] === CompanyCvCustomizationSectionKey::REFERENCES) {
            $viewData = array_merge($viewData, $this->companyCvReferencesCustomizationService->buildReferencesAdminViewData($company, $request));
            $viewData['cvReferencesFormAction'] = $this->urlGenerator->generate('admin_employment_companies_cv_customization', ['id' => $id]);
            $viewData['loadReferencesEditorAssets'] = (bool) ($viewData['cvReferencesCustomizationEnabled'] ?? false);
        }

        return new Response($twig->render('admin/employment/companies/cv_customization.html.twig', $viewData));
    }

    /**
     * @brief Handle POST actions on company CV customization page.
     *
     * @param Request $request HTTP request.
     * @param TrackedCompany $company Tracked company.
     * @return RedirectResponse|null Redirect when handled, null to continue GET render.
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function handleCvCustomizationPost(Request $request, TrackedCompany $company): ?RedirectResponse
    {
        $formScope = (string) $request->request->get('form_scope', '');
        $redirectParams = $this->buildCvCustomizationRedirectParams($request, $company);

        if ($formScope === 'company_cv_about_enable') {
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(CompanyCvAboutCustomizationService::CSRF_ABOUT_ENABLE, (string) $request->request->get('_token', '')))) {
                $this->addFlash($request, 'error', 'employment.companies.flash.csrf_invalid');

                return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
            }

            $this->companyCvAboutCustomizationService->enableAboutCustomization($company);
            $this->addFlash($request, 'success', 'employment.companies.cv_customization.about.flash.enabled');

            return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
        }

        if ($formScope === 'company_cv_about_reset') {
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(CompanyCvAboutCustomizationService::CSRF_ABOUT_RESET, (string) $request->request->get('_token', '')))) {
                $this->addFlash($request, 'error', 'employment.companies.flash.csrf_invalid');

                return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
            }

            $this->companyCvAboutCustomizationService->resetAboutToInherited($company);
            $this->addFlash($request, 'success', 'employment.companies.cv_customization.about.flash.reset');

            return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
        }

        if ($formScope === 'company_cv_about_save') {
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(CompanyCvAboutCustomizationService::CSRF_ABOUT_SAVE, (string) $request->request->get('_csrf_token', '')))) {
                $this->addFlash($request, 'error', 'employment.companies.flash.csrf_invalid');

                return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
            }

            $result = $this->companyCvAboutCustomizationService->saveAboutFromRequest($company, $request);
            foreach ($result['flashError'] as $messageKey) {
                $this->addFlash($request, 'error', $messageKey);
            }
            foreach ($result['flashWarning'] as $messageKey) {
                $this->addFlash($request, 'warning', $messageKey);
            }
            foreach ($result['flashSuccess'] as $messageKey) {
                $this->addFlash($request, 'success', $messageKey);
            }

            return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
        }

        if ($formScope === 'company_cv_situation_enable') {
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(CompanyCvSituationCustomizationService::CSRF_SITUATION_ENABLE, (string) $request->request->get('_token', '')))) {
                $this->addFlash($request, 'error', 'employment.companies.flash.csrf_invalid');

                return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
            }

            $this->companyCvSituationCustomizationService->enableSituationCustomization($company);
            $this->addFlash($request, 'success', 'employment.companies.cv_customization.situation.flash.enabled');

            return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
        }

        if ($formScope === 'company_cv_situation_reset') {
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(CompanyCvSituationCustomizationService::CSRF_SITUATION_RESET, (string) $request->request->get('_token', '')))) {
                $this->addFlash($request, 'error', 'employment.companies.flash.csrf_invalid');

                return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
            }

            $this->companyCvSituationCustomizationService->resetSituationToInherited($company);
            $this->addFlash($request, 'success', 'employment.companies.cv_customization.situation.flash.reset');

            return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
        }

        if ($formScope === 'company_cv_situation_save') {
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(CompanyCvSituationCustomizationService::CSRF_SITUATION_SAVE, (string) $request->request->get('_csrf_token', '')))) {
                $this->addFlash($request, 'error', 'employment.companies.flash.csrf_invalid');

                return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
            }

            $result = $this->companyCvSituationCustomizationService->saveSituationFromRequest($company, $request);
            foreach ($result['flashError'] as $messageKey) {
                $this->addFlash($request, 'error', $messageKey);
            }
            foreach ($result['flashWarning'] as $messageKey) {
                $this->addFlash($request, 'warning', $messageKey);
            }
            foreach ($result['flashSuccess'] as $messageKey) {
                $this->addFlash($request, 'success', $messageKey);
            }

            return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
        }

        if ($formScope === 'company_cv_experience_enable') {
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(CompanyCvExperienceCustomizationService::CSRF_EXPERIENCE_ENABLE, (string) $request->request->get('_token', '')))) {
                $this->addFlash($request, 'error', 'employment.companies.flash.csrf_invalid');

                return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
            }

            $this->companyCvExperienceCustomizationService->enableExperienceCustomization($company);
            $this->addFlash($request, 'success', 'employment.companies.cv_customization.experience.flash.enabled');

            return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
        }

        if ($formScope === 'company_cv_experience_reset') {
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(CompanyCvExperienceCustomizationService::CSRF_EXPERIENCE_RESET, (string) $request->request->get('_token', '')))) {
                $this->addFlash($request, 'error', 'employment.companies.flash.csrf_invalid');

                return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
            }

            $this->companyCvExperienceCustomizationService->resetExperienceToInherited($company);
            $this->addFlash($request, 'success', 'employment.companies.cv_customization.experience.flash.reset');

            return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
        }

        if ($formScope === 'company_cv_experience_save') {
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(CompanyCvExperienceCustomizationService::CSRF_EXPERIENCE_SAVE, (string) $request->request->get('_csrf_token', '')))) {
                $this->addFlash($request, 'error', 'employment.companies.flash.csrf_invalid');

                return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
            }

            $result = $this->companyCvExperienceCustomizationService->saveExperienceFromRequest($company, $request);
            foreach ($result['flashError'] as $messageKey) {
                $this->addFlash($request, 'error', $messageKey);
            }
            foreach ($result['flashWarning'] as $messageKey) {
                $this->addFlash($request, 'warning', $messageKey);
            }
            foreach ($result['flashSuccess'] as $messageKey) {
                $this->addFlash($request, 'success', $messageKey);
            }

            return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
        }

        if ($formScope === 'company_cv_skills_enable') {
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(CompanyCvSkillsCustomizationService::CSRF_SKILLS_ENABLE, (string) $request->request->get('_token', '')))) {
                $this->addFlash($request, 'error', 'employment.companies.flash.csrf_invalid');

                return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
            }

            $this->companyCvSkillsCustomizationService->enableSkillsCustomization($company);
            $this->addFlash($request, 'success', 'employment.companies.cv_customization.skills.flash.enabled');

            return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
        }

        if ($formScope === 'company_cv_skills_reset') {
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(CompanyCvSkillsCustomizationService::CSRF_SKILLS_RESET, (string) $request->request->get('_token', '')))) {
                $this->addFlash($request, 'error', 'employment.companies.flash.csrf_invalid');

                return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
            }

            $this->companyCvSkillsCustomizationService->resetSkillsToInherited($company);
            $this->addFlash($request, 'success', 'employment.companies.cv_customization.skills.flash.reset');

            return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
        }

        if ($formScope === 'company_cv_flagship_projects_enable') {
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(CompanyCvFlagshipProjectsCustomizationService::CSRF_FLAGSHIP_ENABLE, (string) $request->request->get('_token', '')))) {
                $this->addFlash($request, 'error', 'employment.companies.flash.csrf_invalid');

                return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
            }

            $this->companyCvFlagshipProjectsCustomizationService->enableFlagshipProjectsCustomization($company);
            $this->addFlash($request, 'success', 'employment.companies.cv_customization.flagship_projects.flash.enabled');

            return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
        }

        if ($formScope === 'company_cv_flagship_projects_reset') {
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(CompanyCvFlagshipProjectsCustomizationService::CSRF_FLAGSHIP_RESET, (string) $request->request->get('_token', '')))) {
                $this->addFlash($request, 'error', 'employment.companies.flash.csrf_invalid');

                return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
            }

            $this->companyCvFlagshipProjectsCustomizationService->resetFlagshipProjectsToInherited($company);
            $this->addFlash($request, 'success', 'employment.companies.cv_customization.flagship_projects.flash.reset');

            return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
        }

        if ($formScope === 'company_cv_flagship_projects_save') {
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(CompanyCvFlagshipProjectsCustomizationService::CSRF_FLAGSHIP_SAVE, (string) $request->request->get('_csrf_token', '')))) {
                $this->addFlash($request, 'error', 'employment.companies.flash.csrf_invalid');

                return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
            }

            $result = $this->companyCvFlagshipProjectsCustomizationService->saveFlagshipProjectsFromRequest($company, $request);
            foreach ($result['flashError'] as $messageKey) {
                $this->addFlash($request, 'error', $messageKey);
            }
            foreach ($result['flashWarning'] as $messageKey) {
                $this->addFlash($request, 'warning', $messageKey);
            }
            foreach ($result['flashStructuredWarning'] as $error) {
                $this->addStructuredFlash($request, 'warning', $error);
            }
            foreach ($result['flashSuccess'] as $messageKey) {
                $this->addFlash($request, 'success', $messageKey);
            }

            return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
        }

        if ($formScope === 'company_cv_education_enable') {
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(CompanyCvEducationCustomizationService::CSRF_EDUCATION_ENABLE, (string) $request->request->get('_token', '')))) {
                $this->addFlash($request, 'error', 'employment.companies.flash.csrf_invalid');

                return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
            }

            $this->companyCvEducationCustomizationService->enableEducationCustomization($company);
            $this->addFlash($request, 'success', 'employment.companies.cv_customization.education.flash.enabled');

            return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
        }

        if ($formScope === 'company_cv_education_reset') {
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(CompanyCvEducationCustomizationService::CSRF_EDUCATION_RESET, (string) $request->request->get('_token', '')))) {
                $this->addFlash($request, 'error', 'employment.companies.flash.csrf_invalid');

                return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
            }

            $this->companyCvEducationCustomizationService->resetEducationToInherited($company);
            $this->addFlash($request, 'success', 'employment.companies.cv_customization.education.flash.reset');

            return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
        }

        if ($formScope === 'company_cv_education_save') {
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(CompanyCvEducationCustomizationService::CSRF_EDUCATION_SAVE, (string) $request->request->get('_csrf_token', '')))) {
                $this->addFlash($request, 'error', 'employment.companies.flash.csrf_invalid');

                return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
            }

            $result = $this->companyCvEducationCustomizationService->saveEducationFromRequest($company, $request);
            foreach ($result['flashError'] as $messageKey) {
                $this->addFlash($request, 'error', $messageKey);
            }
            foreach ($result['flashWarning'] as $messageKey) {
                $this->addFlash($request, 'warning', $messageKey);
            }
            foreach ($result['flashSuccess'] as $messageKey) {
                $this->addFlash($request, 'success', $messageKey);
            }

            return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
        }

        if ($formScope === 'company_cv_certification_enable') {
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(CompanyCvCertificationCustomizationService::CSRF_CERTIFICATION_ENABLE, (string) $request->request->get('_token', '')))) {
                $this->addFlash($request, 'error', 'employment.companies.flash.csrf_invalid');

                return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
            }

            $this->companyCvCertificationCustomizationService->enableCertificationCustomization($company);
            $this->addFlash($request, 'success', 'employment.companies.cv_customization.certification.flash.enabled');

            return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
        }

        if ($formScope === 'company_cv_certification_reset') {
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(CompanyCvCertificationCustomizationService::CSRF_CERTIFICATION_RESET, (string) $request->request->get('_token', '')))) {
                $this->addFlash($request, 'error', 'employment.companies.flash.csrf_invalid');

                return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
            }

            $this->companyCvCertificationCustomizationService->resetCertificationToInherited($company);
            $this->addFlash($request, 'success', 'employment.companies.cv_customization.certification.flash.reset');

            return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
        }

        if ($formScope === 'company_cv_certification_save') {
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(CompanyCvCertificationCustomizationService::CSRF_CERTIFICATION_SAVE, (string) $request->request->get('_csrf_token', '')))) {
                $this->addFlash($request, 'error', 'employment.companies.flash.csrf_invalid');

                return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
            }

            $result = $this->companyCvCertificationCustomizationService->saveCertificationFromRequest($company, $request);
            foreach ($result['flashError'] as $messageKey) {
                $this->addFlash($request, 'error', $messageKey);
            }
            foreach ($result['flashWarning'] as $messageKey) {
                $this->addFlash($request, 'warning', $messageKey);
            }
            foreach ($result['flashSuccess'] as $messageKey) {
                $this->addFlash($request, 'success', $messageKey);
            }

            return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
        }

        if ($formScope === 'company_cv_languages_enable') {
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(CompanyCvLanguagesCustomizationService::CSRF_LANGUAGES_ENABLE, (string) $request->request->get('_token', '')))) {
                $this->addFlash($request, 'error', 'employment.companies.flash.csrf_invalid');
                return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
            }
            $this->companyCvLanguagesCustomizationService->enableLanguagesCustomization($company);
            $this->addFlash($request, 'success', 'employment.companies.cv_customization.languages.flash.enabled');
            return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
        }

        if ($formScope === 'company_cv_languages_reset') {
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(CompanyCvLanguagesCustomizationService::CSRF_LANGUAGES_RESET, (string) $request->request->get('_token', '')))) {
                $this->addFlash($request, 'error', 'employment.companies.flash.csrf_invalid');
                return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
            }
            $this->companyCvLanguagesCustomizationService->resetLanguagesToInherited($company);
            $this->addFlash($request, 'success', 'employment.companies.cv_customization.languages.flash.reset');
            return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
        }

        if ($formScope === 'company_cv_languages_save') {
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(CompanyCvLanguagesCustomizationService::CSRF_LANGUAGES_SAVE, (string) $request->request->get('_csrf_token', '')))) {
                $this->addFlash($request, 'error', 'employment.companies.flash.csrf_invalid');
                return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
            }
            $result = $this->companyCvLanguagesCustomizationService->saveLanguagesFromRequest($company, $request);
            foreach ($result['flashError'] as $messageKey) { $this->addFlash($request, 'error', $messageKey); }
            foreach ($result['flashWarning'] as $messageKey) { $this->addFlash($request, 'warning', $messageKey); }
            foreach ($result['flashSuccess'] as $messageKey) { $this->addFlash($request, 'success', $messageKey); }
            return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
        }

        if ($formScope === 'company_cv_interests_enable') {
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(CompanyCvInterestsCustomizationService::CSRF_INTERESTS_ENABLE, (string) $request->request->get('_token', '')))) {
                $this->addFlash($request, 'error', 'employment.companies.flash.csrf_invalid');
                return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
            }
            $this->companyCvInterestsCustomizationService->enableInterestsCustomization($company);
            $this->addFlash($request, 'success', 'employment.companies.cv_customization.interests.flash.enabled');
            return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
        }

        if ($formScope === 'company_cv_interests_reset') {
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(CompanyCvInterestsCustomizationService::CSRF_INTERESTS_RESET, (string) $request->request->get('_token', '')))) {
                $this->addFlash($request, 'error', 'employment.companies.flash.csrf_invalid');
                return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
            }
            $this->companyCvInterestsCustomizationService->resetInterestsToInherited($company);
            $this->addFlash($request, 'success', 'employment.companies.cv_customization.interests.flash.reset');
            return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
        }

        if ($formScope === 'company_cv_interests_save') {
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(CompanyCvInterestsCustomizationService::CSRF_INTERESTS_SAVE, (string) $request->request->get('_csrf_token', '')))) {
                $this->addFlash($request, 'error', 'employment.companies.flash.csrf_invalid');
                return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
            }
            $result = $this->companyCvInterestsCustomizationService->saveInterestsFromRequest($company, $request);
            foreach ($result['flashError'] as $messageKey) { $this->addFlash($request, 'error', $messageKey); }
            foreach ($result['flashWarning'] as $messageKey) { $this->addFlash($request, 'warning', $messageKey); }
            foreach ($result['flashSuccess'] as $messageKey) { $this->addFlash($request, 'success', $messageKey); }
            return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
        }

        if ($formScope === 'company_cv_web_profiles_enable') {
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(CompanyCvWebProfilesCustomizationService::CSRF_WEB_PROFILES_ENABLE, (string) $request->request->get('_token', '')))) {
                $this->addFlash($request, 'error', 'employment.companies.flash.csrf_invalid');
                return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
            }
            $this->companyCvWebProfilesCustomizationService->enableWebProfilesCustomization($company);
            $this->addFlash($request, 'success', 'employment.companies.cv_customization.web_profiles.flash.enabled');
            return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
        }

        if ($formScope === 'company_cv_web_profiles_reset') {
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(CompanyCvWebProfilesCustomizationService::CSRF_WEB_PROFILES_RESET, (string) $request->request->get('_token', '')))) {
                $this->addFlash($request, 'error', 'employment.companies.flash.csrf_invalid');
                return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
            }
            $this->companyCvWebProfilesCustomizationService->resetWebProfilesToInherited($company);
            $this->addFlash($request, 'success', 'employment.companies.cv_customization.web_profiles.flash.reset');
            return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
        }

        if ($formScope === 'company_cv_web_profiles_save') {
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(CompanyCvWebProfilesCustomizationService::CSRF_WEB_PROFILES_SAVE, (string) $request->request->get('_csrf_token', '')))) {
                $this->addFlash($request, 'error', 'employment.companies.flash.csrf_invalid');
                return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
            }
            $result = $this->companyCvWebProfilesCustomizationService->saveWebProfilesFromRequest($company, $request);
            foreach ($result['flashError'] as $messageKey) { $this->addFlash($request, 'error', $messageKey); }
            foreach ($result['flashWarning'] as $messageKey) { $this->addFlash($request, 'warning', $messageKey); }
            foreach ($result['flashSuccess'] as $messageKey) { $this->addFlash($request, 'success', $messageKey); }
            return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
        }

        if ($formScope === 'company_cv_references_enable') {
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(CompanyCvReferencesCustomizationService::CSRF_REFERENCES_ENABLE, (string) $request->request->get('_token', '')))) {
                $this->addFlash($request, 'error', 'employment.companies.flash.csrf_invalid');
                return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
            }
            $this->companyCvReferencesCustomizationService->enableReferencesCustomization($company);
            $this->addFlash($request, 'success', 'employment.companies.cv_customization.references.flash.enabled');
            return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
        }

        if ($formScope === 'company_cv_references_reset') {
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(CompanyCvReferencesCustomizationService::CSRF_REFERENCES_RESET, (string) $request->request->get('_token', '')))) {
                $this->addFlash($request, 'error', 'employment.companies.flash.csrf_invalid');
                return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
            }
            $this->companyCvReferencesCustomizationService->resetReferencesToInherited($company);
            $this->addFlash($request, 'success', 'employment.companies.cv_customization.references.flash.reset');
            return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
        }

        if ($formScope === 'company_cv_references_save') {
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(CompanyCvReferencesCustomizationService::CSRF_REFERENCES_SAVE, (string) $request->request->get('_csrf_token', '')))) {
                $this->addFlash($request, 'error', 'employment.companies.flash.csrf_invalid');
                return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
            }
            $result = $this->companyCvReferencesCustomizationService->saveReferencesFromRequest($company, $request);
            foreach ($result['flashError'] as $messageKey) { $this->addFlash($request, 'error', $messageKey); }
            foreach ($result['flashWarning'] as $messageKey) { $this->addFlash($request, 'warning', $messageKey); }
            foreach ($result['flashSuccess'] as $messageKey) { $this->addFlash($request, 'success', $messageKey); }
            return new RedirectResponse($this->urlGenerator->generate('admin_employment_companies_cv_customization', $redirectParams));
        }

        return null;
    }

    /**
     * @brief Build redirect query params preserving section, panel, and locale.
     *
     * @param Request $request HTTP request.
     * @param TrackedCompany $company Tracked company.
     * @return array<string, int|string>
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function buildCvCustomizationRedirectParams(Request $request, TrackedCompany $company): array
    {
        $section = (string) $request->request->get('customization_section', '');
        if ($section === '') {
            $sectionQuery = $request->query->get('section');
            $section = is_string($sectionQuery) ? $sectionQuery : '';
        }
        if ($section === '' || !CompanyCvCustomizationSectionKey::isValid($section)) {
            $section = CompanyCvCustomizationSectionKey::ABOUT;
        }

        $params = ['id' => (int) $company->getId(), 'section' => $section];

        if ($section === CompanyCvCustomizationSectionKey::ABOUT) {
            $panel = (string) $request->request->get('customization_panel', $request->query->get('panel', ''));
            if (in_array($panel, ['section', 'photo', 'presentation'], true)) {
                $params['panel'] = $panel;
            }
        }

        if ($section === CompanyCvCustomizationSectionKey::EXPERIENCE) {
            $panel = (string) $request->request->get('customization_panel', $request->query->get('panel', ''));
            if ($panel === 'professional_entries') {
                $params['panel'] = $panel;
            }
        }

        if ($section === CompanyCvCustomizationSectionKey::SKILLS) {
            $panel = (string) $request->request->get('customization_panel', $request->query->get('panel', ''));
            if ($panel === 'skills_catalog') {
                $params['panel'] = $panel;
            }
        }

        if ($section === CompanyCvCustomizationSectionKey::EDUCATION) {
            $panel = (string) $request->request->get('customization_panel', $request->query->get('panel', ''));
            if ($panel === 'education_entries') {
                $params['panel'] = $panel;
            }
        }

        if ($section === CompanyCvCustomizationSectionKey::CERTIFICATION) {
            $panel = (string) $request->request->get('customization_panel', $request->query->get('panel', ''));
            if ($panel === 'certification_entries') {
                $params['panel'] = $panel;
            }
        }

        if ($section === CompanyCvCustomizationSectionKey::LANGUAGES && (string) $request->request->get('customization_panel', $request->query->get('panel', '')) === 'languages_entries') {
            $params['panel'] = 'languages_entries';
        }

        if ($section === CompanyCvCustomizationSectionKey::INTERESTS && (string) $request->request->get('customization_panel', $request->query->get('panel', '')) === 'interests_entries') {
            $params['panel'] = 'interests_entries';
        }

        if ($section === CompanyCvCustomizationSectionKey::WEB_PROFILES && (string) $request->request->get('customization_panel', $request->query->get('panel', '')) === 'web_profiles_entries') {
            $params['panel'] = 'web_profiles_entries';
        }

        if ($section === CompanyCvCustomizationSectionKey::REFERENCES) {
            $panel = (string) $request->request->get('customization_panel', $request->query->get('panel', ''));
            if (in_array($panel, ['section', 'references_entries'], true)) {
                $params['panel'] = $panel;
            }
        }

        $locale = (string) $request->request->get('customization_locale', $request->query->get('locale', ''));
        if ($locale !== '') {
            $params['locale'] = $locale;
        }

        if ($section === CompanyCvCustomizationSectionKey::EXPERIENCE) {
            $entry = (string) $request->request->get('customization_entry', $request->query->get('entry', ''));
            if ($entry !== '' && ExperienceContract::isValidUuid($entry)) {
                $params['panel'] = 'professional_entries';
                $params['entry'] = $entry;
            }
        }

        return $params;
    }

    /**
     * @brief Push flash message on session flash bag.
     *
     * @param Request $request HTTP request.
     * @param string $type Flash type (`success`, `warning`, `error`).
     * @param string $messageKey Translation key.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function addFlash(Request $request, string $type, string $messageKey): void
    {
        FlashMessageHelper::add($request, $type, $messageKey);
    }

    /**
     * @brief Push structured flash message (translation key + parameters) on session flash bag.
     *
     * @param Request $request HTTP request.
     * @param string $type Flash type (`success`, `warning`, `error`).
     * @param array{message?: string, parameters?: array<string, string>} $error Structured flash payload.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function addStructuredFlash(Request $request, string $type, array $error): void
    {
        if (!isset($error['message']) || !is_string($error['message'])) {
            return;
        }

        $parameters = $error['parameters'] ?? [];
        if (!is_array($parameters)) {
            $parameters = [];
        }

        /** @var array<string, string> $normalizedParameters */
        $normalizedParameters = [];
        foreach ($parameters as $key => $value) {
            if (is_string($key) && (is_string($value) || is_numeric($value))) {
                $normalizedParameters[$key] = (string) $value;
            }
        }

        FlashMessageHelper::add($request, $type, [
            'message' => $error['message'],
            'parameters' => $normalizedParameters,
        ]);
    }

    /**
     * @brief Shared Twig variables for country selects and labels.
     *
     * @return array{employmentCountries: list<\App\Entity\EmploymentCountry>, countryLabelsByCode: array<string, string>, activeLocales: list<string>, defaultPresentationLocale: string, csrfCountryCreateToken: string}
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function buildCountryModalViewData(): array
    {
        $localeConfig = $this->localeConfigurationService->getConfiguration();
        $activeLocales = $this->presentationLocaleResolver->getActiveLocales();

        return [
            'employmentCountries' => $this->employmentCountryList->getCountries(),
            'countryLabelsByCode' => $this->employmentCountryList->getLabelsByCode(),
            'activeLocales' => $activeLocales,
            'defaultPresentationLocale' => is_string($localeConfig['defaultLocale'] ?? null)
                ? $localeConfig['defaultLocale']
                : ($activeLocales[0] ?? 'fr'),
            'csrfCountryCreateToken' => $this->csrfTokenManager->getToken('employment_country_create')->getValue(),
        ];
    }

    /**
     * @brief Twig variables for CV / LM document variant selects.
     *
     * @return array{cvDocumentVariants: list<\App\Entity\EmploymentDocumentVariant>, lmDocumentVariants: list<\App\Entity\EmploymentDocumentVariant>}
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function buildDocumentVariantViewData(): array
    {
        return [
            'cvDocumentVariants' => $this->documentVariantRepository->findActiveByKindForCompanySelect(EmploymentDocumentKind::CV),
            'lmDocumentVariants' => $this->documentVariantRepository->findActiveByKindForCompanySelect(EmploymentDocumentKind::LM),
        ];
    }

    /**
     * @brief Build optional document variant ids from request.
     *
     * @param Request $request HTTP request.
     * @return TrackedCompanyDocumentInput
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function documentInputFromRequest(Request $request): TrackedCompanyDocumentInput
    {
        return new TrackedCompanyDocumentInput(
            $this->nullablePositiveIntFromRequest($request, 'cv_document_variant_id'),
            $this->nullablePositiveIntFromRequest($request, 'lm_document_variant_id'),
        );
    }

    /**
     * @brief Parse optional positive integer form field.
     *
     * @param Request $request HTTP request.
     * @param string $key Field name.
     * @return int|null
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function nullablePositiveIntFromRequest(Request $request, string $key): ?int
    {
        $raw = trim((string) $request->request->get($key, ''));
        if ($raw === '') {
            return null;
        }

        if (!ctype_digit($raw)) {
            return null;
        }

        $value = (int) $raw;

        return $value > 0 ? $value : null;
    }

    /**
     * @brief Build optional recruiter contact input from request body.
     *
     * @param Request $request HTTP request.
     * @return TrackedCompanyContactInput
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function contactInputFromRequest(Request $request): TrackedCompanyContactInput
    {
        return new TrackedCompanyContactInput(
            recruiterName: $this->nullableRequestString($request, 'recruiter_name'),
            addressLine1: $this->nullableRequestString($request, 'address_line1'),
            addressLine2: $this->nullableRequestString($request, 'address_line2'),
            addressPostalCode: $this->nullableRequestString($request, 'address_postal_code'),
            addressCity: $this->nullableRequestString($request, 'address_city'),
            phone: $this->nullableRequestString($request, 'phone'),
            email: $this->nullableRequestString($request, 'email'),
        );
    }

    /**
     * @brief Read trimmed request field or null when absent.
     *
     * @param Request $request HTTP request.
     * @param string $key Form field name.
     * @return string|null
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function nullableRequestString(Request $request, string $key): ?string
    {
        if (!$request->request->has($key)) {
            return null;
        }

        $value = trim((string) $request->request->get($key, ''));

        return $value === '' ? null : $value;
    }

    /**
     * @brief Throw not found HTTP exception.
     *
     * @return never
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function createNotFoundException(): never
    {
        throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }
}
