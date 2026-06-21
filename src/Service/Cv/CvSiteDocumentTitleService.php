<?php

declare(strict_types=1);

namespace App\Service\Cv;

use App\Repository\CvProfileRepository;

/**
 * @brief Resolve the site-wide document title prefix from `cvPublicIdentity.displayName`.
 */
final class CvSiteDocumentTitleService
{
    public function __construct(
        private readonly CvProfileRepository $cvProfileRepository,
        private readonly CvPublicIdentityPlaceholderService $cvPublicIdentityPlaceholderService,
    ) {
    }

    /**
     * @brief Return owner name for `<title>` prefix (same rules as `[[cv.display_name]]`).
     *
     * @param string $viewerLocale Active request locale for fallback label.
     * @return string Plain-text display name.
     * @date 2026-05-16
     * @author Stephane H.
     */
    public function resolveOwnerPrefix(string $viewerLocale): string
    {
        $profile = $this->cvProfileRepository->findOneBy([], ['id' => 'DESC']);
        $payload = [];
        if ($profile !== null) {
            $decoded = json_decode($profile->getContentJson(), true);
            $payload = is_array($decoded) ? $decoded : [];
        }

        return $this->cvPublicIdentityPlaceholderService->resolveDisplayNamePlain($payload, $viewerLocale);
    }
}
