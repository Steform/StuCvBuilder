<?php

namespace App\Controller;

use App\Cv\SectionBackgroundContract;
use App\Cv\SectionTransitionContract;
use App\Repository\CvProfileRepository;
use App\Service\Customization\CustomizationPlaceholderStateService;
use App\Cv\AboutPresentationTypographyContract;
use App\Service\Cv\CvAboutPresentationTypographyCssBuilder;
use App\Service\Cv\CvAboutProfileSettingsService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Serves dynamic CV About profile photo placement CSS for public visitors.
 */
class CvAboutProfileCssController
{
    /**
     * @brief Serve sanitized stylesheet for About profile photo positioning and section backgrounds.
     *
     * @param CvProfileRepository $cvProfileRepository Profile repository.
     * @param CvAboutProfileSettingsService $cvAboutProfileSettingsService Placement CSS builder.
     * @param CustomizationPlaceholderStateService $placeholderStateService Placeholder mode gate.
     * @return Response CSS response with placement and atmosphere variables.
     * @date 2026-05-20
     * @author Stephane H.
     */
    #[Route('/css/cv-about-profile.css', name: 'app_cv_about_profile_css', methods: ['GET'])]
    public function stylesheet(
        CvProfileRepository $cvProfileRepository,
        CvAboutProfileSettingsService $cvAboutProfileSettingsService,
        CvAboutPresentationTypographyCssBuilder $cvAboutPresentationTypographyCssBuilder,
        CustomizationPlaceholderStateService $placeholderStateService,
    ): Response {
        $contentJson = '{}';
        if (!$placeholderStateService->isActive()) {
            $profile = $cvProfileRepository->findOneBy([], ['id' => 'DESC']);
            $contentJson = $profile?->getContentJson() ?? '{}';
        }

        $settings = $cvAboutProfileSettingsService->resolveFromContentJson($contentJson);
        $css = $cvAboutProfileSettingsService->buildStylesheetCss($settings);

        $decodedPayload = json_decode($contentJson, true);
        $decodedPayload = is_array($decodedPayload) ? $decodedPayload : [];
        $sectionBackgrounds = SectionBackgroundContract::normalizeMap(
            $decodedPayload[SectionBackgroundContract::KEY] ?? null,
            $decodedPayload
        );
        $sectionTransitions = SectionTransitionContract::normalizeMap(
            $decodedPayload[SectionTransitionContract::KEY] ?? null
        );

        $background = isset($settings['background']) && is_array($settings['background']) ? $settings['background'] : [];
        $aboutPrimary = is_string($background['primary'] ?? null) ? (string) $background['primary'] : '#010a22';
        $aboutSecondary = is_string($background['secondary'] ?? null) ? (string) $background['secondary'] : '#03215a';

        $css .= $cvAboutProfileSettingsService->buildSectionBackgroundVariablesCss(
            $sectionBackgrounds,
            $aboutPrimary,
            $aboutSecondary
        );
        $css .= $cvAboutProfileSettingsService->buildAllSectionTextureLayerCss($sectionBackgrounds, $sectionTransitions);
        $css .= $cvAboutPresentationTypographyCssBuilder->buildCss(
            AboutPresentationTypographyContract::fromPayload($decodedPayload)
        );

        return new Response($css, Response::HTTP_OK, [
            'Content-Type' => 'text/css; charset=UTF-8',
            'Cache-Control' => 'public, max-age=120, must-revalidate',
        ]);
    }
}
