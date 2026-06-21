<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * @brief Add dedicated Open Graph image storage on home customization singleton.
 */
final class Version20260621210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add open_graph_image_relative_path column to home_customization for SEO share cards.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE home_customization
            ADD open_graph_image_relative_path VARCHAR(512) DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE home_customization DROP open_graph_image_relative_path');
    }
}
