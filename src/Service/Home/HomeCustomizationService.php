<?php

namespace App\Service\Home;

use App\Cv\SiteColorsContract;
use App\Service\Customization\CustomizationAssetScope;
use App\Entity\HomeCustomization;
use App\Entity\HomeCustomizationTranslation;
use App\Repository\HomeCustomizationRepository;
use App\Service\Locale\LocaleConfigurationService;
use App\Service\Site\SiteSeoResolverService;
use App\Site\SiteSeoContract;
use App\Service\RichText\RichHtmlSanitizer;
use App\Service\Security\CssSanitizerService;
use App\Service\Util\ImageReencoder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

/**
 * Application service for global home landing customization.
 */
class HomeCustomizationService
{
    public const UPLOAD_SUBDIRECTORY = CustomizationAssetScope::HOME_CUSTOM_UPLOAD_ROOT;

    public const DEFAULT_DASHBOARD_TILE_ICON_PATH = 'images/home/dashboard.svg';

    public const FAVICON_CUSTOM_UPLOAD_ROOT = CustomizationAssetScope::FAVICON_CUSTOM_UPLOAD_ROOT;

    public const OG_IMAGE_CUSTOM_UPLOAD_ROOT = 'images/home/custom/og';

    public const DEFAULT_SITE_FAVICON_PATH = 'favicon/favicon.svg';

    public const DEFAULT_SIGNATURE_PATH = 'images/home/generic-signature.webp';

    public const DEFAULT_BACKGROUND_PATH = 'images/home/start.webp';

    /** @var list<string> Deprecated stored home image paths treated as unset. */
    public const DEPRECATED_HOME_IMAGE_PATHS = [
        'images/home/hirt-stephane.webp',
    ];

    /**
     * @var list<string> Legacy cv-symfony8 seeded intro copies replaced by generic UI defaults.
     */
    public const DEPRECATED_LEGACY_INTRO_TEXTS = [
        'Site internet de HIRT Stéphane, Développeur, photographe amateur, blogueur.',
        'HIRT Stephane website, Developer, amateur photographer, blogger.',
        'Webseite von HIRT Stephane, Entwickler, Amateurfotograf, Blogger.',
        'HIRT Stephane interneto svetaine, programuotojas, megejas fotografas, tinklarastininkas.',
        'Nettsiden til HIRT Stephane, utvikler, amatørfotograf, blogger.',
    ];

    private const TILE_ICON_ROLE_DASHBOARD = 'dashboard';

    /**
     * @brief Allowed upload extensions for signature or background images.
     * @var array<int, string>
     */
    private const ALLOWED_IMAGE_EXTENSIONS = ['webp', 'jpg', 'jpeg', 'png', 'gif'];

    /**
     * @brief Allowed upload extensions for quick tile icons.
     * @var array<int, string>
     */
    private const ALLOWED_TILE_ICON_EXTENSIONS = ['webp', 'svg'];

    /**
     * @brief Allowed upload extensions for site favicon.
     *
     * @var array<int, string>
     */
    private const ALLOWED_SITE_FAVICON_EXTENSIONS = ['svg', 'png', 'webp'];

    /**
     * @brief Allowed upload extensions for dedicated Open Graph images.
     *
     * @var array<int, string>
     */
    private const ALLOWED_OPEN_GRAPH_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    /**
     * @brief Raw CSS declarations matching legacy home-landing title styles.
     */
    private const SEED_INTRO_TITLE_CSS = <<<'CSS'
margin: 0;
font-size: clamp(1.8rem, 5vw, 2.7rem);
font-weight: 700;
letter-spacing: 0.02em;
text-shadow: 0 0.3rem 1rem rgba(0, 0, 0, 0.5);
CSS;

    private const SEED_WEBCV_CSS = <<<'CSS'
margin-top: 1.4rem;
border-radius: 999px;
padding: 0.72rem 2.1rem;
font-weight: 700;
letter-spacing: 0.08em;
text-transform: uppercase;
border: 2px solid rgba(255, 255, 255, 0.88);
color: #ffffff;
background: rgba(255, 255, 255, 0.08);
backdrop-filter: blur(4px);
-webkit-backdrop-filter: blur(4px);
box-shadow: 0 0.45rem 1.3rem rgba(0, 0, 0, 0.25);
transition: all 180ms ease-in-out;
CSS;

    private const SEED_WEBCV_HOVER_CSS = <<<'CSS'
color: #0f172a;
background: rgba(255, 255, 255, 0.96);
border-color: rgba(255, 255, 255, 1);
transform: translateY(-1px);
CSS;

    private const PLACEHOLDER_INTRO_TITLE_CSS = <<<'CSS'
margin: 0;
font-size: clamp(1.4rem, 4vw, 2rem);
font-weight: 600;
CSS;

    private const PLACEHOLDER_WEBCV_CSS = <<<'CSS'
margin-top: 1rem;
border-radius: 999px;
padding: 0.6rem 1.5rem;
CSS;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly HomeCustomizationRepository $repository,
        private readonly CssSanitizerService $cssSanitizer,
        private readonly LocaleConfigurationService $localeConfigurationService,
        private readonly RichHtmlSanitizer $richHtmlSanitizer,
        private readonly HomeQuickTilePresetRegistry $quickTilePresetRegistry,
        private readonly ImageReencoder $imageReencoder,
        private readonly string $projectDir,
    ) {
    }

    /**
     * @brief Return singleton customization row, creating seeded defaults when missing.
     * @return HomeCustomization
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function getOrCreateSingleton(): HomeCustomization
    {
        $existing = $this->repository->getSingleton();
        if ($existing instanceof HomeCustomization) {
            return $existing;
        }

        $entity = new HomeCustomization();
        $entity->setSignatureImageRelativePath(null);
        $entity->setBackgroundImageRelativePath(self::DEFAULT_BACKGROUND_PATH);
        $entity->setIntroTitleCssSanitized($this->cssSanitizer->sanitizeDeclarationBlock(self::SEED_INTRO_TITLE_CSS));
        $entity->setWebcvButtonCssSanitized($this->cssSanitizer->sanitizeDeclarationBlock(self::SEED_WEBCV_CSS));
        $entity->setWebcvButtonCssHoverSanitized($this->cssSanitizer->sanitizeDeclarationBlock(self::SEED_WEBCV_HOVER_CSS));
        $entity->setQuickTileStyle('style_1');
        $entity->setQuickTileCssSanitized(null);
        $entity->setSiteColorsJson(SiteColorsContract::encodeForStorage([
            'accent' => SiteColorsContract::DEFAULT_ACCENT_HEX,
            'cvMenuBackground' => null,
        ]));

        $localeConfiguration = $this->localeConfigurationService->getConfiguration();
        /** @var list<string> $activeLocales */
        $activeLocales = is_array($localeConfiguration['activeLocales'] ?? null) ? $localeConfiguration['activeLocales'] : ['fr'];

        foreach ($activeLocales as $localeCode) {
            if (!is_string($localeCode) || trim($localeCode) === '') {
                continue;
            }

            $translation = new HomeCustomizationTranslation();
            $translation->setLocale($localeCode);
            $translation->setIntroText('');
            $entity->addTranslation($translation);
        }

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return $entity;
    }

    /**
     * @brief Create a minimal home customization row after CV reset.
     *
     * @return HomeCustomization Persisted placeholder singleton.
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function createPlaceholderSingleton(): HomeCustomization
    {
        $localeConfiguration = $this->localeConfigurationService->getConfiguration();
        /** @var list<string> $activeLocales */
        $activeLocales = is_array($localeConfiguration['activeLocales'] ?? null) ? $localeConfiguration['activeLocales'] : [];

        $entity = new HomeCustomization();
        $entity->setSignatureImageRelativePath(null);
        $entity->setBackgroundImageRelativePath(null);
        $entity->setSignatureCssSanitized(null);
        $entity->setIntroTitleCssSanitized($this->cssSanitizer->sanitizeDeclarationBlock(self::PLACEHOLDER_INTRO_TITLE_CSS));
        $entity->setWebcvButtonCssSanitized($this->cssSanitizer->sanitizeDeclarationBlock(self::PLACEHOLDER_WEBCV_CSS));
        $entity->setWebcvButtonCssHoverSanitized(null);
        $entity->setQuickTileStyle('style_1');
        $entity->setQuickTileCssSanitized(null);
        $entity->setDashboardTileIconRelativePath(null);
        $entity->setSiteFaviconRelativePath(null);
        $entity->setSiteColorsJson(SiteColorsContract::encodeForStorage([
            'accent' => SiteColorsContract::DEFAULT_ACCENT_HEX,
            'cvMenuBackground' => null,
        ]));

        foreach ($activeLocales as $localeCode) {
            if (!is_string($localeCode) || trim($localeCode) === '') {
                continue;
            }

            $translation = new HomeCustomizationTranslation();
            $translation->setLocale($localeCode);
            $translation->setIntroText('');
            $entity->addTranslation($translation);
        }

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return $entity;
    }

    /**
     * @brief Produce merged stylesheet content for public landing overrides.
     * @param HomeCustomization $customization Current customization entity.
     * @return string
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function buildStylesheetCss(HomeCustomization $customization): string
    {
        $sections = [];
        $bgBlock = $this->buildBackgroundCssBlock($customization);
        if ($bgBlock !== '') {
            $sections[] = $bgBlock;
        }

        $signatureCss = (string) ($customization->getSignatureCssSanitized() ?? '');
        if ($signatureCss !== '') {
            $sections[] = ".home-landing__signature {\n".$signatureCss."\n}";
        }

        $intro = (string) ($customization->getIntroTitleCssSanitized() ?? '');
        if ($intro !== '') {
            $sections[] = ".home-custom-intro {\n".$intro."\n}";
        }

        $webcv = (string) ($customization->getWebcvButtonCssSanitized() ?? '');
        if ($webcv !== '') {
            $sections[] = ".home-custom-webcv {\n".$webcv."\n}";
        }

        $webcvHover = (string) ($customization->getWebcvButtonCssHoverSanitized() ?? '');
        if ($webcvHover !== '') {
            $sections[] = ".home-custom-webcv:hover,\n.home-custom-webcv:focus,\n.home-custom-webcv:focus-visible {\n".$webcvHover."\n}";
        }

        $quickTileCss = $this->resolveQuickTileCss($customization);
        if ($quickTileCss !== '') {
            $sections[] = ".home-custom-quick-tile.button-icon {\n".$quickTileCss."\n}";
        }

        $sections[] = <<<'CSS'
.button-icon.home-quick-tile-add {
    background: transparent !important;
    background-image: none !important;
    background-color: transparent !important;
    border: none !important;
    box-shadow: none !important;
}
CSS;

        $fullCss = implode("\n\n", $sections);

        return $fullCss;
    }

    /**
     * @brief Resolve intro text for the active request locale with HTML sanitization.
     * @param string $requestLocale Current request locale code.
     * @param HomeCustomization $customization Customization aggregate.
     * @return string Resolved intro HTML or empty string.
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function resolveIntroText(string $requestLocale, HomeCustomization $customization): string
    {
        $localeConfiguration = $this->localeConfigurationService->getConfiguration();
        /** @var array<int, string> $activeLocales */
        $activeLocales = is_array($localeConfiguration['activeLocales'] ?? null) ? $localeConfiguration['activeLocales'] : [];
        $defaultLocale = is_string($localeConfiguration['defaultLocale'] ?? null) ? $localeConfiguration['defaultLocale'] : 'en';

        $candidates = [$requestLocale, $defaultLocale];
        foreach ($activeLocales as $activeLocale) {
            $candidates[] = $activeLocale;
        }

        foreach ($candidates as $candidate) {
            $normalized = strtolower(trim($candidate));
            if ($normalized === '') {
                continue;
            }

            foreach ($customization->getTranslations() as $translation) {
                if (strtolower($translation->getLocale()) !== $normalized) {
                    continue;
                }

                $text = self::normalizeStoredIntroText(trim($translation->getIntroText()));
                if ($text !== '') {
                    return $this->richHtmlSanitizer->sanitize($text);
                }
            }
        }

        return '';
    }

    /**
     * @brief Resolve intro text for one locale in admin forms, ignoring legacy seeded copies.
     *
     * @param HomeCustomization $customization Customization aggregate.
     * @param string $locale Locale code.
     * @return string Sanitized intro HTML or empty string.
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function resolveIntroTextForLocale(HomeCustomization $customization, string $locale): string
    {
        foreach ($customization->getTranslations() as $translation) {
            if (strtolower($translation->getLocale()) !== strtolower(trim($locale))) {
                continue;
            }

            $text = self::normalizeStoredIntroText(trim($translation->getIntroText()));
            if ($text === '') {
                return '';
            }

            return $this->richHtmlSanitizer->sanitize($text);
        }

        return '';
    }

    /**
     * @brief Resolve configured SEO meta description for one locale.
     *
     * @param string $locale Locale code.
     * @return string Plain-text meta description or empty string when unset.
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function resolveMetaDescriptionForLocale(string $locale): string
    {
        $customization = $this->repository->getSingleton();
        if (!$customization instanceof HomeCustomization) {
            return '';
        }

        return trim($customization->getMetaDescriptionForLocale($locale));
    }

    /**
     * @brief Persist per-locale SEO meta descriptions from site configuration admin POST.
     *
     * @param Request $request Admin site configuration request.
     * @param list<string> $activeLocales Active locale codes.
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function applySiteSeoMetaDescriptionsFromAdminRequest(Request $request, array $activeLocales): void
    {
        $customization = $this->getOrCreateSingleton();
        $submitted = $request->request->all(SiteSeoContract::REQUEST_FIELD_META_DESCRIPTION);
        if (!is_array($submitted)) {
            $submitted = [];
        }

        foreach ($activeLocales as $localeCode) {
            if (!is_string($localeCode) || trim($localeCode) === '') {
                continue;
            }

            $rawValue = $submitted[$localeCode] ?? '';
            $customization->setMetaDescriptionForLocale(
                $localeCode,
                SiteSeoResolverService::normalizeMetaDescription($rawValue)
            );
        }

        $this->entityManager->persist($customization);
    }

    /**
     * @brief Drop legacy cv-symfony8 intro seed copies so generic fallbacks can apply.
     *
     * @param string $introText Raw intro HTML or plain text from persistence.
     * @return string Empty string when legacy copy is detected, otherwise unchanged input.
     * @date 2026-06-21
     * @author Stephane H.
     */
    public static function normalizeStoredIntroText(string $introText): string
    {
        return self::isDeprecatedLegacyIntroText($introText) ? '' : $introText;
    }

    /**
     * @brief Detect legacy cv-symfony8 home intro seed copies.
     *
     * @param string $introText Raw intro HTML or plain text.
     * @return bool True when the value matches a deprecated legacy seed copy.
     * @date 2026-06-21
     * @author Stephane H.
     */
    public static function isDeprecatedLegacyIntroText(string $introText): bool
    {
        $normalized = mb_strtolower(trim(html_entity_decode(strip_tags($introText), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        if ($normalized === '') {
            return false;
        }

        foreach (self::DEPRECATED_LEGACY_INTRO_TEXTS as $deprecatedCopy) {
            if ($normalized === mb_strtolower(trim($deprecatedCopy))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @brief Resolve public site favicon path for Twig layouts.
     *
     * @return string Relative path under public/ (custom upload or system default).
     * @date 2026-05-18
     * @author Stephane H.
     */
    public function resolveSiteFaviconRelativePath(): string
    {
        $customization = $this->repository->getSingleton();
        if ($customization instanceof HomeCustomization) {
            $stored = $customization->getSiteFaviconRelativePath();
            if (is_string($stored) && $stored !== '' && $this->isPublicAssetFile($stored)) {
                return $stored;
            }
        }

        return self::DEFAULT_SITE_FAVICON_PATH;
    }

    /**
     * @brief Resolve MIME type for the active site favicon asset.
     *
     * @return string MIME type string for the link rel icon type attribute.
     * @date 2026-05-18
     * @author Stephane H.
     */
    public function resolveSiteFaviconMimeType(): string
    {
        $extension = strtolower(pathinfo($this->resolveSiteFaviconRelativePath(), PATHINFO_EXTENSION));

        return match ($extension) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/svg+xml',
        };
    }

    /**
     * @brief Resolve dedicated Open Graph image path when configured in site identity admin.
     *
     * @return string|null Relative path under public/ suitable for asset().
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function resolveOpenGraphImageRelativePath(): ?string
    {
        $customization = $this->repository->getSingleton();
        if (!$customization instanceof HomeCustomization) {
            return null;
        }

        $stored = $customization->getOpenGraphImageRelativePath();
        if (is_string($stored) && $stored !== '' && $this->isPublicAssetFile($stored)) {
            return $stored;
        }

        return null;
    }

    /**
     * @brief Whether the given share image path is the dedicated Open Graph upload.
     *
     * @param string|null $relativePath Candidate relative public asset path.
     * @return bool True when the path matches the stored OG image.
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function isDedicatedOpenGraphImage(?string $relativePath): bool
    {
        if (!is_string($relativePath) || $relativePath === '') {
            return false;
        }

        $customization = $this->repository->getSingleton();
        if (!$customization instanceof HomeCustomization) {
            return false;
        }

        return $relativePath === $customization->getOpenGraphImageRelativePath();
    }

    /**
     * @brief Whether a share image meets Open Graph large-card minimum dimensions.
     *
     * @param string|null $relativePath Relative public asset path.
     * @return bool True when dedicated OG image is set or raster dimensions are large enough.
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function isLargeFormatShareImage(?string $relativePath): bool
    {
        if (!is_string($relativePath) || $relativePath === '') {
            return false;
        }

        if ($this->isDedicatedOpenGraphImage($relativePath)) {
            return true;
        }

        $absolutePath = rtrim($this->projectDir, '/').'/public/'.$relativePath;
        if (!is_file($absolutePath)) {
            return false;
        }

        $dimensions = @getimagesize($absolutePath);
        if (!is_array($dimensions)) {
            return false;
        }

        return ($dimensions[0] ?? 0) >= SiteSeoContract::OPEN_GRAPH_IMAGE_MIN_WIDTH
            && ($dimensions[1] ?? 0) >= SiteSeoContract::OPEN_GRAPH_IMAGE_MIN_HEIGHT;
    }

    /**
     * @brief Persist customization updates from the admin home form.
     * @param Request $request HTTP request with POST payload.
     * @param array<int, string> $activeLocales Active locales allowed for editing.
     * @return void
     * @date 2026-06-09
     * @author Stephane H.
     */
    public function saveFromAdminRequest(Request $request, array $activeLocales): void
    {
        $customization = $this->getOrCreateSingleton();

        if ($this->isTruthyRequestFlag($request, 'reset_background')) {
            $this->deleteCustomUploadIfNeeded($customization->getBackgroundImageRelativePath());
            $customization->setBackgroundImageRelativePath(self::DEFAULT_BACKGROUND_PATH);
        } else {
            $backgroundUpload = $request->files->get('background_upload');
            if ($backgroundUpload instanceof UploadedFile && $backgroundUpload->isValid()) {
                $relative = $this->storeUploadedImage($backgroundUpload, $customization->getBackgroundImageRelativePath());
                $customization->setBackgroundImageRelativePath($relative);
            }
        }

        if ($this->isTruthyRequestFlag($request, 'reset_signature')) {
            $this->deleteCustomUploadIfNeeded($customization->getSignatureImageRelativePath());
            $customization->setSignatureImageRelativePath(null);
        } else {
            $signatureUpload = $request->files->get('signature_upload');
            if ($signatureUpload instanceof UploadedFile && $signatureUpload->isValid()) {
                $relative = $this->storeUploadedImage($signatureUpload, $customization->getSignatureImageRelativePath());
                $customization->setSignatureImageRelativePath($relative);
            }
        }

        $customization->setBackgroundCssSanitized($this->cssSanitizer->sanitizeDeclarationBlock((string) $request->request->get('background_css', '')));
        $customization->setSignatureCssSanitized($this->cssSanitizer->sanitizeDeclarationBlock((string) $request->request->get('signature_css', '')));
        $customization->setIntroTitleCssSanitized($this->cssSanitizer->sanitizeDeclarationBlock((string) $request->request->get('intro_title_css', '')));
        $customization->setWebcvButtonCssSanitized($this->cssSanitizer->sanitizeDeclarationBlock((string) $request->request->get('webcv_button_css', '')));
        $customization->setWebcvButtonCssHoverSanitized($this->cssSanitizer->sanitizeDeclarationBlock((string) $request->request->get('webcv_button_css_hover', '')));

        $quickTileStyle = trim((string) $request->request->get('quick_tile_style', 'style_1'));
        if (!$this->quickTilePresetRegistry->isValidStyle($quickTileStyle)) {
            $quickTileStyle = 'style_1';
        }
        $customization->setQuickTileStyle($quickTileStyle);
        if ($quickTileStyle === HomeQuickTilePresetRegistry::STYLE_CUSTOM) {
            $customization->setQuickTileCssSanitized($this->cssSanitizer->sanitizeDeclarationBlock((string) $request->request->get('quick_tile_css', '')));
        } else {
            $customization->setQuickTileCssSanitized(null);
        }

        if ($this->isTruthyRequestFlag($request, 'reset_dashboard_tile_icon')) {
            $this->deleteCustomUploadIfNeeded($customization->getDashboardTileIconRelativePath());
            $customization->setDashboardTileIconRelativePath(null);
        } else {
            $dashboardIconUpload = $request->files->get('dashboard_tile_icon_upload');
            if ($dashboardIconUpload instanceof UploadedFile && $dashboardIconUpload->isValid()) {
                $relative = $this->storeTileIconUpload($dashboardIconUpload, self::TILE_ICON_ROLE_DASHBOARD, $customization->getDashboardTileIconRelativePath());
                $customization->setDashboardTileIconRelativePath($relative);
            }
        }

        /** @var array<string, mixed>|null $introPayload */
        $introPayload = $request->request->all('intro_text');
        if (is_array($introPayload)) {
            foreach ($activeLocales as $localeCode) {
                if (!isset($introPayload[$localeCode]) || !is_string($introPayload[$localeCode])) {
                    continue;
                }

                $translation = $this->findTranslation($customization, $localeCode);
                if (!$translation instanceof HomeCustomizationTranslation) {
                    $translation = new HomeCustomizationTranslation();
                    $translation->setLocale($localeCode);
                    $customization->addTranslation($translation);
                }

                $rawIntro = trim($introPayload[$localeCode]);
                $translation->setIntroText($this->richHtmlSanitizer->sanitize($rawIntro));
            }
        }

        $this->entityManager->persist($customization);
        $this->entityManager->flush();
    }

    /**
     * @brief Apply site favicon upload or reset from admin site configuration form.
     *
     * @param Request $request Admin POST request.
     * @param HomeCustomization $customization Singleton customization row.
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function applySiteFaviconFromAdminRequest(Request $request, HomeCustomization $customization): void
    {
        if ($this->isTruthyRequestFlag($request, 'reset_site_favicon')) {
            $this->deleteCustomUploadIfNeeded($customization->getSiteFaviconRelativePath());
            $customization->setSiteFaviconRelativePath(null);
        } else {
            $siteFaviconUpload = $request->files->get('site_favicon_upload');
            if ($siteFaviconUpload instanceof UploadedFile && $siteFaviconUpload->isValid()) {
                $relative = $this->storeSiteFaviconUpload($siteFaviconUpload, $customization->getSiteFaviconRelativePath());
                $customization->setSiteFaviconRelativePath($relative);
            }
        }
    }

    /**
     * @brief Apply dedicated Open Graph image upload or reset from admin site configuration form.
     *
     * @param Request $request Admin POST request.
     * @param HomeCustomization $customization Singleton customization row.
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function applyOpenGraphImageFromAdminRequest(Request $request, HomeCustomization $customization): void
    {
        if ($this->isTruthyRequestFlag($request, 'reset_open_graph_image')) {
            $this->deleteCustomUploadIfNeeded($customization->getOpenGraphImageRelativePath());
            $customization->setOpenGraphImageRelativePath(null);
        } else {
            $openGraphUpload = $request->files->get('open_graph_image_upload');
            if ($openGraphUpload instanceof UploadedFile && $openGraphUpload->isValid()) {
                $relative = $this->storeOpenGraphImageUpload(
                    $openGraphUpload,
                    $customization->getOpenGraphImageRelativePath(),
                );
                $customization->setOpenGraphImageRelativePath($relative);
            }
        }
    }

    /**
     * @brief Resolve public signature/photo path for the home landing page.
     *
     * @param HomeCustomization $customization Customization aggregate.
     * @param bool $includeDefaultAsset When false, bundled generic signature is treated as unset.
     * @return string|null Relative path under public/ when a user-facing asset exists.
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function resolveSignatureImageRelativePath(
        HomeCustomization $customization,
        bool $includeDefaultAsset = true,
    ): ?string {
        return $this->resolveHomeImageRelativePath(
            $customization->getSignatureImageRelativePath(),
            self::DEFAULT_SIGNATURE_PATH,
            $includeDefaultAsset,
        );
    }

    /**
     * @brief Whether the home landing uses a user-uploaded signature or photo.
     *
     * @param string|null $storedPath Raw signature path from HomeCustomization.
     * @return bool True when the path points to a custom upload.
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function hasUserSignatureUpload(?string $storedPath): bool
    {
        if (!is_string($storedPath) || trim($storedPath) === '') {
            return false;
        }

        if (in_array($storedPath, self::DEPRECATED_HOME_IMAGE_PATHS, true)) {
            return false;
        }

        if ($storedPath === self::DEFAULT_SIGNATURE_PATH) {
            return false;
        }

        return $this->isPublicAssetFile($storedPath);
    }

    /**
     * @brief Resolve public background image path for the home landing page.
     *
     * @param HomeCustomization $customization Customization aggregate.
     * @return string|null Relative path under public/ when a default or user upload exists.
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function resolveBackgroundImageRelativePath(HomeCustomization $customization): ?string
    {
        return $this->resolveHomeImageRelativePath(
            $customization->getBackgroundImageRelativePath(),
            self::DEFAULT_BACKGROUND_PATH
        );
    }

    /**
     * @brief Build CSS block for landing background container.
     * @param HomeCustomization $customization Customization entity.
     * @return string CSS rules or empty string.
     * @date 2026-05-17
     * @author Stephane H.
     */
    private function buildBackgroundCssBlock(HomeCustomization $customization): string
    {
        $declarations = [];
        $backgroundCss = (string) ($customization->getBackgroundCssSanitized() ?? '');
        if ($backgroundCss !== '') {
            $declarations[] = $backgroundCss;
        }

        $bgPath = $this->resolveBackgroundImageRelativePath($customization);
        if (is_string($bgPath) && $bgPath !== '') {
            $safeUrl = $this->escapeCssUrlPath($bgPath);
            if ($safeUrl !== '') {
                $declarations[] = "background-image: url('".$safeUrl."');\n    background-size: cover;\n    background-position: center;\n    background-repeat: no-repeat;";
            }
        }

        if ($declarations === []) {
            return '';
        }

        return ".home-landing.home-custom-bg {\n".implode("\n    ", $declarations)."\n}";
    }

    /**
     * @brief Resolve quick tile CSS from preset or custom field.
     * @param HomeCustomization $customization Customization entity.
     * @return string Sanitized declaration block.
     * @date 2026-05-17
     * @author Stephane H.
     */
    private function resolveQuickTileCss(HomeCustomization $customization): string
    {
        $style = $customization->getQuickTileStyle();
        if ($style === HomeQuickTilePresetRegistry::STYLE_CUSTOM) {
            return (string) ($customization->getQuickTileCssSanitized() ?? '');
        }

        if (!in_array($style, HomeQuickTilePresetRegistry::PRESET_STYLES, true)) {
            $style = 'style_1';
        }

        $rawPreset = $this->quickTilePresetRegistry->getPresetCss($style);
        $sanitized = $this->cssSanitizer->sanitizeDeclarationBlock($rawPreset);

        return $sanitized;
    }

    private function findTranslation(HomeCustomization $customization, string $locale): ?HomeCustomizationTranslation
    {
        foreach ($customization->getTranslations() as $translation) {
            if (strtolower($translation->getLocale()) === strtolower($locale)) {
                return $translation;
            }
        }

        return null;
    }

    /**
     * @brief Persist uploaded signature or background image under custom directory.
     * @param UploadedFile $upload Uploaded binary.
     * @param string|null $previousRelativePath Previous relative path for cleanup.
     * @return string New relative asset path.
     * @date 2026-05-17
     * @author Stephane H.
     */
    private function storeUploadedImage(UploadedFile $upload, ?string $previousRelativePath): string
    {
        $extension = strtolower((string) $upload->guessExtension());
        if (!in_array($extension, self::ALLOWED_IMAGE_EXTENSIONS, true)) {
            throw new \InvalidArgumentException('dashboard.customization_home.flash.invalid_image');
        }

        return $this->storeFileInCustomDirectory($upload, $previousRelativePath, bin2hex(random_bytes(16)).'.'.$extension);
    }

    /**
     * @brief Persist uploaded quick tile icon (SVG or WebP only).
     * @param UploadedFile $upload Uploaded binary.
     * @param string $role Tile role key (dashboard).
     * @param string|null $previousRelativePath Previous relative path for cleanup.
     * @return string New relative asset path.
     * @date 2026-05-17
     * @author Stephane H.
     */
    private function storeTileIconUpload(UploadedFile $upload, string $role, ?string $previousRelativePath): string
    {
        $extension = strtolower((string) $upload->guessExtension());
        if (!in_array($extension, self::ALLOWED_TILE_ICON_EXTENSIONS, true)) {
            throw new \InvalidArgumentException('dashboard.customization_home.flash.invalid_tile_icon');
        }

        $mimeType = strtolower((string) $upload->getMimeType());
        $allowedMimes = ['image/webp', 'image/svg+xml'];
        if (!in_array($mimeType, $allowedMimes, true)) {
            throw new \InvalidArgumentException('dashboard.customization_home.flash.invalid_tile_icon');
        }

        $filename = sprintf('quick-tile-%s-%s.%s', $role, bin2hex(random_bytes(8)), $extension);

        return $this->storeFileInPurgeableRoot($upload, $previousRelativePath, $filename, self::UPLOAD_SUBDIRECTORY);
    }

    /**
     * @brief Persist uploaded site favicon under the favicon custom directory.
     *
     * @param UploadedFile $upload Uploaded binary.
     * @param string|null $previousRelativePath Previous relative path for cleanup.
     * @return string New relative asset path.
     * @date 2026-05-18
     * @author Stephane H.
     */
    private function storeSiteFaviconUpload(UploadedFile $upload, ?string $previousRelativePath): string
    {
        $extension = strtolower((string) $upload->guessExtension());
        if (!in_array($extension, self::ALLOWED_SITE_FAVICON_EXTENSIONS, true)) {
            throw new \InvalidArgumentException('dashboard.customization_home.flash.invalid_site_favicon');
        }

        $mimeType = strtolower((string) $upload->getMimeType());
        $allowedMimes = ['image/svg+xml', 'image/png', 'image/webp'];
        if (!in_array($mimeType, $allowedMimes, true)) {
            throw new \InvalidArgumentException('dashboard.customization_home.flash.invalid_site_favicon');
        }

        $filename = 'site-favicon-'.bin2hex(random_bytes(8)).'.'.$extension;

        return $this->storeFileInPurgeableRoot($upload, $previousRelativePath, $filename, self::FAVICON_CUSTOM_UPLOAD_ROOT);
    }

    /**
     * @brief Persist uploaded Open Graph image under the OG custom directory.
     *
     * @param UploadedFile $upload Uploaded binary.
     * @param string|null $previousRelativePath Previous relative path for cleanup.
     * @return string New relative asset path.
     * @date 2026-06-21
     * @author Stephane H.
     */
    private function storeOpenGraphImageUpload(UploadedFile $upload, ?string $previousRelativePath): string
    {
        $extension = strtolower((string) $upload->guessExtension());
        if (!in_array($extension, self::ALLOWED_OPEN_GRAPH_IMAGE_EXTENSIONS, true)) {
            throw new \InvalidArgumentException('dashboard.configuration_site.flash.invalid_open_graph_image');
        }

        $mimeType = strtolower((string) $upload->getMimeType());
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mimeType, $allowedMimes, true)) {
            throw new \InvalidArgumentException('dashboard.configuration_site.flash.invalid_open_graph_image');
        }

        $filename = 'open-graph-'.bin2hex(random_bytes(8)).'.'.$extension;

        return $this->storeFileInPurgeableRoot(
            $upload,
            $previousRelativePath,
            $filename,
            self::OG_IMAGE_CUSTOM_UPLOAD_ROOT,
        );
    }

    /**
     * @brief Move upload into a purgeable public directory and remove previous custom file.
     *
     * @param UploadedFile $upload Uploaded file.
     * @param string|null $previousRelativePath Previous stored path.
     * @param string $filename Target filename inside the upload root.
     * @param string $uploadRoot Directory relative to public/ without trailing slash.
     * @return string Relative path under public/.
     * @date 2026-05-18
     * @author Stephane H.
     */
    private function storeFileInPurgeableRoot(
        UploadedFile $upload,
        ?string $previousRelativePath,
        string $filename,
        string $uploadRoot
    ): string {
        $publicDir = rtrim($this->projectDir, '/').'/public';
        $normalizedRoot = trim($uploadRoot, '/');
        $targetDir = $publicDir.'/'.$normalizedRoot;
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }

        $mimeType = strtolower((string) $upload->getMimeType());
        $absoluteTargetPath = $targetDir.'/'.$filename;
        if ($this->isRasterMimeType($mimeType)) {
            $this->imageReencoder->reencodeToPath($upload, $absoluteTargetPath, $mimeType);
        } else {
            $upload->move($targetDir, $filename);
        }

        $relativePath = $normalizedRoot.'/'.$filename;
        $this->deleteCustomUploadIfNeeded($previousRelativePath);

        return $relativePath;
    }

    /**
     * @brief Move upload into public home custom directory and remove previous custom file.
     * @param UploadedFile $upload Uploaded file.
     * @param string|null $previousRelativePath Previous stored path.
     * @param string $filename Target filename in custom directory.
     * @return string Relative path under public/.
     * @date 2026-05-17
     * @author Stephane H.
     */
    private function storeFileInCustomDirectory(UploadedFile $upload, ?string $previousRelativePath, string $filename): string
    {
        return $this->storeFileInPurgeableRoot($upload, $previousRelativePath, $filename, self::UPLOAD_SUBDIRECTORY);
    }

    /**
     * @brief Delete a previous file when it lives under a purgeable customizable directory.
     * @param string|null $relativePath Relative public path.
     * @return void
     * @date 2026-05-18
     * @author Stephane H.
     */
    private function deleteCustomUploadIfNeeded(?string $relativePath): void
    {
        if (!is_string($relativePath) || $relativePath === '') {
            return;
        }

        if (!CustomizationAssetScope::isPurgeableRelativePath($relativePath)) {
            return;
        }

        $absolutePrevious = rtrim($this->projectDir, '/').'/public/'.$relativePath;
        if (is_file($absolutePrevious)) {
            @unlink($absolutePrevious);
        }
    }

    /**
     * @brief Resolve a stored home image path with fallback to bundled default assets.
     *
     * @param string|null $storedPath Raw path from HomeCustomization.
     * @param string $defaultPath Bundled default asset relative to public/.
     * @return string|null Relative path under public/ or null when no asset is available.
     * @date 2026-06-21
     * @author Stephane H.
     */
    private function resolveHomeImageRelativePath(
        ?string $storedPath,
        string $defaultPath,
        bool $includeDefaultAsset = true,
    ): ?string {
        if (is_string($storedPath) && $storedPath !== '') {
            if (in_array($storedPath, self::DEPRECATED_HOME_IMAGE_PATHS, true)) {
                return $includeDefaultAsset && $this->isPublicAssetFile($defaultPath) ? $defaultPath : null;
            }

            if ($storedPath === $defaultPath && !$includeDefaultAsset) {
                return null;
            }

            if ($this->isPublicAssetFile($storedPath)) {
                return $storedPath;
            }
        }

        return $includeDefaultAsset && $this->isPublicAssetFile($defaultPath) ? $defaultPath : null;
    }

    /**
     * @brief Check whether a relative public asset path exists on disk.
     *
     * @param string $relativePath Path relative to public/.
     * @return bool True when the file is present.
     * @date 2026-05-18
     * @author Stephane H.
     */
    private function isPublicAssetFile(string $relativePath): bool
    {
        $trimmed = trim($relativePath);
        if ($trimmed === '' || str_contains($trimmed, '..')) {
            return false;
        }

        $absolute = rtrim($this->projectDir, '/').'/public/'.ltrim(str_replace('\\', '/', $trimmed), '/');

        return is_file($absolute);
    }

    /**
     * @brief Interpret checkbox-style request flags.
     * @param Request $request HTTP request.
     * @param string $fieldName Request field name.
     * @return bool
     * @date 2026-05-17
     * @author Stephane H.
     */
    private function isTruthyRequestFlag(Request $request, string $fieldName): bool
    {
        $value = $request->request->get($fieldName);

        return in_array($value, [1, '1', true, 'true', 'on', 'yes'], true);
    }

    private function escapeCssUrlPath(string $relativePath): string
    {
        $trimmed = trim($relativePath);
        if ($trimmed === '' || str_contains($trimmed, '..')) {
            return '';
        }

        if (preg_match('#^[a-zA-Z0-9/_\\.-]+$#', $trimmed) !== 1) {
            return '';
        }

        return '/'.ltrim(str_replace('\\', '/', $trimmed), '/');
    }

    /**
     * @brief Whether the MIME type represents a raster image eligible for re-encoding.
     *
     * @param string $mimeType Detected MIME type.
     * @return bool True for JPEG, PNG, or WebP uploads.
     * @date 2026-06-21
     * @author Stephane H.
     */
    private function isRasterMimeType(string $mimeType): bool
    {
        return in_array($mimeType, ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif'], true);
    }
}
