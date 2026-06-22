<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\Locale\LocaleConfigurationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @brief Legacy route redirecting public CV identity admin to the cv_data customization tab.
 *
 * @date 2026-05-23
 * @author Stephane H.
 */
class CvPublicIdentityController extends AbstractController
{
    public function __construct(
        private readonly LocaleConfigurationService $localeConfigurationService,
    ) {
    }

    /**
     * @brief Redirect legacy `/admin/cv/public-identity` URLs to `admin_cv_index?tab=cv_data`.
     *
     * @param Request $request HTTP GET or POST (POST locale field preserved when valid).
     * @return Response Redirect to CV customization index.
     * @date 2026-05-23
     * @author Stephane H.
     */
    #[Route('/admin/cv/public-identity', name: 'admin_cv_public_identity', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_CV_EDIT')]
    public function __invoke(Request $request): Response
    {
        $localeConfiguration = $this->localeConfigurationService->getConfiguration();
        $activeLocales = is_array($localeConfiguration['activeLocales'] ?? null) ? $localeConfiguration['activeLocales'] : [];
        if ($activeLocales === []) {
            $activeLocales = $this->localeConfigurationService->getSupportedLocales();
        }
        $defaultLocale = is_string($localeConfiguration['defaultLocale'] ?? null)
            ? $localeConfiguration['defaultLocale']
            : ($activeLocales[0] ?? 'fr');

        $locale = $request->query->get('locale');
        if ($request->isMethod('POST')) {
            $formLocale = $request->request->get('cv_public_identity_form_locale');
            if (is_string($formLocale) && trim($formLocale) !== '') {
                $locale = $formLocale;
            }
        }

        $resolvedLocale = $this->resolveLocale($locale, $activeLocales, $defaultLocale);
        $params = ['tab' => 'cv_data', 'locale' => $resolvedLocale];

        return $this->redirectToRoute('admin_cv_index', $params);
    }

    /**
     * @brief Resolve locale code from query or POST with configured default fallback.
     *
     * @param mixed $raw Raw locale value.
     * @param list<string> $activeLocales Allowed locale codes.
     * @param string $defaultLocale Default locale when missing or invalid.
     * @return string Resolved locale code.
     * @date 2026-05-23
     * @author Stephane H.
     */
    private function resolveLocale(mixed $raw, array $activeLocales, string $defaultLocale): string
    {
        if (is_string($raw) && in_array($raw, $activeLocales, true)) {
            return $raw;
        }

        return in_array($defaultLocale, $activeLocales, true) ? $defaultLocale : ($activeLocales[0] ?? 'fr');
    }
}
