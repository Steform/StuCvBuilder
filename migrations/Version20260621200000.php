<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * @brief Add per-locale SEO meta description storage on home customization translations.
 */
final class Version20260621200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add meta_description column to home_customization_translation for admin SEO taglines.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE home_customization_translation
            ADD meta_description VARCHAR(512) NOT NULL DEFAULT ''
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE home_customization_translation DROP meta_description');
    }
}
