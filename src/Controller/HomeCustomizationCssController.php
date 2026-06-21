<?php

namespace App\Controller;

use App\Service\Home\HomeCustomizationService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller HomeCustomizationCssController.
 */
class HomeCustomizationCssController
{
    /**
     * @brief Serve merged landing customization stylesheet for public users.
     * @param HomeCustomizationService $homeCustomizationService Customization service.
     * @return Response
     * @date 2026-05-16
     * @author Stephane H.
     */
    #[Route('/css/home-custom.css', name: 'app_home_customization_css', methods: ['GET'])]
    public function stylesheet(HomeCustomizationService $homeCustomizationService): Response
    {
        $customization = $homeCustomizationService->getOrCreateSingleton();
        $css = $homeCustomizationService->buildStylesheetCss($customization);

        $response = new Response($css, Response::HTTP_OK, [
            'Content-Type' => 'text/css; charset=UTF-8',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
        $response->setEtag(md5($css));

        return $response;
    }
}
