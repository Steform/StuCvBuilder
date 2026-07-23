<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * @brief Add configurable public CV access mode and invalid-format policy to site configuration.
 *
 * @date 2026-07-22
 * @author Stephane H.
 */
final class Version20260722100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add cv_public_access_mode and cv_invalid_format_policy columns to home_customization.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE home_customization ADD cv_public_access_mode VARCHAR(32) DEFAULT 'gated' NOT NULL, ADD cv_invalid_format_policy VARCHAR(32) DEFAULT 'allow' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE home_customization DROP cv_public_access_mode, DROP cv_invalid_format_policy');
    }
}
