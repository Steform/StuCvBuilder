<?php

declare(strict_types=1);

namespace App\Service\Home;

use App\Entity\HomeCustomization;
use App\Entity\HomeQuickTile;
use App\Entity\HomeQuickTileTranslation;
use App\Repository\HomeQuickTileRepository;
use App\Service\Customization\CustomizationAssetScope;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

/**
 * @brief CRUD and resolution for global custom home quick tiles.
 */
class HomeQuickTileService
{
    public const UPLOAD_SUBDIRECTORY = CustomizationAssetScope::HOME_CUSTOM_UPLOAD_ROOT;

    public const DEFAULT_TILE_ICON_PATH = 'images/home/dashboard.svg';

    /**
     * @var array<int, string>
     */
    private const ALLOWED_TILE_ICON_EXTENSIONS = ['webp', 'svg'];

    /**
     * @brief Build quick tile application service.
     * @param EntityManagerInterface $entityManager Doctrine entity manager.
     * @param HomeCustomizationService $homeCustomizationService Home customization service.
     * @param HomeQuickTileRepository $quickTileRepository Quick tile repository.
     * @param HomeQuickTileLinkValidator $linkValidator Link validator.
     * @param string $projectDir Symfony project directory.
     * @return void
     * @date 2026-05-18
     * @author Stephane H.
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly HomeCustomizationService $homeCustomizationService,
        private readonly HomeQuickTileRepository $quickTileRepository,
        private readonly HomeQuickTileLinkValidator $linkValidator,
        private readonly HomeQuickTileLabelFormatter $labelFormatter,
        private readonly string $projectDir,
    ) {
    }

    /**
     * @brief Resolve enabled tiles for public home rendering.
     * @param string $locale Active request locale.
     * @param string $defaultLocale Fallback locale from site configuration.
     * @return list<HomeQuickTileResolvedView>
     * @date 2026-05-18
     * @author Stephane H.
     */
    public function resolveForHome(string $locale, string $defaultLocale): array
    {
        $customization = $this->homeCustomizationService->getOrCreateSingleton();
        $tiles = $this->quickTileRepository->findEnabledOrdered($customization);
        $resolved = [];

        foreach ($tiles as $tile) {
            $view = $this->resolveTileView($tile, $locale, $defaultLocale);
            if ($view !== null) {
                $resolved[] = $view;
            }
        }

        return $resolved;
    }

    /**
     * @brief List all tiles for admin management.
     * @param void No input parameter.
     * @return list<HomeQuickTile>
     * @date 2026-05-18
     * @author Stephane H.
     */
    public function listAllForAdmin(): array
    {
        $customization = $this->homeCustomizationService->getOrCreateSingleton();

        return $this->quickTileRepository->findAllOrdered($customization);
    }

    /**
     * @brief Find one tile by id for the singleton customization.
     * @param int $tileId Tile primary key.
     * @return HomeQuickTile|null
     * @date 2026-05-18
     * @author Stephane H.
     */
    public function findTileForAdmin(int $tileId): ?HomeQuickTile
    {
        $customization = $this->homeCustomizationService->getOrCreateSingleton();
        $tile = $this->quickTileRepository->find($tileId);
        if ($tile === null || $tile->getCustomization()?->getId() !== $customization->getId()) {
            return null;
        }

        return $tile;
    }

    /**
     * @brief Create a tile from admin or home modal request.
     * @param Request $request HTTP request with labels and link fields.
     * @param array<int, string> $activeLocales Locales requiring labels.
     * @return HomeQuickTile Persisted tile.
     * @date 2026-05-18
     * @author Stephane H.
     */
    public function createFromRequest(Request $request, array $activeLocales): HomeQuickTile
    {
        $customization = $this->homeCustomizationService->getOrCreateSingleton();
        $linkUrl = $this->linkValidator->validateAndNormalize((string) $request->request->get('link_url', ''));
        $openInNewTab = $this->resolveOpenInNewTabFlag($request, $linkUrl);

        $iconUpload = $request->files->get('tile_icon_upload');
        if (!$iconUpload instanceof UploadedFile) {
            throw new \InvalidArgumentException('dashboard.customization_quick_tiles.flash.icon_required');
        }

        $tile = new HomeQuickTile();
        $tile->setCustomization($customization);
        $tile->setLinkUrl($linkUrl);
        $tile->setOpenInNewTab($openInNewTab);
        $tile->setSortOrder($this->nextSortOrder($customization));
        $tile->setEnabled(true);
        $tile->setIconRelativePath($this->storeIconUpload($iconUpload, null));

        $this->applyLabelsFromRequest($tile, $request, $activeLocales);

        $customization->addQuickTile($tile);
        $this->entityManager->persist($tile);
        $this->entityManager->flush();

        return $tile;
    }

    /**
     * @brief Update an existing tile from admin request.
     * @param HomeQuickTile $tile Target tile.
     * @param Request $request HTTP request.
     * @param array<int, string> $activeLocales Locales requiring labels.
     * @return void
     * @date 2026-05-18
     * @author Stephane H.
     */
    public function updateFromRequest(HomeQuickTile $tile, Request $request, array $activeLocales): void
    {
        $linkUrl = $this->linkValidator->validateAndNormalize((string) $request->request->get('link_url', ''));
        $tile->setLinkUrl($linkUrl);
        $tile->setOpenInNewTab($this->resolveOpenInNewTabFlag($request, $linkUrl));
        if ($request->request->has('enabled')) {
            $tile->setEnabled($this->isTruthyRequestFlag($request, 'enabled'));
        }

        $iconUpload = $request->files->get('tile_icon_upload');
        if ($iconUpload instanceof UploadedFile) {
            $tile->setIconRelativePath($this->storeIconUpload($iconUpload, $tile->getIconRelativePath()));
        }

        $storedLabels = $this->collectLabelsFromRequest($request, $activeLocales);
        if ($storedLabels !== []) {
            foreach ($tile->getTranslations()->toArray() as $existing) {
                $tile->removeTranslation($existing);
                $this->entityManager->remove($existing);
            }

            $this->persistLabelsOnTile($tile, $storedLabels);
        }

        $tile->markUpdated();
        $this->entityManager->flush();
    }

    /**
     * @brief Delete tile row and purge custom icon file when applicable.
     * @param HomeQuickTile $tile Tile to remove.
     * @return void
     * @date 2026-05-18
     * @author Stephane H.
     */
    public function delete(HomeQuickTile $tile): void
    {
        $this->deleteCustomUploadIfNeeded($tile->getIconRelativePath());
        $customization = $tile->getCustomization();
        if ($customization !== null) {
            $customization->removeQuickTile($tile);
        }

        $this->entityManager->remove($tile);
        $this->entityManager->flush();
    }

    /**
     * @brief Move tile one position earlier in sort order.
     * @param HomeQuickTile $tile Target tile.
     * @return void
     * @date 2026-05-18
     * @author Stephane H.
     */
    public function moveUp(HomeQuickTile $tile): void
    {
        $this->swapWithNeighbor($tile, -1);
    }

    /**
     * @brief Move tile one position later in sort order.
     * @param HomeQuickTile $tile Target tile.
     * @return void
     * @date 2026-05-18
     * @author Stephane H.
     */
    public function moveDown(HomeQuickTile $tile): void
    {
        $this->swapWithNeighbor($tile, 1);
    }

    /**
     * @brief Replace all quick tiles from backup payload during import.
     *
     * Tiles are removed with a scoped DQL delete so this method does not flush the
     * entire unit of work (home intro translations must stay pending until import commit).
     *
     * @param HomeCustomization $home Parent customization row.
     * @param list<array<string, mixed>> $tilesPayload Serialized tile rows.
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function replaceAllFromBackup(HomeCustomization $home, array $tilesPayload): void
    {
        $existingTiles = $this->quickTileRepository->findAllOrdered($home);
        foreach ($existingTiles as $existing) {
            $this->deleteCustomUploadIfNeeded($existing->getIconRelativePath());
        }

        $this->quickTileRepository->deleteAllForCustomization($home);

        foreach ($existingTiles as $existing) {
            foreach ($existing->getTranslations() as $translation) {
                if ($this->entityManager->contains($translation)) {
                    $this->entityManager->detach($translation);
                }
            }

            $home->removeQuickTile($existing);
            if ($this->entityManager->contains($existing)) {
                $this->entityManager->detach($existing);
            }
        }

        $sortOrder = 0;
        foreach ($tilesPayload as $row) {
            if (!is_array($row)) {
                continue;
            }

            $linkUrlRaw = isset($row['linkUrl']) && is_string($row['linkUrl']) ? $row['linkUrl'] : '';
            if (trim($linkUrlRaw) === '') {
                continue;
            }

            try {
                $linkUrl = $this->linkValidator->validateAndNormalize($linkUrlRaw);
            } catch (\InvalidArgumentException) {
                continue;
            }

            $iconPath = isset($row['iconRelativePath']) && is_string($row['iconRelativePath'])
                ? trim($row['iconRelativePath'])
                : '';
            if ($iconPath === '') {
                continue;
            }

            $tile = new HomeQuickTile();
            $tile->setCustomization($home);
            $tile->setLinkUrl($linkUrl);
            $tile->setOpenInNewTab((bool) ($row['openInNewTab'] ?? false));
            $tile->setEnabled((bool) ($row['enabled'] ?? true));
            $tile->setSortOrder(isset($row['sortOrder']) && is_int($row['sortOrder']) ? $row['sortOrder'] : $sortOrder);
            $tile->setIconRelativePath($iconPath);
            $sortOrder += 10;

            $translations = $row['translations'] ?? [];
            if (is_array($translations)) {
                foreach ($translations as $translationRow) {
                    if (!is_array($translationRow)) {
                        continue;
                    }

                    $locale = isset($translationRow['locale']) && is_string($translationRow['locale'])
                        ? trim($translationRow['locale'])
                        : '';
                    $label = isset($translationRow['label']) && is_string($translationRow['label'])
                        ? trim($translationRow['label'])
                        : '';
                    $formattedLabel = $this->labelFormatter->formatForStorage($label);
                    if ($locale === '' || $formattedLabel === '') {
                        continue;
                    }

                    $translation = new HomeQuickTileTranslation();
                    $translation->setLocale($locale);
                    $translation->setLabel(mb_substr($formattedLabel, 0, 128));
                    $tile->addTranslation($translation);
                }
            }

            if ($tile->getTranslations()->isEmpty()) {
                continue;
            }

            $home->addQuickTile($tile);
            $this->entityManager->persist($tile);
        }
    }

    /**
     * @brief Serialize quick tiles for customization backup export.
     * @param HomeCustomization $home Home customization entity.
     * @return list<array<string, mixed>>
     * @date 2026-05-18
     * @author Stephane H.
     */
    public function serializeForBackup(HomeCustomization $home): array
    {
        $rows = [];
        $tiles = $this->quickTileRepository->findAllOrdered($home);

        foreach ($tiles as $tile) {
            $translations = [];
            foreach ($tile->getTranslations() as $translation) {
                $translations[] = [
                    'locale' => $translation->getLocale(),
                    'label' => $translation->getLabel(),
                ];
            }

            usort($translations, static fn (array $a, array $b): int => strcmp($a['locale'], $b['locale']));

            $rows[] = [
                'sortOrder' => $tile->getSortOrder(),
                'linkUrl' => $tile->getLinkUrl(),
                'openInNewTab' => $tile->isOpenInNewTab(),
                'iconRelativePath' => $tile->getIconRelativePath(),
                'enabled' => $tile->isEnabled(),
                'translations' => $translations,
            ];
        }

        return $rows;
    }

    /**
     * @brief Collect icon relative paths from all tiles for backup file collector.
     * @param HomeCustomization $home Home customization entity.
     * @return list<string>
     * @date 2026-05-18
     * @author Stephane H.
     */
    public function collectIconPaths(HomeCustomization $home): array
    {
        $paths = [];
        foreach ($this->quickTileRepository->findAllOrdered($home) as $tile) {
            $path = $tile->getIconRelativePath();
            if (is_string($path) && trim($path) !== '') {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    /**
     * @brief Build a resolved public view or skip incomplete tiles.
     * @param HomeQuickTile $tile Source entity.
     * @param string $locale Request locale.
     * @param string $defaultLocale Site default locale.
     * @return HomeQuickTileResolvedView|null
     * @date 2026-05-18
     * @author Stephane H.
     */
    private function resolveTileView(HomeQuickTile $tile, string $locale, string $defaultLocale): ?HomeQuickTileResolvedView
    {
        $iconPath = $tile->getIconRelativePath();
        if (!is_string($iconPath) || trim($iconPath) === '' || !$this->isPublicAssetFile($iconPath)) {
            return null;
        }

        $label = $this->labelFormatter->resolveForDisplay($tile, $locale, $defaultLocale);
        if ($label === '') {
            return null;
        }

        $labelsByLocale = [];
        foreach ($tile->getTranslations() as $translation) {
            $translationLocale = strtolower(trim($translation->getLocale()));
            if ($translationLocale === '') {
                continue;
            }

            $formatted = $this->labelFormatter->formatForStorage($translation->getLabel());
            if ($formatted !== '') {
                $labelsByLocale[$translationLocale] = $formatted;
            }
        }

        return new HomeQuickTileResolvedView(
            (int) $tile->getId(),
            $tile->getLinkUrl(),
            $iconPath,
            $label,
            $label,
            $tile->isOpenInNewTab(),
            $tile->isEnabled(),
            $labelsByLocale,
        );
    }

    /**
     * @brief Apply localized labels from request (create flow requires at least one locale).
     * @param HomeQuickTile $tile Target tile.
     * @param Request $request HTTP request.
     * @param array<int, string> $activeLocales Active locale codes.
     * @return void
     * @date 2026-05-18
     * @author Stephane H.
     */
    private function applyLabelsFromRequest(HomeQuickTile $tile, Request $request, array $activeLocales): void
    {
        $storedLabels = $this->collectLabelsFromRequest($request, $activeLocales);
        if ($storedLabels === []) {
            throw new \InvalidArgumentException('dashboard.customization_quick_tiles.flash.label_required');
        }

        $this->persistLabelsOnTile($tile, $storedLabels);
    }

    /**
     * @brief Parse non-empty localized labels from the request payload.
     * @param Request $request HTTP request.
     * @param array<int, string> $activeLocales Active locale codes.
     * @return array<string, string> Locale code to formatted label.
     * @date 2026-05-18
     * @author Stephane H.
     */
    private function collectLabelsFromRequest(Request $request, array $activeLocales): array
    {
        /** @var array<string, mixed> $labels */
        $labels = $request->request->all('tile_label');
        if (!is_array($labels)) {
            $labels = [];
        }

        $storedLabels = [];
        foreach ($activeLocales as $locale) {
            $raw = isset($labels[$locale]) && is_string($labels[$locale]) ? $labels[$locale] : '';
            $formatted = $this->labelFormatter->formatForStorage($raw);
            if ($formatted !== '') {
                $storedLabels[$locale] = mb_substr($formatted, 0, 128);
            }
        }

        return $storedLabels;
    }

    /**
     * @brief Attach formatted translation rows to a tile.
     * @param HomeQuickTile $tile Target tile.
     * @param array<string, string> $storedLabels Locale code to label text.
     * @return void
     * @date 2026-05-18
     * @author Stephane H.
     */
    private function persistLabelsOnTile(HomeQuickTile $tile, array $storedLabels): void
    {
        foreach ($storedLabels as $locale => $value) {
            $translation = new HomeQuickTileTranslation();
            $translation->setLocale($locale);
            $translation->setLabel($value);
            $tile->addTranslation($translation);
        }
    }

    /**
     * @brief Compute next sort index after existing tiles.
     * @param HomeCustomization $customization Parent customization.
     * @return int
     * @date 2026-05-18
     * @author Stephane H.
     */
    private function nextSortOrder(HomeCustomization $customization): int
    {
        $max = -1;
        foreach ($this->quickTileRepository->findAllOrdered($customization) as $existing) {
            $max = max($max, $existing->getSortOrder());
        }

        return $max + 10;
    }

    /**
     * @brief Swap sort order with adjacent tile when present.
     * @param HomeQuickTile $tile Target tile.
     * @param int $direction -1 for up, +1 for down.
     * @return void
     * @date 2026-05-18
     * @author Stephane H.
     */
    private function swapWithNeighbor(HomeQuickTile $tile, int $direction): void
    {
        $customization = $tile->getCustomization();
        if ($customization === null) {
            return;
        }

        $ordered = $this->quickTileRepository->findAllOrdered($customization);
        $index = null;
        foreach ($ordered as $i => $candidate) {
            if ($candidate->getId() === $tile->getId()) {
                $index = $i;
                break;
            }
        }

        if ($index === null) {
            return;
        }

        $neighborIndex = $index + $direction;
        if (!isset($ordered[$neighborIndex])) {
            return;
        }

        $neighbor = $ordered[$neighborIndex];
        $currentOrder = $tile->getSortOrder();
        $tile->setSortOrder($neighbor->getSortOrder());
        $neighbor->setSortOrder($currentOrder);
        $tile->markUpdated();
        $neighbor->markUpdated();
        $this->entityManager->flush();
    }

    /**
     * @brief Persist uploaded tile icon under customizable home directory.
     * @param UploadedFile $upload Uploaded binary.
     * @param string|null $previousRelativePath Previous path for cleanup.
     * @return string New relative public path.
     * @date 2026-05-18
     * @author Stephane H.
     */
    private function storeIconUpload(UploadedFile $upload, ?string $previousRelativePath): string
    {
        $extension = strtolower((string) $upload->guessExtension());
        if (!in_array($extension, self::ALLOWED_TILE_ICON_EXTENSIONS, true)) {
            throw new \InvalidArgumentException('dashboard.customization_quick_tiles.flash.invalid_icon');
        }

        $mimeType = strtolower((string) $upload->getMimeType());
        if (!in_array($mimeType, ['image/webp', 'image/svg+xml'], true)) {
            throw new \InvalidArgumentException('dashboard.customization_quick_tiles.flash.invalid_icon');
        }

        $filename = sprintf('quick-tile-custom-%s.%s', bin2hex(random_bytes(8)), $extension);
        $publicDir = rtrim($this->projectDir, '/').'/public';
        $targetDir = $publicDir.'/'.self::UPLOAD_SUBDIRECTORY;
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }

        $upload->move($targetDir, $filename);
        $relativePath = self::UPLOAD_SUBDIRECTORY.'/'.$filename;
        $this->deleteCustomUploadIfNeeded($previousRelativePath);

        return $relativePath;
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
     * @brief Check whether a relative public asset path exists on disk.
     * @param string $relativePath Path relative to public/.
     * @return bool
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
     * @brief Resolve open-in-new-tab checkbox with link-type default.
     * @param Request $request HTTP request.
     * @param string $linkUrl Validated link URL.
     * @return bool
     * @date 2026-05-18
     * @author Stephane H.
     */
    private function resolveOpenInNewTabFlag(Request $request, string $linkUrl): bool
    {
        if ($request->request->has('open_in_new_tab')) {
            return $this->isTruthyRequestFlag($request, 'open_in_new_tab');
        }

        return $this->linkValidator->suggestsOpenInNewTab($linkUrl);
    }

    /**
     * @brief Interpret checkbox-style request flags.
     * @param Request $request HTTP request.
     * @param string $fieldName Request field name.
     * @return bool
     * @date 2026-05-18
     * @author Stephane H.
     */
    private function isTruthyRequestFlag(Request $request, string $fieldName): bool
    {
        $value = $request->request->get($fieldName);

        return in_array($value, [1, '1', true, 'true', 'on', 'yes'], true);
    }
}
