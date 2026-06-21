<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Site\SiteColorsResolver;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @brief Serves dynamic CSS for public CV sidebar/menu background color.
 *
 * @date 2026-05-31
 * @author Stephane H.
 */
class CvPublicSidebarCssController
{
    /**
     * @brief Serve CSS variable driving CV public sidebar background color.
     *
     * @param SiteColorsResolver $siteColorsResolver Site colors resolver.
     * @return Response CSS response with sidebar background variable.
     * @date 2026-05-31
     * @author Stephane H.
     */
    #[Route('/css/cv-public-sidebar.css', name: 'app_cv_public_sidebar_css', methods: ['GET'])]
    public function stylesheet(SiteColorsResolver $siteColorsResolver): Response
    {
        $menuBackground = $siteColorsResolver->resolveCvMenuBackground();
        $css = sprintf(
            ".cv-public-page{--cv-public-sidebar-bg:%s;}\n",
            $menuBackground
        );

        return new Response($css, Response::HTTP_OK, [
            'Content-Type' => 'text/css; charset=UTF-8',
            'Cache-Control' => 'public, max-age=120, must-revalidate',
        ]);
    }
}
