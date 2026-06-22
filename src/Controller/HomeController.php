<?php

namespace App\Controller;

use App\Repository\HomeCustomizationRepository;
use App\Service\Customization\CustomizationPlaceholderStateService;
use App\Service\Customization\CustomizationUiStateResolver;
use App\Service\Home\HomeCustomizationService;
use App\Service\Home\HomeQuickTileService;
use App\Service\Locale\LocaleConfigurationService;
use App\Service\Site\SiteMailTemplatePreviewService;
use App\Service\Setup\SiteSetupOnboardingService;
use App\Service\Site\SiteConfigurationService;
use App\Service\Site\SiteMailTemplateAdminService;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;

/**
 * Controller HomeController.
 */
class HomeController extends AbstractController
{
    /**
     * @brief Render application home page with public custom quick tiles.
     * @param Request $request Current request.
     * @param Environment $twig Twig environment.
     * @param HomeCustomizationService $homeCustomizationService Home customization service.
     * @param HomeCustomizationRepository $homeCustomizationRepository Home customization repository.
     * @param CustomizationPlaceholderStateService $placeholderStateService Placeholder state service.
     * @param HomeQuickTileService $homeQuickTileService Custom quick tile resolver.
     * @param LocaleConfigurationService $localeConfigurationService Locale configuration service.
     * @param TranslatorInterface $translator Translator.
     * @return Response HTML home landing.
     * @date 2026-05-18
     * @author Stephane H.
     */
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(
        Request $request,
        Environment $twig,
        HomeCustomizationService $homeCustomizationService,
        HomeCustomizationRepository $homeCustomizationRepository,
        CustomizationPlaceholderStateService $placeholderStateService,
        HomeQuickTileService $homeQuickTileService,
        LocaleConfigurationService $localeConfigurationService,
        TranslatorInterface $translator,
    ): Response {
        $cvPlaceholderActive = $placeholderStateService->isActive();
        $customization = $cvPlaceholderActive
            ? $homeCustomizationRepository->getSingleton()
            : $homeCustomizationService->getOrCreateSingleton();

        if ($customization === null && $cvPlaceholderActive) {
            $customization = $homeCustomizationService->createPlaceholderSingleton();
        }

        $introResolved = '';
        if ($customization !== null) {
            $introResolved = $homeCustomizationService->resolveIntroText($request->getLocale(), $customization);
        }

        if ($cvPlaceholderActive && trim(strip_tags($introResolved)) === '') {
            $introResolved = $translator->trans('home.placeholder.intro', [], 'messages', $request->getLocale());
        }

        $localeConfiguration = $localeConfigurationService->getConfiguration();
        $activeLocales = is_array($localeConfiguration['activeLocales'] ?? null)
            ? $localeConfiguration['activeLocales']
            : $localeConfigurationService->getSupportedLocales();
        if ($activeLocales === []) {
            $activeLocales = $localeConfigurationService->getSupportedLocales();
        }
        $defaultLocale = is_string($localeConfiguration['defaultLocale'] ?? null)
            ? $localeConfiguration['defaultLocale']
            : ($activeLocales[0] ?? 'fr');

        $homeQuickTiles = $homeQuickTileService->resolveForHome($request->getLocale(), $defaultLocale);

        return new Response($twig->render('home/index.html.twig', [
            'homeCustomization' => $customization,
            'homeIntroResolved' => $introResolved,
            'cvPlaceholderActive' => $cvPlaceholderActive,
            'signatureImageResolvedPath' => $customization !== null
                ? $homeCustomizationService->resolveSignatureImageRelativePath($customization)
                : null,
            'backgroundImageResolvedPath' => $customization !== null
                ? $homeCustomizationService->resolveBackgroundImageRelativePath($customization)
                : null,
            'homeQuickTiles' => $homeQuickTiles,
            'quickTileActiveLocales' => $activeLocales,
            'quickTileDefaultLocale' => $defaultLocale,
        ]));
    }

    /**
     * @brief Render protected dashboard page.
     * @param Environment $twig Twig environment.
     * @return Response
     * @date 2026-04-22
     * @author Stephane H.
     */
    #[IsGranted('ROLE_CV_EDIT')]
    #[Route('/dashboard', name: 'app_dashboard', methods: ['GET'])]
    public function dashboard(
        Environment $twig,
        SiteConfigurationService $siteConfigurationService,
        SiteSetupOnboardingService $siteSetupOnboardingService,
        Request $request,
    ): Response {
        return new Response($twig->render('home/dashboard.html.twig', [
            'maintenanceModeEnabled' => $siteConfigurationService->isMaintenanceModeEnabled(),
            'setupOnboarding' => $siteSetupOnboardingService->resolveChecklist($request->getLocale()),
        ]));
    }

    /**
     * @brief Render and handle dashboard home customization page including custom quick tiles panel.
     *
     * @param Request $request Current request.
     * @param Environment $twig Twig environment.
     * @param LocaleConfigurationService $localeConfigurationService Locale configuration service.
     * @param HomeCustomizationService $homeCustomizationService Home customization service.
     * @param HomeQuickTileService $homeQuickTileService Quick tile service for embedded admin panel.
     * @param CustomizationUiStateResolver $customizationUiStateResolver UI state resolver.
     * @return Response
     * @date 2026-05-19
     * @author Stephane H.
     */
    #[IsGranted('ROLE_CV_EDIT')]
    #[Route('/dashboard/customization/home', name: 'app_dashboard_customization_home', methods: ['GET', 'POST'])]
    public function dashboardCustomizationHome(
        Request $request,
        Environment $twig,
        LocaleConfigurationService $localeConfigurationService,
        HomeCustomizationService $homeCustomizationService,
        HomeQuickTileService $homeQuickTileService,
        CustomizationUiStateResolver $customizationUiStateResolver,
    ): Response {
        $localeConfiguration = $localeConfigurationService->getConfiguration();
        $activeLocales = is_array($localeConfiguration['activeLocales'] ?? null) ? $localeConfiguration['activeLocales'] : [];
        if ($activeLocales === []) {
            $activeLocales = $localeConfigurationService->getSupportedLocales();
        }
        $defaultLocale = is_string($localeConfiguration['defaultLocale'] ?? null)
            ? $localeConfiguration['defaultLocale']
            : ($activeLocales[0] ?? 'fr');

        if ($request->isMethod('POST')) {
            $uiState = $customizationUiStateResolver->resolveHomeFromRequest($request, $activeLocales, $defaultLocale);
            $redirectParams = $customizationUiStateResolver->buildHomeRedirectParams($uiState);

            $csrfToken = (string) $request->request->get('_csrf_token', '');
            if (!$this->isCsrfTokenValid('dashboard_customization_home', $csrfToken)) {
                $this->addFlash('warning', 'dashboard.customization_home.flash.invalid_csrf');

                return $this->redirectToRoute('app_dashboard_customization_home', $redirectParams);
            }

            try {
                $homeCustomizationService->saveFromAdminRequest($request, $activeLocales);
                $this->addFlash('success', 'dashboard.customization_home.flash.saved');
            } catch (\InvalidArgumentException $exception) {
                $this->addFlash('warning', $exception->getMessage());
            }

            return $this->redirectToRoute('app_dashboard_customization_home', $redirectParams);
        }

        $uiState = $customizationUiStateResolver->resolveHomeFromRequest($request, $activeLocales, $defaultLocale);
        $homeCustomization = $homeCustomizationService->getOrCreateSingleton();
        $editId = (int) $request->query->get('edit', 0);
        $homeQuickTileEdit = $editId > 0 ? $homeQuickTileService->findTileForAdmin($editId) : null;

        return new Response($twig->render('home/customization_home.html.twig', [
            'homeCustomization' => $homeCustomization,
            'activeLocales' => $activeLocales,
            'defaultLocale' => $defaultLocale,
            'homeCustomizationActivePanel' => $uiState->panel,
            'homeCustomizationActiveLocale' => $uiState->locale,
            'homeQuickTiles' => $homeQuickTileService->listAllForAdmin(),
            'homeQuickTileEdit' => $homeQuickTileEdit,
            'siteFaviconResolvedPath' => $homeCustomizationService->resolveSiteFaviconRelativePath(),
            'defaultSiteFaviconPath' => HomeCustomizationService::DEFAULT_SITE_FAVICON_PATH,
            'backgroundImageResolvedPath' => $homeCustomizationService->resolveBackgroundImageRelativePath($homeCustomization),
            'signatureImageResolvedPath' => $homeCustomizationService->resolveSignatureImageRelativePath($homeCustomization),
            'homeCustomizationService' => $homeCustomizationService,
        ]));
    }

    /**
     * @brief Render and handle dashboard language configuration page.
     * @param Request $request Current request.
     * @param Environment $twig Twig environment.
     * @param LocaleConfigurationService $localeConfigurationService Locale configuration service.
     * @return Response
     * @date 2026-05-08
     * @author Stephane H.
     */
    #[IsGranted('ROLE_ADMIN')]
    #[Route('/dashboard/configuration/language', name: 'app_dashboard_configuration_language', methods: ['GET', 'POST'])]
    public function dashboardConfigurationLanguage(Request $request, Environment $twig, LocaleConfigurationService $localeConfigurationService): Response
    {
        if ($request->isMethod('POST')) {
            $csrfToken = (string) $request->request->get('_csrf_token', '');
            if (!$this->isCsrfTokenValid('dashboard_language_configuration', $csrfToken)) {
                $this->addFlash('warning', 'dashboard.configuration_language.flash.invalid_csrf');

                return $this->redirectToRoute('app_dashboard_configuration_language');
            }

            $activeLocales = $request->request->all('active_locales');
            $defaultLocale = (string) $request->request->get('default_locale', '');

            try {
                $localeConfigurationService->saveConfiguration(is_array($activeLocales) ? $activeLocales : [], $defaultLocale);
                $this->addFlash('success', 'dashboard.configuration_language.flash.saved');
            } catch (\InvalidArgumentException $exception) {
                $this->addFlash('warning', $exception->getMessage());
            }

            return $this->redirectToRoute('app_dashboard_configuration_language');
        }

        return new Response($twig->render('home/configuration_language.html.twig', [
            'localeConfiguration' => $localeConfigurationService->getConfiguration(),
            'supportedLocales' => $localeConfigurationService->getSupportedLocales(),
        ]));
    }

    /**
     * @brief Render and handle dashboard site configuration page (favicon, colors, mail templates, maintenance, antibot).
     *
     * @param Request $request Current request.
     * @param Environment $twig Twig environment.
     * @param SiteConfigurationService $siteConfigurationService Site configuration service.
     * @param SiteMailTemplateAdminService $siteMailTemplateAdminService Mail template admin service.
     * @param HomeCustomizationService $homeCustomizationService Home customization service.
     * @param string $appTotpEmailFrom Environment fallback sender email.
     * @param string $appCvContactToEmail Environment fallback recipient email.
     * @return Response
     * @date 2026-06-16
     * @author Stephane H.
     */
    #[IsGranted('ROLE_CV_EDIT')]
    #[Route('/dashboard/configuration/site', name: 'app_dashboard_configuration_site', methods: ['GET', 'POST'])]
    public function dashboardConfigurationSite(
        Request $request,
        Environment $twig,
        SiteConfigurationService $siteConfigurationService,
        SiteMailTemplateAdminService $siteMailTemplateAdminService,
        HomeCustomizationService $homeCustomizationService,
        #[Autowire('%app.totp_email_from%')] string $appTotpEmailFrom,
        #[Autowire('%app.cv_contact_to_email%')] string $appCvContactToEmail,
    ): Response {
        $activeLocales = $siteConfigurationService->getActiveLocales();

        if ($request->isMethod('POST')) {
            $csrfToken = (string) $request->request->get('_csrf_token', '');
            if (!$this->isCsrfTokenValid('dashboard_site_configuration', $csrfToken)) {
                $this->addFlash('warning', 'dashboard.configuration_site.flash.invalid_csrf');

                return $this->redirectToRoute('app_dashboard_configuration_site');
            }

            $resetType = trim((string) $request->request->get('mail_templates_reset_type', ''));

            try {
                $siteConfigurationService->saveFromAdminRequest($request);
                if ($resetType !== '') {
                    $this->addFlash('success', 'dashboard.configuration_site.mail_templates.flash.reset');
                } else {
                    $this->addFlash('success', 'dashboard.configuration_site.flash.saved');
                }
            } catch (\InvalidArgumentException $exception) {
                $this->addFlash('warning', $exception->getMessage());
            }

            return $this->redirectToRoute('app_dashboard_configuration_site');
        }

        $homeCustomization = $homeCustomizationService->getOrCreateSingleton();
        $siteSeoMetaDescriptionByLocale = [];
        foreach ($activeLocales as $localeCode) {
            if (!is_string($localeCode) || trim($localeCode) === '') {
                continue;
            }
            $siteSeoMetaDescriptionByLocale[$localeCode] = $homeCustomizationService->resolveMetaDescriptionForLocale($localeCode);
        }

        return new Response($twig->render('home/configuration_site.html.twig', [
            'homeCustomization' => $homeCustomization,
            'cvAntibotThreshold' => $siteConfigurationService->getCvAntibotThreshold(),
            'maintenanceModeEnabled' => $siteConfigurationService->isMaintenanceModeEnabled(),
            'recruiterVisitNotificationEnabled' => $siteConfigurationService->isRecruiterVisitNotificationEnabled(),
            'siteAccentColor' => $siteConfigurationService->getResolvedAccentColor(),
            'siteCvMenuBackgroundColor' => $siteConfigurationService->getResolvedCvMenuBackground(),
            'siteFaviconResolvedPath' => $homeCustomizationService->resolveSiteFaviconRelativePath(),
            'openGraphImageResolvedPath' => $homeCustomizationService->resolveOpenGraphImageRelativePath(),
            'defaultSiteFaviconPath' => HomeCustomizationService::DEFAULT_SITE_FAVICON_PATH,
            'siteMailTemplates' => $siteMailTemplateAdminService->buildAdminViewModel($activeLocales),
            'activeLocales' => $activeLocales,
            'siteSeoMetaDescriptionByLocale' => $siteSeoMetaDescriptionByLocale,
            'defaultMailFromEmail' => $appTotpEmailFrom,
            'defaultMailToEmail' => $appCvContactToEmail,
        ]));
    }

    /**
     * @brief Render HTML preview for one mail template type and locale from admin draft values.
     *
     * @param Request $request Preview POST request.
     * @param SiteConfigurationService $siteConfigurationService Site configuration service.
     * @param SiteMailTemplatePreviewService $siteMailTemplatePreviewService Mail preview renderer.
     * @param TranslatorInterface $translator Translation service.
     * @return JsonResponse
     * @date 2026-06-16
     * @author Stephane H.
     */
    #[IsGranted('ROLE_CV_EDIT')]
    #[Route('/dashboard/configuration/site/mail-templates/preview', name: 'app_dashboard_site_mail_template_preview', methods: ['POST'])]
    public function previewSiteMailTemplate(
        Request $request,
        SiteConfigurationService $siteConfigurationService,
        SiteMailTemplatePreviewService $siteMailTemplatePreviewService,
        TranslatorInterface $translator,
    ): JsonResponse {
        $csrfToken = (string) $request->request->get('_csrf_token', '');
        if (!$this->isCsrfTokenValid('dashboard_site_configuration', $csrfToken)) {
            return new JsonResponse([
                'error' => $translator->trans('dashboard.configuration_site.flash.invalid_csrf', [], 'messages', $request->getLocale()),
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $preview = $siteMailTemplatePreviewService->renderFromAdminPreviewRequest(
                $request,
                $siteConfigurationService->getActiveLocales(),
            );
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse([
                'error' => $translator->trans($exception->getMessage(), [], 'messages', $request->getLocale()),
            ], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse([
            'type' => $preview['type'],
            'locale' => $preview['locale'],
            'fromEmail' => $preview['fromEmail'],
            'fromName' => $preview['fromName'],
            'toEmail' => $preview['toEmail'],
            'subject' => $preview['subject'],
            'html' => $preview['html'],
        ]);
    }
}
