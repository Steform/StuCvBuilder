<?php

declare(strict_types=1);

namespace App\Service\Cv;

use App\Cv\AboutSectionPatternCustomizationContract;
use App\Service\Security\SvgUploadSanitizer;

/**
 * @brief Discover About SVG pattern templates and map canonical grayscale tones to CSS variables.
 *
 * @date 2026-05-27
 * @author Stephane H.
 */
final class CvAboutPatternTemplateService
{
    private const MANIFEST_FILENAME = '_pattern-manifest.json';
    public const SIDE_LEFT = 'left';
    public const SIDE_RIGHT = 'right';
    public const SIDE_BOTH = 'both';

    /** @var string[] */
    private const TEMPLATE_GLOBS = [
        'fond-about*.svg',
    ];

    /** @var array<string, string> */
    private const COLOR_TO_VARIABLE_MAP = [
        '#2d2d2d' => 'var(--cv-about-pattern-tone-1)',
        '#434343' => 'var(--cv-about-pattern-tone-2)',
        '#a1a1a1' => 'var(--cv-about-pattern-tone-3)',
        '#cbcbcb' => 'var(--cv-about-pattern-tone-4)',
    ];

    public function __construct(
        private readonly string $projectDir,
        private readonly SvgUploadSanitizer $svgUploadSanitizer,
    ) {
    }

    /**
     * @brief List available pattern template IDs discovered under public About assets.
     *
     * @param void No input parameter.
     * @return list<string>
     * @date 2026-05-28
     * @author Stephane H.
     */
    public function listPatternIds(): array
    {
        return array_keys($this->listPatternChoiceMap());
    }

    /**
     * @brief List available pattern choices with display name and deletion capability.
     *
     * @param void No input parameter.
     * @return list<array{id: string, label: string, side: string, canDelete: bool}>
     * @date 2026-05-28
     * @author Stephane H.
     */
    public function listPatternChoices(): array
    {
        return array_values($this->listPatternChoiceMap());
    }

    /**
     * @brief List available pattern choices grouped by allowed side.
     *
     * @param void No input parameter.
     * @return array{left: list<array{id: string, label: string, side: string, canDelete: bool}>, right: list<array{id: string, label: string, side: string, canDelete: bool}>}
     * @date 2026-05-28
     * @author Stephane H.
     */
    public function listPatternChoicesBySide(): array
    {
        $left = [];
        $right = [];
        foreach ($this->listPatternChoices() as $choice) {
            $side = is_string($choice['side'] ?? null) ? $choice['side'] : self::SIDE_BOTH;
            if (in_array($side, [self::SIDE_LEFT, self::SIDE_BOTH], true)) {
                $left[] = $choice;
            }
            if (in_array($side, [self::SIDE_RIGHT, self::SIDE_BOTH], true)) {
                $right[] = $choice;
            }
        }

        return [
            self::SIDE_LEFT => $left,
            self::SIDE_RIGHT => $right,
        ];
    }

    /**
     * @brief Render one pattern SVG with color tokens replaced by dynamic About tone variables.
     *
     * @param string|null $patternId Requested template id.
     * @return array{patternId: string, svg: string, warnings: list<string>} Warnings only when the template file is missing.
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function renderTemplate(?string $patternId): array
    {
        $warnings = [];
        $resolvedId = AboutSectionPatternCustomizationContract::normalizePatternId($patternId);
        if ($resolvedId === null) {
            return [
                'patternId' => '',
                'svg' => '',
                'warnings' => [],
            ];
        }

        $path = $this->resolveTemplatePath($resolvedId);
        if ($path === null) {
            $warnings[] = 'dashboard.customization_cv.about_section_customization.pattern_warning_missing';
            return [
                'patternId' => $resolvedId,
                'svg' => '',
                'warnings' => array_values(array_unique($warnings)),
            ];
        }

        $svg = (string) (file_get_contents($path) ?: '');
        if ($svg === '') {
            $warnings[] = 'dashboard.customization_cv.about_section_customization.pattern_warning_missing';

            return [
                'patternId' => $resolvedId,
                'svg' => '',
                'warnings' => array_values(array_unique($warnings)),
            ];
        }

        foreach (self::COLOR_TO_VARIABLE_MAP as $hex => $cssVariable) {
            $svg = preg_replace('/'.preg_quote($hex, '/').'/i', $cssVariable, $svg) ?? $svg;
        }

        $svg = preg_replace('/^\s*<\?xml[^>]*>\s*/i', '', $svg) ?? $svg;
        $svg = preg_replace('/^\s*<!DOCTYPE[^>]*>\s*/i', '', $svg) ?? $svg;
        $svg = $this->ensureSvgRootAttributes($svg);

        return [
            'patternId' => $resolvedId,
            'svg' => $svg,
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    /**
     * @brief Store an uploaded About SVG template and optional display name in the manifest.
     *
     * @param string $svgContent Validated SVG content.
     * @param string|null $displayName Human-readable label for admin radios.
     * @param string|null $side Allowed side for this uploaded SVG (`left` or `right`).
     * @return string Stored template id.
     * @date 2026-05-28
     * @author Stephane H.
     */
    public function storeUploadedTemplate(string $svgContent, ?string $displayName = null, ?string $side = null): string
    {
        $svgContent = $this->svgUploadSanitizer->sanitize($svgContent);
        $targetDirectory = $this->projectDir.'/public/images/cv/about/patterns';
        if (!is_dir($targetDirectory)) {
            mkdir($targetDirectory, 0775, true);
        }

        $templateId = sprintf('fond-about-upload-%s', bin2hex(random_bytes(5)));
        file_put_contents($targetDirectory.'/'.$templateId.'.svg', $svgContent);
        $this->savePatternMetadata($templateId, $displayName, $side);

        return $templateId;
    }

    /**
     * @brief Delete one deletable template file and manifest entry by normalized id.
     *
     * @param string|null $patternId Candidate template id.
     * @return bool True when a file was deleted.
     * @date 2026-05-28
     * @author Stephane H.
     */
    public function deleteTemplate(?string $patternId): bool
    {
        $normalizedId = AboutSectionPatternCustomizationContract::normalizePatternId($patternId);
        if ($normalizedId === null || !$this->isPatternDeletable($normalizedId)) {
            return false;
        }

        $path = $this->resolveTemplatePath($normalizedId);
        if ($path === null || !is_file($path)) {
            return false;
        }

        @unlink($path);
        $manifest = $this->readManifestMap();
        unset($manifest[$normalizedId]);
        $this->writeManifestMap($manifest);

        return true;
    }

    /**
     * @brief Ensure root SVG tag exposes expected class for About pattern styling.
     *
     * @param string $svg SVG markup.
     * @return string
     * @date 2026-05-27
     * @author Stephane H.
     */
    private function ensureSvgRootAttributes(string $svg): string
    {
        $updated = preg_replace_callback('/<svg\b[^>]*>/i', static function (array $matches): string {
            $openTag = $matches[0] ?? '<svg>';
            if (!is_string($openTag)) {
                return '<svg class="cv-about__pattern-svg">';
            }

            $openTag = preg_replace('/\s*preserveAspectRatio\s*=\s*"[^"]*"/i', '', $openTag) ?? $openTag;

            if (preg_match('/\bclass\s*=\s*"([^"]*)"/i', $openTag, $classMatch) === 1) {
                $existingClass = trim((string) ($classMatch[1] ?? ''));
                if (!preg_match('/(?:^|\s)cv-about__pattern-svg(?:\s|$)/', $existingClass)) {
                    $replacement = trim($existingClass.' cv-about__pattern-svg');
                    $openTag = preg_replace(
                        '/\bclass\s*=\s*"[^"]*"/i',
                        'class="'.$replacement.'"',
                        $openTag,
                        1
                    ) ?? $openTag;
                }
            } else {
                $openTag = preg_replace('/<svg\b/i', '<svg class="cv-about__pattern-svg"', $openTag, 1) ?? $openTag;
            }

            return $openTag;
        }, $svg, 1);

        if (!is_string($updated) || $updated === '') {
            return $svg;
        }

        return $updated;
    }

    /**
     * @brief Return strict grayscale palette expected in uploaded About SVG templates.
     *
     * @param void No input parameter.
     * @return list<string>
     * @date 2026-05-27
     * @author Stephane H.
     */
    public function getAllowedHexPalette(): array
    {
        return array_keys(self::COLOR_TO_VARIABLE_MAP);
    }

    /**
     * @brief Validate an uploaded SVG content against the strict grayscale palette.
     *
     * @param string $svg Raw SVG payload.
     * @return list<string> Unknown hex colors detected in the document.
     * @date 2026-05-27
     * @author Stephane H.
     */
    public function findUnknownHexColors(string $svg): array
    {
        $unknown = [];
        $known = array_fill_keys(array_keys(self::COLOR_TO_VARIABLE_MAP), true);
        foreach ($this->extractHexColors($svg) as $hex) {
            if (!isset($known[$hex])) {
                $unknown[$hex] = true;
            }
        }

        $result = array_keys($unknown);
        sort($result);

        return $result;
    }

    /**
     * @brief Resolve one template file path from a normalized pattern id.
     *
     * @param string $patternId Normalized pattern id.
     * @return string|null
     * @date 2026-05-27
     * @author Stephane H.
     */
    private function resolveTemplatePath(string $patternId): ?string
    {
        foreach ($this->resolveCandidateFiles() as $filePath) {
            if ($this->patternIdFromPath($filePath) === $patternId) {
                return $filePath;
            }
        }

        return null;
    }

    /**
     * @brief Return all candidate SVG files in About public pattern directories.
     *
     * @param void No input parameter.
     * @return list<string>
     * @date 2026-05-27
     * @author Stephane H.
     */
    private function resolveCandidateFiles(): array
    {
        $baseDirs = [
            $this->projectDir.'/public/images/cv/about/patterns',
            $this->projectDir.'/public/images/cv/about',
        ];

        $files = [];
        foreach ($baseDirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            foreach (self::TEMPLATE_GLOBS as $glob) {
                $matched = glob($dir.'/'.$glob);
                if (!is_array($matched)) {
                    continue;
                }

                foreach ($matched as $filePath) {
                    if (is_file($filePath)) {
                        $files[$filePath] = true;
                    }
                }
            }
        }

        $result = array_keys($files);
        sort($result);

        return $result;
    }

    /**
     * @brief Extract normalized `#rrggbb` colors from raw SVG content.
     *
     * @param string $svg Raw SVG text.
     * @return list<string>
     * @date 2026-05-27
     * @author Stephane H.
     */
    private function extractHexColors(string $svg): array
    {
        preg_match_all('/#[0-9a-fA-F]{3,6}\b/', $svg, $matches);
        $colors = [];
        foreach ($matches[0] ?? [] as $color) {
            if (!is_string($color)) {
                continue;
            }

            $normalized = strtolower($color);
            if (strlen($normalized) === 4) {
                $normalized = '#'.$normalized[1].$normalized[1].$normalized[2].$normalized[2].$normalized[3].$normalized[3];
            }

            $colors[$normalized] = true;
        }

        return array_keys($colors);
    }

    /**
     * @brief Convert a discovered file path to a normalized pattern identifier.
     *
     * @param string $filePath Absolute file path.
     * @return string|null
     * @date 2026-05-27
     * @author Stephane H.
     */
    private function patternIdFromPath(string $filePath): ?string
    {
        $basename = pathinfo($filePath, PATHINFO_FILENAME);

        return AboutSectionPatternCustomizationContract::normalizePatternId($basename);
    }

    /**
     * @brief Build sorted pattern choice map keyed by id.
     *
     * @param void No input parameter.
     * @return array<string, array{id: string, label: string, side: string, canDelete: bool}>
     * @date 2026-05-28
     * @author Stephane H.
     */
    private function listPatternChoiceMap(): array
    {
        $manifest = $this->readManifestMap();
        $result = [];
        foreach ($this->resolveCandidateFiles() as $filePath) {
            $id = $this->patternIdFromPath($filePath);
            if ($id === null) {
                continue;
            }

            $result[$id] = [
                'id' => $id,
                'label' => $this->resolvePatternLabel($id, $manifest),
                'side' => $this->resolvePatternSide($id, $manifest),
                'canDelete' => $this->isPatternDeletable($id),
            ];
        }

        ksort($result);

        return $result;
    }

    /**
     * @brief Resolve one admin label from manifest metadata or fallback id.
     *
     * @param string $patternId Normalized pattern id.
     * @param array<string, array{label: string, side: string}> $manifest Existing manifest map.
     * @return string
     * @date 2026-05-28
     * @author Stephane H.
     */
    private function resolvePatternLabel(string $patternId, array $manifest): string
    {
        $stored = isset($manifest[$patternId]['label']) && is_string($manifest[$patternId]['label'])
            ? trim($manifest[$patternId]['label'])
            : '';

        return $stored !== '' ? $stored : $patternId;
    }

    /**
     * @brief Resolve allowed side from manifest metadata with default `both`.
     *
     * @param string $patternId Normalized pattern id.
     * @param array<string, array{label: string, side: string}> $manifest Existing manifest map.
     * @return string
     * @date 2026-05-28
     * @author Stephane H.
     */
    private function resolvePatternSide(string $patternId, array $manifest): string
    {
        $stored = isset($manifest[$patternId]['side']) && is_string($manifest[$patternId]['side'])
            ? $manifest[$patternId]['side']
            : self::SIDE_BOTH;

        return $this->normalizePatternSide($stored) ?? self::SIDE_BOTH;
    }

    /**
     * @brief Persist pattern metadata (display name + side) in manifest for one pattern id.
     *
     * @param string $patternId Normalized pattern id.
     * @param string|null $displayName Optional label from admin form.
     * @param string|null $side Allowed side for this SVG.
     * @return void
     * @date 2026-05-28
     * @author Stephane H.
     */
    private function savePatternMetadata(string $patternId, ?string $displayName, ?string $side): void
    {
        $manifest = $this->readManifestMap();
        $normalizedName = $this->normalizeDisplayName($displayName);
        $normalizedSide = $this->normalizePatternSide($side);
        if ($normalizedName === null && $normalizedSide === null) {
            unset($manifest[$patternId]);
        } else {
            $manifest[$patternId] = [
                'label' => $normalizedName ?? $patternId,
                'side' => $normalizedSide ?? self::SIDE_BOTH,
            ];
        }

        $this->writeManifestMap($manifest);
    }

    /**
     * @brief Read manifest mapping pattern ids to custom admin labels.
     *
     * @param void No input parameter.
     * @return array<string, array{label: string, side: string}>
     * @date 2026-05-28
     * @author Stephane H.
     */
    private function readManifestMap(): array
    {
        $manifestPath = $this->projectDir.'/public/images/cv/about/patterns/'.self::MANIFEST_FILENAME;
        if (!is_file($manifestPath)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($manifestPath), true);
        if (!is_array($decoded)) {
            return [];
        }

        $result = [];
        foreach ($decoded as $rawId => $rawMeta) {
            $id = AboutSectionPatternCustomizationContract::normalizePatternId($rawId);
            if ($id === null) {
                continue;
            }

            $rawLabel = null;
            $rawSide = null;
            if (is_string($rawMeta)) {
                $rawLabel = $rawMeta;
            } elseif (is_array($rawMeta)) {
                $rawLabel = is_string($rawMeta['label'] ?? null) ? $rawMeta['label'] : null;
                $rawSide = is_string($rawMeta['side'] ?? null) ? $rawMeta['side'] : null;
            }

            $label = $this->normalizeDisplayName($rawLabel);
            if ($label === null) {
                continue;
            }
            $result[$id] = [
                'label' => $label,
                'side' => $this->normalizePatternSide($rawSide) ?? self::SIDE_BOTH,
            ];
        }

        ksort($result);

        return $result;
    }

    /**
     * @brief Write pattern label manifest as pretty JSON under patterns directory.
     *
     * @param array<string, array{label: string, side: string}> $manifest Normalized map.
     * @return void
     * @date 2026-05-28
     * @author Stephane H.
     */
    private function writeManifestMap(array $manifest): void
    {
        ksort($manifest);
        $directory = $this->projectDir.'/public/images/cv/about/patterns';
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents(
            $directory.'/'.self::MANIFEST_FILENAME,
            (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * @brief Normalize optional display name to a bounded, trimmed UTF-8-safe label.
     *
     * @param string|null $raw Raw submitted name.
     * @return string|null
     * @date 2026-05-28
     * @author Stephane H.
     */
    private function normalizeDisplayName(?string $raw): ?string
    {
        if (!is_string($raw)) {
            return null;
        }

        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }

        $bounded = substr($trimmed, 0, 80);

        return preg_replace('/\s+/', ' ', $bounded) ?: null;
    }

    /**
     * @brief Normalize submitted pattern side value to allowed enum.
     *
     * @param string|null $raw Raw side input.
     * @return string|null
     * @date 2026-05-28
     * @author Stephane H.
     */
    private function normalizePatternSide(?string $raw): ?string
    {
        if (!is_string($raw)) {
            return null;
        }

        $value = strtolower(trim($raw));
        if (!in_array($value, [self::SIDE_LEFT, self::SIDE_RIGHT, self::SIDE_BOTH], true)) {
            return null;
        }

        return $value;
    }

    /**
     * @brief Tell if a pattern id is managed as deletable custom upload.
     *
     * @param string $patternId Normalized pattern id.
     * @return bool
     * @date 2026-05-28
     * @author Stephane H.
     */
    private function isPatternDeletable(string $patternId): bool
    {
        return str_starts_with($patternId, 'fond-about-upload-');
    }
}

