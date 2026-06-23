<?php

declare(strict_types=1);

namespace App\Service\Setup;

use App\Entity\HomeCustomization;
use App\Repository\CvProfileRepository;
use App\Repository\HomeCustomizationRepository;
use App\Service\Cv\AboutPresentationContract;
use App\Service\Cv\CvPublicIdentityContract;
use App\Service\Cv\ExperienceContract;
use App\Service\Home\HomeCustomizationService;

/**
 * @brief Resolve first-time setup checklist progress for admin onboarding.
 */
final class SiteSetupOnboardingService
{
    public function __construct(
        private readonly CvProfileRepository $cvProfileRepository,
        private readonly HomeCustomizationRepository $homeCustomizationRepository,
        private readonly HomeCustomizationService $homeCustomizationService,
    ) {
    }

    /**
     * @brief Build onboarding checklist items with completion state and admin routes.
     *
     * @param string $locale Admin viewer locale for intro resolution.
     * @return array{
     *     isComplete: bool,
     *     completedCount: int,
     *     totalCount: int,
     *     items: list<array{key: string, done: bool, route: string, routeParams: array<string, mixed>}>
     * }
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function resolveChecklist(string $locale): array
    {
        $profile = $this->cvProfileRepository->findOneBy([], ['id' => 'DESC']);
        $payload = [];
        if ($profile !== null) {
            $decoded = json_decode($profile->getContentJson(), true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        $homeCustomization = $this->homeCustomizationRepository->getSingleton();
        $items = [
            $this->buildItem('cv_identity', $this->hasConfiguredCvIdentity($payload), 'admin_cv_index', ['tab' => 'cv_data']),
            $this->buildItem('cv_about', AboutPresentationContract::hasPersistedPresentation($payload), 'admin_cv_index', ['tab' => 'about']),
            $this->buildItem('cv_experience', $this->hasConfiguredExperience($payload), 'admin_cv_index', ['tab' => 'experience']),
            $this->buildItem('home_signature', $this->hasConfiguredHomeSignature($homeCustomization), 'app_dashboard_customization_home', ['customization_panel' => 'signature']),
            $this->buildItem('home_intro', $this->hasConfiguredHomeIntro($homeCustomization, $locale), 'app_dashboard_customization_home', ['customization_panel' => 'texts']),
            $this->buildItem('site_meta_description', $this->hasConfiguredSiteMetaDescription($locale), 'app_dashboard_configuration_site', []),
        ];

        $completedCount = count(array_filter($items, static fn (array $item): bool => $item['done']));

        return [
            'isComplete' => $completedCount === count($items),
            'completedCount' => $completedCount,
            'totalCount' => count($items),
            'items' => $items,
        ];
    }

    /**
     * @param array<string, mixed> $payload Decoded CvProfile JSON.
     */
    private function hasConfiguredCvIdentity(array $payload): bool
    {
        $identity = $payload[CvPublicIdentityContract::KEY_ROOT] ?? null;
        if (!is_array($identity)) {
            return false;
        }

        $displayName = $identity[CvPublicIdentityContract::FIELD_DISPLAY_NAME] ?? null;

        return is_string($displayName) && trim($displayName) !== '';
    }

    /**
     * @param array<string, mixed> $payload Decoded CvProfile JSON.
     */
    private function hasConfiguredExperience(array $payload): bool
    {
        if (!ExperienceContract::hasPersistedExperienceMap($payload)) {
            return false;
        }

        foreach (ExperienceContract::entriesByLocaleFromStoredPayload($payload) as $rows) {
            if ($rows !== []) {
                return true;
            }
        }

        return false;
    }

    private function hasConfiguredHomeSignature(?HomeCustomization $customization): bool
    {
        if (!$customization instanceof HomeCustomization) {
            return false;
        }

        return $this->homeCustomizationService->hasUserSignatureUpload(
            $customization->getSignatureImageRelativePath()
        );
    }

    private function hasConfiguredHomeIntro(?HomeCustomization $customization, string $locale): bool
    {
        if (!$customization instanceof HomeCustomization) {
            return false;
        }

        $intro = trim(strip_tags($this->homeCustomizationService->resolveIntroText($locale, $customization)));

        return $intro !== '';
    }

    private function hasConfiguredSiteMetaDescription(string $locale): bool
    {
        return $this->homeCustomizationService->resolveMetaDescriptionForLocale($locale) !== '';
    }

    /**
     * @param string $key Checklist item key.
     * @param bool $done Whether the step is complete.
     * @param string $route Symfony route name.
     * @param array<string, mixed> $routeParams Route parameters.
     * @return array{key: string, done: bool, route: string, routeParams: array<string, mixed>}
     * @date 2026-06-21
     * @author Stephane H.
     */
    private function buildItem(string $key, bool $done, string $route, array $routeParams): array
    {
        return [
            'key' => $key,
            'done' => $done,
            'route' => $route,
            'routeParams' => $routeParams,
        ];
    }
}
