<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Site\SiteSitemapService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @brief Serves the public XML sitemap for landing and CV entry points.
 */
final class SitemapController extends AbstractController
{
    /**
     * @brief Build sitemap.xml with absolute URLs for indexable public routes.
     *
     * @param SiteSitemapService $siteSitemapService Public sitemap builder.
     * @return Response XML sitemap document.
     * @date 2026-06-21
     * @author Stephane H.
     */
    #[Route('/sitemap.xml', name: 'app_sitemap', methods: ['GET'])]
    public function sitemap(SiteSitemapService $siteSitemapService): Response
    {
        return new Response(
            $siteSitemapService->buildXmlDocument(),
            Response::HTTP_OK,
            ['Content-Type' => 'application/xml; charset=UTF-8'],
        );
    }
}
