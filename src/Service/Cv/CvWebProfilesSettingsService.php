<?php

declare(strict_types=1);

namespace App\Service\Cv;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @brief Resolves CV professional web profile links from persisted JSON for admin and public rendering.
 *
 * @date 2026-06-09
 * @author Stephane H.
 */
class CvWebProfilesSettingsService
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @brief Resolve web profile data from content JSON.
     *
     * @param string $contentJson CvProfile JSON payload.
     * @param string $displayLocale Viewer locale for platform labels.
     * @return array{
     *     entries: list<array<string, mixed>>,
     *     hasPersistedEntries: bool
     * }
     * @date 2026-06-09
     * @author Stephane H.
     */
    public function resolveFromContentJson(string $contentJson, string $displayLocale): array
    {
        $payload = json_decode($contentJson, true);

        return $this->resolveFromPayload(is_array($payload) ? $payload : [], $displayLocale);
    }

    /**
     * @brief Resolve web profile data from decoded payload array.
     *
     * @param array<string, mixed> $payload Decoded profile JSON.
     * @param string $displayLocale Viewer locale for platform labels.
     * @return array{
     *     entries: list<array<string, mixed>>,
     *     hasPersistedEntries: bool
     * }
     * @date 2026-06-09
     * @author Stephane H.
     */
    public function resolveFromPayload(array $payload, string $displayLocale): array
    {
        $hasPersistedEntries = WebProfilesContract::hasPersistedEntries($payload);
        $entries = WebProfilesContract::filterVisible(
            WebProfilesContract::entriesFromStoredPayload($payload)
        );

        return [
            'entries' => $this->attachDisplayLabels($entries, $displayLocale),
            'hasPersistedEntries' => $hasPersistedEntries,
        ];
    }

    /**
     * @brief Attach display labels and icon hints to web profile entries.
     *
     * @param list<array<string, mixed>> $entries Normalized visible entries.
     * @param string $displayLocale Viewer locale.
     * @return list<array<string, mixed>>
     * @date 2026-06-09
     * @author Stephane H.
     */
    public function attachDisplayLabels(array $entries, string $displayLocale): array
    {
        $result = [];
        foreach ($entries as $entry) {
            $platform = is_string($entry['platform'] ?? null) ? $entry['platform'] : 'other';
            $customLabel = is_string($entry['label'] ?? null) ? trim($entry['label']) : '';
            $defaultLabel = $this->translator->trans(
                'cv.web_profiles.platform.'.$platform,
                [],
                'messages',
                $displayLocale
            );

            $entry['displayLabel'] = $customLabel !== '' ? $customLabel : $defaultLabel;
            $entry['iconClass'] = $this->resolveIconClass($platform, is_string($entry['url'] ?? null) ? $entry['url'] : '');
            $result[] = $entry;
        }

        return $result;
    }

    /**
     * @brief Resolve Bootstrap icon class for a platform code.
     *
     * @param string $platform Platform slug.
     * @param string $url Profile URL used for GitHub host detection.
     * @return string Bootstrap Icons class name.
     * @date 2026-06-09
     * @author Stephane H.
     */
    private function resolveIconClass(string $platform, string $url): string
    {
        if ($platform === 'github' || FlagshipProjectsContract::isGithubCodeUrl($url)) {
            return 'bi-github';
        }

        return match ($platform) {
            'linkedin' => 'bi-linkedin',
            'gitlab' => 'bi-gitlab',
            'bitbucket' => 'bi-git',
            'stackoverflow' => 'bi-stack-overflow',
            'mastodon' => 'bi-mastodon',
            'medium' => 'bi-medium',
            'website' => 'bi-globe2',
            default => 'bi-link-45deg',
        };
    }
}
