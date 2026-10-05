<?php

namespace App\Controller;

use App\Cv\AboutSectionPatternCustomizationContract;
use App\Service\Customization\CustomizationPlaceholderStateService;
use App\Service\Cv\CvAboutPatternCssBuilder;
use App\Service\Cv\CvAboutStylesheetProfileResolver;
use App\Service\Site\SiteColorsResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Serves dynamic About section pattern tone CSS for public and admin preview.
 */
class CvAboutPatternCssController
{
    /**
     * @brief Serve CSS variables driving inline About SVG pattern colors.
     *
     * @param Request $request HTTP request (`company` query = optional company code).
     * @param CvAboutStylesheetProfileResolver $stylesheetProfileResolver Profile JSON resolver.
     * @param CvAboutPatternCssBuilder $cssBuilder Pattern variable builder.
     * @param CustomizationPlaceholderStateService $placeholderStateService Placeholder mode gate.
     * @return Response CSS response with pattern tone variables.
     * @date 2026-05-23
     * @author Stephane H.
     */
    #[Route('/css/cv-about-pattern.css', name: 'app_cv_about_pattern_css', methods: ['GET'])]
    public function stylesheet(
        Request $request,
        CvAboutStylesheetProfileResolver $stylesheetProfileResolver,
        CvAboutPatternCssBuilder $cssBuilder,
        CustomizationPlaceholderStateService $placeholderStateService,
        SiteColorsResolver $siteColorsResolver,
    ): Response {
        $payload = [];
        if (!$placeholderStateService->isActive()) {
            $companyCode = $request->query->get('company');
            $contentJson = $stylesheetProfileResolver->resolveContentJson(
                is_string($companyCode) ? $companyCode : null
            );
            $decoded = json_decode($contentJson, true);
            $payload = is_array($decoded) ? $decoded : [];
        }

        $pattern = AboutSectionPatternCustomizationContract::fromPayload($payload);
        $css = $cssBuilder->buildCss($pattern, $payload, $siteColorsResolver->resolveAccentColor());

        return new Response($css, Response::HTTP_OK, [
            'Content-Type' => 'text/css; charset=UTF-8',
            'Cache-Control' => 'public, max-age=120, must-revalidate',
        ]);
    }
}
