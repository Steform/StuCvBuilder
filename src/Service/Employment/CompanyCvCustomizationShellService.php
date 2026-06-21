<?php

declare(strict_types=1);

namespace App\Service\Employment;

use App\Cv\CompanyCvCustomizationSectionKey;
use App\Entity\TrackedCompany;
use App\Repository\CompanyCvSectionOverrideRepository;

/**
 * @brief Company CV customization shell: section navigation and customized badges.
 */
final class CompanyCvCustomizationShellService
{
    /**
     * @brief Build shell navigation service.
     *
     * @param CompanyCvSectionOverrideRepository $overrideRepository Section override repository.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function __construct(
        private readonly CompanyCvSectionOverrideRepository $overrideRepository,
    ) {
    }
    /**
     * @brief Build shell view data for company CV customization admin page.
     *
     * @param TrackedCompany $company Tracked company entity.
     * @param string|null $requestedSection Section key from query string.
     * @return array{
     *     sections: list<array{key: string, labelKey: string, customized: bool}>,
     *     activeSection: string,
     *     customizedCount: int,
     *     totalSections: int
     * }
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function buildShellViewData(TrackedCompany $company, ?string $requestedSection): array
    {
        $sections = $this->buildSections($company);
        $activeSection = $this->resolveActiveSection($requestedSection);
        $customizedCount = $this->countCustomized($sections);

        return [
            'sections' => $sections,
            'activeSection' => $activeSection,
            'customizedCount' => $customizedCount,
            'totalSections' => count($sections),
        ];
    }

    /**
     * @brief Resolve active section from query, falling back to default.
     *
     * @param string|null $requestedSection Raw query value.
     * @return string Valid section key.
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function resolveActiveSection(?string $requestedSection): string
    {
        $trimmed = $requestedSection !== null ? trim($requestedSection) : '';
        if ($trimmed !== '' && CompanyCvCustomizationSectionKey::isValid($trimmed)) {
            return $trimmed;
        }

        return CompanyCvCustomizationSectionKey::defaultKey();
    }

    /**
     * @brief Count sections marked as customized for summary badge.
     *
     * @param list<array{key: string, labelKey: string, customized: bool}> $sections Section rows.
     * @return int
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function countCustomized(array $sections): int
    {
        $count = 0;
        foreach ($sections as $section) {
            if ($section['customized']) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * @brief List sections with inheritance state (phase 1: always inherited).
     *
     * @param TrackedCompany $company Tracked company (reserved for future override lookup).
     * @return list<array{key: string, labelKey: string, customized: bool}>
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function buildSections(TrackedCompany $company): array
    {
        $customizedKeys = array_fill_keys(
            $this->overrideRepository->findSectionKeysForCompany($company),
            true,
        );

        $sections = [];
        foreach (CompanyCvCustomizationSectionKey::orderedKeys() as $key) {
            $sections[] = [
                'key' => $key,
                'labelKey' => CompanyCvCustomizationSectionKey::adminTabTranslationKey($key),
                'customized' => isset($customizedKeys[$key]),
            ];
        }

        return $sections;
    }
}
