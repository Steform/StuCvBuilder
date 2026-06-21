<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\EmploymentCountry;
use App\Repository\EmploymentCountryRepository;
use App\Service\Employment\EmploymentCountryList;
use App\Service\Employment\EmploymentCountryManagementService;
use App\Service\Employment\EmploymentCountryPresentationLocaleResolver;
use App\Service\Locale\LocaleConfigurationService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * Admin endpoints for employment country options.
 */
#[IsGranted('ROLE_ADMIN')]
class EmploymentCountryAdminController
{
    private const CSRF_CREATE = 'employment_country_create';

    private const CSRF_EDIT = 'employment_country_edit';

    /**
     * @brief Build employment country admin controller.
     *
     * @param EmploymentCountryRepository $employmentCountryRepository Country repository.
     * @param EmploymentCountryList $employmentCountryList Country list helper.
     * @param EmploymentCountryPresentationLocaleResolver $presentationLocaleResolver Presentation locale helper.
     * @param LocaleConfigurationService $localeConfigurationService Site locale configuration.
     * @param EmploymentCountryManagementService $countryManagementService Country management service.
     * @param CsrfTokenManagerInterface $csrfTokenManager CSRF manager.
     * @param UrlGeneratorInterface $urlGenerator URL generator.
     * @param TranslatorInterface $translator Translator for error messages.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function __construct(
        private readonly EmploymentCountryRepository $employmentCountryRepository,
        private readonly EmploymentCountryList $employmentCountryList,
        private readonly EmploymentCountryPresentationLocaleResolver $presentationLocaleResolver,
        private readonly LocaleConfigurationService $localeConfigurationService,
        private readonly EmploymentCountryManagementService $countryManagementService,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @brief List managed countries with edit actions.
     *
     * @param Environment $twig Twig environment.
     * @return Response
     * @date 2026-06-01
     * @author Stephane H.
     */
    #[Route('/admin/employment/countries', name: 'admin_employment_countries_index', methods: ['GET'])]
    public function index(Environment $twig): Response
    {
        $localeConfig = $this->localeConfigurationService->getConfiguration();

        return new Response($twig->render('admin/employment/countries/index.html.twig', [
            'employmentCountries' => $this->employmentCountryList->getCountries(),
            'activeLocales' => $this->presentationLocaleResolver->getActiveLocales(),
            'defaultPresentationLocale' => is_string($localeConfig['defaultLocale'] ?? null)
                ? $localeConfig['defaultLocale']
                : ($this->presentationLocaleResolver->getActiveLocales()[0] ?? 'fr'),
            'csrfCountryCreateToken' => $this->csrfTokenManager->getToken(self::CSRF_CREATE)->getValue(),
            'csrfCountryEditToken' => $this->csrfTokenManager->getToken(self::CSRF_EDIT)->getValue(),
        ]));
    }

    /**
     * @brief Create country via AJAX from company modal or countries page.
     *
     * @param Request $request HTTP request.
     * @return JsonResponse Created country payload or error message.
     * @date 2026-06-01
     * @author Stephane H.
     */
    #[Route('/admin/employment/countries', name: 'admin_employment_countries_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $token = (string) $request->request->get('_token', '');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_CREATE, $token))) {
            return new JsonResponse([
                'error' => $this->translator->trans('employment.companies.flash.csrf_invalid', [], 'messages'),
            ], Response::HTTP_FORBIDDEN);
        }

        $result = $this->countryManagementService->create(
            (string) $request->request->get('code', ''),
            (string) $request->request->get('label', ''),
            (string) $request->request->get('presentation_locale', ''),
        );

        if ($result['error'] !== null) {
            return new JsonResponse([
                'error' => $this->translator->trans($result['error'], [], 'messages'),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $country = $result['country'];
        if (!$country instanceof EmploymentCountry) {
            return new JsonResponse([
                'error' => $this->translator->trans('employment.countries.flash.create_failed', [], 'messages'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse([
            'code' => $country->getCode(),
            'label' => $country->getLabel(),
            'presentationLocale' => $country->getPresentationLocale(),
        ], Response::HTTP_CREATED);
    }

    /**
     * @brief Update country label from admin list modal.
     *
     * @param Request $request HTTP request.
     * @param int $id Country id.
     * @return RedirectResponse
     * @date 2026-06-01
     * @author Stephane H.
     */
    #[Route('/admin/employment/countries/{id}/edit', name: 'admin_employment_countries_edit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function edit(Request $request, int $id): RedirectResponse
    {
        $token = (string) $request->request->get('_token', '');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_EDIT, $token))) {
            $request->getSession()->getFlashBag()->add('error', 'employment.companies.flash.csrf_invalid');

            return new RedirectResponse($this->urlGenerator->generate('admin_employment_countries_index'));
        }

        $country = $this->employmentCountryRepository->find($id);
        if (!$country instanceof EmploymentCountry) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
        }

        $error = $this->countryManagementService->update(
            $country,
            (string) $request->request->get('label', ''),
            (string) $request->request->get('presentation_locale', ''),
        );
        if ($error !== null) {
            $request->getSession()->getFlashBag()->add('error', $error);
        } else {
            $request->getSession()->getFlashBag()->add('success', 'employment.countries.flash.updated');
        }

        return new RedirectResponse($this->urlGenerator->generate('admin_employment_countries_index'));
    }
}
