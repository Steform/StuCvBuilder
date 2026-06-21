<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Service\Cv\CvAboutProfileSettingsService;
use App\Service\Home\HomeCustomizationService;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * @brief Remove legacy profile photo and home image seed paths from persisted customization.
 */
final class Version20260621180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Clear legacy HIRT profile photo and home image seed paths from cv_profile and home_customization.';
    }

    public function up(Schema $schema): void
    {
        foreach (CvAboutProfileSettingsService::DEPRECATED_PROFILE_PHOTO_PATHS as $legacyPhotoPath) {
            $this->addSql(
                <<<'SQL'
                UPDATE cv_profile
                SET content_json = JSON_REMOVE(content_json, '$.aboutProfilePhotoPath')
                WHERE JSON_UNQUOTE(JSON_EXTRACT(content_json, '$.aboutProfilePhotoPath')) = :legacy
                SQL,
                ['legacy' => $legacyPhotoPath]
            );
        }

        foreach (HomeCustomizationService::DEPRECATED_HOME_IMAGE_PATHS as $legacyImagePath) {
            $this->addSql(
                'UPDATE home_customization SET signature_image_relative_path = NULL WHERE signature_image_relative_path = :legacy',
                ['legacy' => $legacyImagePath]
            );
            $this->addSql(
                'UPDATE home_customization SET background_image_relative_path = NULL WHERE background_image_relative_path = :legacy',
                ['legacy' => $legacyImagePath]
            );
        }
    }

    public function down(Schema $schema): void
    {
        // Legacy image paths are intentionally not restored.
    }
}
