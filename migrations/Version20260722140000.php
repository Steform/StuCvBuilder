<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * @brief Create cv_access_request table for public company access requests.
 *
 * @date 2026-07-22
 * @author Stephane H.
 */
final class Version20260722140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create cv_access_request table for recruiter CV access requests.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE cv_access_request (id INT AUTO_INCREMENT NOT NULL, tracked_company_id INT DEFAULT NULL, company_name VARCHAR(255) NOT NULL, recruiter_name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, country_code VARCHAR(2) DEFAULT NULL, message LONGTEXT DEFAULT NULL, status VARCHAR(32) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', reviewed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', reviewer_note LONGTEXT DEFAULT NULL, submitter_ip VARCHAR(45) DEFAULT NULL, locale VARCHAR(16) DEFAULT NULL, INDEX idx_cv_access_request_status (status), INDEX idx_cv_access_request_created_at (created_at), INDEX IDX_CV_ACCESS_REQUEST_COMPANY (tracked_company_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE cv_access_request ADD CONSTRAINT FK_CV_ACCESS_REQUEST_COMPANY FOREIGN KEY (tracked_company_id) REFERENCES tracked_company (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cv_access_request DROP FOREIGN KEY FK_CV_ACCESS_REQUEST_COMPANY');
        $this->addSql('DROP TABLE cv_access_request');
    }
}
