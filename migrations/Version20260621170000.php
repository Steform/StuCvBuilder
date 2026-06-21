<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Service\Home\HomeCustomizationService;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * @brief Remove legacy cv-symfony8 home intro seed copies from persisted customization.
 */
final class Version20260621170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Clear legacy HIRT home intro seed copies from home_customization_translation.';
    }

    public function up(Schema $schema): void
    {
        foreach (HomeCustomizationService::DEPRECATED_LEGACY_INTRO_TEXTS as $legacyIntroText) {
            $this->addSql(
                'UPDATE home_customization_translation SET intro_text = :cleared WHERE intro_text = :legacy',
                [
                    'cleared' => '',
                    'legacy' => $legacyIntroText,
                ]
            );
        }
    }

    public function down(Schema $schema): void
    {
        // Legacy intro copies are intentionally not restored.
    }
}
