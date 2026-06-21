<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Service\Cv\CvLegacySeedContentService;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * @brief Strip cv-symfony8 demo seeds from persisted CvProfile JSON on upgraded instances.
 */
final class Version20260621190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove legacy cv-symfony8 demo seeds from cv_profile.content_json (projects, certs, situation, skills).';
    }

    public function up(Schema $schema): void
    {
        // Data cleanup runs in postUp() so JSON can be decoded and rewritten in PHP.
    }

    public function postUp(Schema $schema): void
    {
        $rows = $this->connection->fetchAllAssociative('SELECT id, content_json FROM cv_profile');

        foreach ($rows as $row) {
            $rawJson = $row['content_json'] ?? null;
            if (!is_string($rawJson) || trim($rawJson) === '') {
                continue;
            }

            try {
                $decoded = json_decode($rawJson, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                continue;
            }

            if (!is_array($decoded)) {
                continue;
            }

            $sanitized = CvLegacySeedContentService::stripLegacySeededContent($decoded);
            $sanitizedJson = json_encode($sanitized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            if ($sanitizedJson === $rawJson) {
                continue;
            }

            $this->connection->update(
                'cv_profile',
                ['content_json' => $sanitizedJson],
                ['id' => (int) $row['id']]
            );
        }
    }

    public function down(Schema $schema): void
    {
        // Legacy seeded content is intentionally not restored.
    }
}
