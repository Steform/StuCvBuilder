<?php

declare(strict_types=1);

namespace App\Service\Cv;

use App\Cv\CvProfilePersistenceScope;
use App\Cv\SkillsTreeContract;
use App\Entity\CvProfile;
use App\Repository\CvProfileRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @brief Persist skills catalog in the global CvProfile content JSON.
 */
final class GlobalSkillsCatalogPersistence implements SkillsCatalogPersistence
{
    /**
     * @brief Wire global CV profile skills catalog persistence.
     *
     * @param CvProfileRepository $cvProfileRepository CV profile repository.
     * @param EntityManagerInterface $entityManager ORM.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function __construct(
        private readonly CvProfileRepository $cvProfileRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @brief Load latest global CV profile payload slice.
     *
     * @return array<string, mixed>
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function loadPayloadSlice(): array
    {
        $profile = $this->cvProfileRepository->findOneBy([], ['id' => 'DESC']);
        if (!$profile instanceof CvProfile) {
            return [];
        }

        $decoded = json_decode($profile->getContentJson(), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @brief Merge catalog into global CV profile and flush.
     *
     * @param array{categories: list<array<string, mixed>>} $catalog Normalized catalog.
     * @param list<string> $activeLocales Active locale codes.
     * @param string $defaultLocale Site default locale.
     * @return array{categories: list<array<string, mixed>>}
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function saveCatalog(array $catalog, array $activeLocales, string $defaultLocale): array
    {
        $normalized = SkillsTreeContract::normalizeCatalog($catalog, $activeLocales, $defaultLocale);
        if ($normalized === null) {
            throw new \InvalidArgumentException('dashboard.customization_cv.skills.flash_invalid');
        }

        $profile = $this->resolveOrCreateProfile();
        $payload = $this->loadPayloadSlice();
        $payload = SkillsTreeContract::mergeCatalogIntoPayload($payload, $normalized);
        $sanitized = CvProfilePersistenceScope::sanitizeForPersistence($payload);
        $encoded = json_encode($sanitized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($encoded)) {
            throw new \InvalidArgumentException('dashboard.customization_cv.skills.flash_invalid');
        }

        $profile->setContentJson($encoded);
        $this->entityManager->flush();

        return $normalized;
    }

    /**
     * @brief Resolve latest CV profile or create an empty row.
     *
     * @return CvProfile
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function resolveOrCreateProfile(): CvProfile
    {
        $profile = $this->cvProfileRepository->findOneBy([], ['id' => 'DESC']);
        if ($profile instanceof CvProfile) {
            return $profile;
        }

        $profile = new CvProfile('default', '{}');
        $this->entityManager->persist($profile);

        return $profile;
    }
}
