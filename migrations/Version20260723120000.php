<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * @brief Add company CV content mode, CvProfile company FK, drop section overrides.
 *
 * @date 2026-07-23
 * @author Stephane H.
 */
final class Version20260723120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add cv_content_mode and company CvProfile clones; drop company_cv_section_override.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE tracked_company ADD cv_content_mode VARCHAR(16) NOT NULL DEFAULT 'synced'");
        $this->addSql("UPDATE tracked_company SET cv_content_mode = 'synced'");
        $this->addSql('ALTER TABLE tracked_company CHANGE cv_content_mode cv_content_mode VARCHAR(16) NOT NULL');
        $this->addSql('ALTER TABLE cv_profile ADD tracked_company_id INT DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_cv_profile_tracked_company ON cv_profile (tracked_company_id)');
        $this->addSql('ALTER TABLE cv_profile ADD CONSTRAINT FK_CV_PROFILE_TRACKED_COMPANY FOREIGN KEY (tracked_company_id) REFERENCES tracked_company (id) ON DELETE CASCADE');
        $this->addSql('DROP TABLE IF EXISTS company_cv_section_override');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE company_cv_section_override (id INT AUTO_INCREMENT NOT NULL, tracked_company_id INT NOT NULL, section_key VARCHAR(32) NOT NULL, content_json LONGTEXT NOT NULL, updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX uniq_company_cv_override_section (tracked_company_id, section_key), INDEX IDX_COMPANY_CV_OVERRIDE_COMPANY (tracked_company_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE company_cv_section_override ADD CONSTRAINT FK_COMPANY_CV_OVERRIDE_COMPANY FOREIGN KEY (tracked_company_id) REFERENCES tracked_company (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE cv_profile DROP FOREIGN KEY FK_CV_PROFILE_TRACKED_COMPANY');
        $this->addSql('DROP INDEX uniq_cv_profile_tracked_company ON cv_profile');
        $this->addSql('ALTER TABLE cv_profile DROP tracked_company_id');
        $this->addSql('ALTER TABLE tracked_company DROP cv_content_mode');
    }
}
