<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * @brief Drop legacy home customization storage tile icon column from removed file-sharing module.
 */
final class Version20260621220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop unused storage_tile_icon_relative_path from home_customization.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE home_customization DROP storage_tile_icon_relative_path');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE home_customization ADD storage_tile_icon_relative_path VARCHAR(512) DEFAULT NULL');
    }
}
