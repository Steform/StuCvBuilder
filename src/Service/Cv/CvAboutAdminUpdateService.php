<?php

declare(strict_types=1);

namespace App\Service\Cv;

use App\Cv\AboutPresentationTypographyContract;
use App\Cv\AboutSectionPatternCustomizationContract;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

/**
 * @brief Apply admin About customization POST fields to a CV content JSON slice.
 */
class CvAboutAdminUpdateService
{
    /**
     * @brief Build About admin update service.
     *
     * @param CvAboutProfileSettingsService $cvAboutProfileSettingsService About settings helper.
     * @param CvAboutPatternTemplateService $cvAboutPatternTemplateService Pattern template storage.
     * @param string $projectDir Symfony project root directory.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function __construct(
        private readonly CvAboutProfileSettingsService $cvAboutProfileSettingsService,
        private readonly CvAboutPatternTemplateService $cvAboutPatternTemplateService,
        private readonly string $projectDir,
    ) {
    }

    /**
     * @brief Apply About admin form submission to a profile or override payload.
     *
     * @param array<string, mixed> $payload Existing About-related JSON slice (mutated logically via return).
     * @param Request $request HTTP request with About form fields and uploads.
     * @param list<string> $activeLocales Active site locales for presentation HTML.
     * @return array{
     *     payload: array<string, mixed>,
     *     flashSuccess: list<string>,
     *     flashWarning: list<string>
     * }
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function applyAboutImagesRequest(array $payload, Request $request, array $activeLocales): array
    {
        $flashSuccess = [];
        $flashWarning = [];

        $existingProfilePhotoPath = $this->cvAboutProfileSettingsService->resolveProfilePhotoDisplayPath(
            $payload['aboutProfilePhotoPath'] ?? null
        );
        $submittedPatternLeftId = $request->request->get('about_section_pattern_template_left');
        $submittedPatternRightId = $request->request->get('about_section_pattern_template_right');

        $presentationValues = $this->parseAboutPresentationUpdate($request, $activeLocales);

        $patternDeleteId = $request->request->get('about_section_pattern_delete');
        if (is_string($patternDeleteId) && trim($patternDeleteId) !== '') {
            $deleted = $this->cvAboutPatternTemplateService->deleteTemplate($patternDeleteId);
            if ($deleted) {
                $flashSuccess[] = 'dashboard.customization_cv.about_section_customization.pattern_delete_success';
            } else {
                $flashWarning[] = 'dashboard.customization_cv.about_section_customization.pattern_delete_forbidden';
            }
        }

        $patternUpload = $request->files->get('about_section_pattern_svg');
        if ($patternUpload instanceof UploadedFile && $patternUpload->isValid()) {
            $uploadedSide = $request->request->get('about_section_pattern_svg_side');
            $uploadResult = $this->storeAboutPatternTemplateUpload(
                $patternUpload,
                $request->request->get('about_section_pattern_svg_name'),
                $uploadedSide
            );
            if ($uploadedSide === 'left') {
                $submittedPatternLeftId = $uploadResult['templateId'];
            } elseif ($uploadedSide === 'right') {
                $submittedPatternRightId = $uploadResult['templateId'];
            }
            foreach ($uploadResult['warnings'] as $warningKey) {
                $flashWarning[] = $warningKey;
            }
        }

        $profilePhotoUpload = $request->files->get('about_profile_photo');
        if ($profilePhotoUpload instanceof UploadedFile && $profilePhotoUpload->isValid()) {
            $newProfilePath = $this->storeAboutImageUpload($profilePhotoUpload, 'profile-photo');
            $this->deleteCustomAboutImageIfNeeded($existingProfilePhotoPath);
            $payload['aboutProfilePhotoPath'] = $newProfilePath;
        }

        $payload[AboutPresentationContract::KEY_HTML_BY_LOCALE] = $presentationValues[AboutPresentationContract::KEY_HTML_BY_LOCALE];
        /** @var array<string, mixed> $toneMixSubmitted */
        $toneMixSubmitted = $request->request->all('about_section_pattern_tone_mix_percent');
        $payload = AboutSectionPatternCustomizationContract::mergeSubmittedIntoPayload(
            $payload,
            $request->request->get('about_section_customization_color'),
            $toneMixSubmitted,
            null,
            $request->request->get('about_section_pattern_dark_surface_darken_percent'),
            $submittedPatternLeftId,
            $submittedPatternRightId,
            $request->request->get('about_section_pattern_template')
        );
        /** @var array<string, mixed> $typographySubmitted */
        $typographySubmitted = $request->request->all('about_presentation_typography');
        $payload = AboutPresentationTypographyContract::mergeSubmittedIntoPayload(
            $payload,
            $typographySubmitted
        );

        $savedPattern = AboutSectionPatternCustomizationContract::fromPayload($payload);
        $patternWarnings = array_values(array_unique(array_merge(
            $this->cvAboutPatternTemplateService->renderTemplate($savedPattern['patternLeftId'] ?? null)['warnings'],
            $this->cvAboutPatternTemplateService->renderTemplate($savedPattern['patternRightId'] ?? null)['warnings']
        )));
        foreach ($patternWarnings as $warningKey) {
            $flashWarning[] = $warningKey;
        }

        return [
            'payload' => $payload,
            'flashSuccess' => $flashSuccess,
            'flashWarning' => $flashWarning,
        ];
    }

    /**
     * @brief Parse About presentation HTML per locale.
     *
     * @param Request $request HTTP request.
     * @param list<string> $activeLocales Allowed locale codes.
     * @return array<string, array<string, string>>
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function parseAboutPresentationUpdate(Request $request, array $activeLocales): array
    {
        $submitted = $request->request->all('about_presentation_html');
        if (!is_array($submitted)) {
            $submitted = [];
        }

        $htmlByLocale = [];
        foreach ($activeLocales as $locale) {
            $raw = $submitted[$locale] ?? '';
            $rawStr = is_string($raw) ? $raw : '';
            $htmlByLocale[$locale] = $this->cvAboutProfileSettingsService->normalizePresentationHtmlForStorage(
                $rawStr,
                $locale
            );
        }

        return [
            AboutPresentationContract::KEY_HTML_BY_LOCALE => $htmlByLocale,
        ];
    }

    /**
     * @brief Store uploaded About section SVG pattern after validation.
     *
     * @param UploadedFile $uploadedFile Uploaded SVG file.
     * @param mixed $displayNameSubmitted Raw display name input.
     * @param mixed $sideSubmitted Raw side input.
     * @return array{templateId: string, warnings: list<string>}
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function storeAboutPatternTemplateUpload(
        UploadedFile $uploadedFile,
        mixed $displayNameSubmitted = null,
        mixed $sideSubmitted = null,
    ): array {
        $mimeType = strtolower((string) $uploadedFile->getMimeType());
        $allowedMimeTypes = ['image/svg+xml', 'text/plain', 'application/xml', 'text/xml'];
        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            throw new \InvalidArgumentException('dashboard.customization_cv.about_section_customization.pattern_upload_invalid_type');
        }

        $extension = strtolower((string) ($uploadedFile->guessExtension() ?: $uploadedFile->getClientOriginalExtension() ?: ''));
        if ($extension !== 'svg') {
            throw new \InvalidArgumentException('dashboard.customization_cv.about_section_customization.pattern_upload_invalid_type');
        }

        $svg = file_get_contents($uploadedFile->getPathname());
        if (!is_string($svg) || trim($svg) === '') {
            throw new \InvalidArgumentException('dashboard.customization_cv.about_section_customization.pattern_upload_invalid_type');
        }

        $displayName = is_string($displayNameSubmitted) ? trim($displayNameSubmitted) : '';
        if ($displayName === '') {
            $originalFilename = pathinfo((string) $uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
            $displayName = is_string($originalFilename) ? trim($originalFilename) : '';
        }
        if ($displayName === '') {
            $displayName = null;
        }
        $side = is_string($sideSubmitted) ? $sideSubmitted : null;
        $templateId = $this->cvAboutPatternTemplateService->storeUploadedTemplate($svg, $displayName, $side);

        return [
            'templateId' => $templateId,
            'warnings' => [],
        ];
    }

    /**
     * @brief Persist uploaded about image in custom directory.
     *
     * @param UploadedFile $uploadedFile Uploaded image.
     * @param string $suffixName Filename suffix token.
     * @return string Relative public path.
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function storeAboutImageUpload(UploadedFile $uploadedFile, string $suffixName): string
    {
        $allowedMimeTypes = ['image/webp', 'image/png', 'image/jpeg'];
        $mimeType = (string) $uploadedFile->getMimeType();
        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            throw new \InvalidArgumentException('dashboard.customization_cv.flash.invalid_image');
        }

        $extension = strtolower((string) ($uploadedFile->guessExtension() ?: $uploadedFile->getClientOriginalExtension() ?: ''));
        if (!in_array($extension, ['webp', 'png', 'jpg', 'jpeg'], true)) {
            throw new \InvalidArgumentException('dashboard.customization_cv.flash.invalid_image');
        }

        $targetRelativeDirectory = CvAboutProfileSettingsService::ABOUT_CUSTOM_UPLOAD_ROOT;
        $targetDirectory = rtrim($this->projectDir, '/').'/public/'.$targetRelativeDirectory;
        if (!is_dir($targetDirectory)) {
            mkdir($targetDirectory, 0775, true);
        }

        $targetFilename = sprintf('about-%s-%s.%s', $suffixName, bin2hex(random_bytes(8)), $extension);
        $uploadedFile->move($targetDirectory, $targetFilename);

        return $targetRelativeDirectory.'/'.$targetFilename;
    }

    /**
     * @brief Delete previous custom about image file when replaced.
     *
     * @param string $relativePath Existing relative path.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function deleteCustomAboutImageIfNeeded(string $relativePath): void
    {
        if (!str_starts_with($relativePath, CvAboutProfileSettingsService::ABOUT_CUSTOM_UPLOAD_ROOT.'/')) {
            return;
        }

        $absolutePath = rtrim($this->projectDir, '/').'/public/'.$relativePath;
        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }
}
