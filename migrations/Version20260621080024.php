<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * @brief Initial StuCvBuilder schema (CV, employment, auth, bug reports).
 */
final class Version20260621080024 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial StuCvBuilder schema without storage tables or seed data.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE app_user (
              id INT AUTO_INCREMENT NOT NULL,
              email VARCHAR(180) NOT NULL,
              password VARCHAR(255) NOT NULL,
              pseudonym VARCHAR(100) NOT NULL,
              totp_enabled TINYINT NOT NULL,
              setup_confirmed TINYINT NOT NULL,
              active TINYINT NOT NULL,
              password_reset_required TINYINT NOT NULL,
              session_version INT NOT NULL,
              roles JSON NOT NULL,
              UNIQUE INDEX UNIQ_88BDF3E9E7927C74 (email),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE bug_report (
              id INT AUTO_INCREMENT NOT NULL,
              status VARCHAR(32) NOT NULL,
              severity VARCHAR(32) NOT NULL,
              action_description LONGTEXT NOT NULL,
              observed_result LONGTEXT NOT NULL,
              expected_result LONGTEXT DEFAULT NULL,
              route_name VARCHAR(255) DEFAULT NULL,
              path VARCHAR(2048) NOT NULL,
              query_string LONGTEXT DEFAULT NULL,
              locale VARCHAR(10) NOT NULL,
              theme VARCHAR(10) NOT NULL,
              user_agent LONGTEXT DEFAULT NULL,
              viewport_width INT DEFAULT NULL,
              viewport_height INT DEFAULT NULL,
              referrer LONGTEXT DEFAULT NULL,
              correlation_id VARCHAR(128) DEFAULT NULL,
              app_version VARCHAR(64) DEFAULT NULL,
              action_timeline_json JSON DEFAULT NULL,
              resolved_at DATETIME DEFAULT NULL,
              archived_at DATETIME DEFAULT NULL,
              archive_reason LONGTEXT DEFAULT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME NOT NULL,
              reporter_user_id INT NOT NULL,
              resolved_by_user_id INT DEFAULT NULL,
              archived_by_user_id INT DEFAULT NULL,
              INDEX IDX_F6F2DC7ADF3D6D95 (reporter_user_id),
              INDEX IDX_F6F2DC7AAC78F73B (resolved_by_user_id),
              INDEX IDX_F6F2DC7AACEC367 (archived_by_user_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE company_cv_section_override (
              id INT AUTO_INCREMENT NOT NULL,
              section_key VARCHAR(32) NOT NULL,
              content_json LONGTEXT NOT NULL,
              updated_at DATETIME NOT NULL,
              tracked_company_id INT NOT NULL,
              INDEX IDX_FCEBB17DB5033087 (tracked_company_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE company_cv_visit (
              id INT AUTO_INCREMENT NOT NULL,
              visit_date DATE NOT NULL,
              visitor_key VARCHAR(64) NOT NULL,
              started_at DATETIME NOT NULL,
              last_activity_at DATETIME NOT NULL,
              journey_json JSON NOT NULL,
              ip_address VARCHAR(64) DEFAULT NULL,
              country_code VARCHAR(2) DEFAULT NULL,
              consent_given_at DATETIME DEFAULT NULL,
              tracking_allowed TINYINT DEFAULT NULL,
              company_id INT NOT NULL,
              INDEX IDX_D4ED2C4B979B1AD6 (company_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE company_recruiter_visit_notification (
              id INT AUTO_INCREMENT NOT NULL,
              notification_date DATE NOT NULL,
              sent_at DATETIME NOT NULL,
              company_id INT NOT NULL,
              visit_id INT DEFAULT NULL,
              INDEX IDX_10FDEA3D979B1AD6 (company_id),
              INDEX IDX_10FDEA3D75FA0FF2 (visit_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE cv_connection_log (
              id INT AUTO_INCREMENT NOT NULL,
              occurred_at DATETIME NOT NULL,
              connection_kind VARCHAR(32) NOT NULL,
              format_raw VARCHAR(128) DEFAULT NULL,
              company_code_snapshot VARCHAR(12) DEFAULT NULL,
              company_name_snapshot VARCHAR(255) DEFAULT NULL,
              ip_address VARCHAR(64) DEFAULT NULL,
              country_code VARCHAR(2) DEFAULT NULL,
              user_agent LONGTEXT DEFAULT NULL,
              gate_passed TINYINT NOT NULL,
              attestation_method VARCHAR(16) DEFAULT NULL,
              technical_score INT DEFAULT NULL,
              countable_for_company TINYINT NOT NULL,
              is_admin_bypass TINYINT NOT NULL,
              request_path VARCHAR(512) DEFAULT NULL,
              request_route VARCHAR(128) DEFAULT NULL,
              consent_given_at DATETIME DEFAULT NULL,
              tracking_allowed TINYINT DEFAULT NULL,
              company_id INT DEFAULT NULL,
              visit_id INT DEFAULT NULL,
              INDEX IDX_FD2C995D979B1AD6 (company_id),
              INDEX IDX_FD2C995D75FA0FF2 (visit_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE cv_profile (
              id INT AUTO_INCREMENT NOT NULL,
              title VARCHAR(150) NOT NULL,
              content_json LONGTEXT NOT NULL,
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE employment_country (
              id INT AUTO_INCREMENT NOT NULL,
              code VARCHAR(2) NOT NULL,
              label VARCHAR(255) NOT NULL,
              presentation_locale VARCHAR(5) NOT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME NOT NULL,
              UNIQUE INDEX uniq_employment_country_code (code),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE employment_document_locale_asset (
              id INT AUTO_INCREMENT NOT NULL,
              locale VARCHAR(8) NOT NULL,
              template_storage_path VARCHAR(512) DEFAULT NULL,
              template_original_filename VARCHAR(255) DEFAULT NULL,
              pdf_storage_path VARCHAR(512) DEFAULT NULL,
              pdf_original_filename VARCHAR(255) DEFAULT NULL,
              variant_id INT NOT NULL,
              INDEX IDX_188B30BB3B69A9AF (variant_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE employment_document_variant (
              id INT AUTO_INCREMENT NOT NULL,
              kind VARCHAR(8) NOT NULL,
              name VARCHAR(160) NOT NULL,
              name_normalized VARCHAR(160) NOT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME NOT NULL,
              archived_at DATETIME DEFAULT NULL,
              link_x NUMERIC(5, 2) NOT NULL,
              link_y NUMERIC(5, 2) NOT NULL,
              square_size_cm NUMERIC(5, 2) NOT NULL,
              is_default TINYINT DEFAULT 0 NOT NULL,
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE employment_print_placement (
              id INT AUTO_INCREMENT NOT NULL,
              kind VARCHAR(8) NOT NULL,
              link_x NUMERIC(5, 2) NOT NULL,
              link_y NUMERIC(5, 2) NOT NULL,
              square_size_cm NUMERIC(5, 2) NOT NULL,
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE home_customization (
              id INT AUTO_INCREMENT NOT NULL,
              signature_image_relative_path VARCHAR(512) DEFAULT NULL,
              background_image_relative_path VARCHAR(512) DEFAULT NULL,
              background_css_sanitized LONGTEXT DEFAULT NULL,
              signature_css_sanitized LONGTEXT DEFAULT NULL,
              quick_tile_style VARCHAR(32) NOT NULL,
              quick_tile_css_sanitized LONGTEXT DEFAULT NULL,
              dashboard_tile_icon_relative_path VARCHAR(512) DEFAULT NULL,
              storage_tile_icon_relative_path VARCHAR(512) DEFAULT NULL,
              site_favicon_relative_path VARCHAR(512) DEFAULT NULL,
              cv_antibot_threshold INT DEFAULT 50 NOT NULL,
              maintenance_mode_enabled TINYINT DEFAULT 0 NOT NULL,
              recruiter_visit_notification_enabled TINYINT DEFAULT 0 NOT NULL,
              site_colors_json LONGTEXT DEFAULT NULL,
              mail_templates_json LONGTEXT DEFAULT NULL,
              intro_title_css_sanitized LONGTEXT DEFAULT NULL,
              webcv_button_css_sanitized LONGTEXT DEFAULT NULL,
              webcv_button_css_hover_sanitized LONGTEXT DEFAULT NULL,
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE home_customization_translation (
              id INT AUTO_INCREMENT NOT NULL,
              locale VARCHAR(8) NOT NULL,
              intro_text LONGTEXT NOT NULL,
              customization_id INT NOT NULL,
              INDEX IDX_84B52900DE55AE3D (customization_id),
              UNIQUE INDEX uniq_home_customization_locale (customization_id, locale),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE home_quick_tile (
              id INT AUTO_INCREMENT NOT NULL,
              sort_order INT NOT NULL,
              link_url VARCHAR(2048) NOT NULL,
              open_in_new_tab TINYINT NOT NULL,
              icon_relative_path VARCHAR(512) DEFAULT NULL,
              enabled TINYINT NOT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME NOT NULL,
              customization_id INT NOT NULL,
              INDEX IDX_CBBB6DC9DE55AE3D (customization_id),
              INDEX idx_home_quick_tile_customization_sort (customization_id, sort_order),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE home_quick_tile_translation (
              id INT AUTO_INCREMENT NOT NULL,
              locale VARCHAR(8) NOT NULL,
              label VARCHAR(128) NOT NULL,
              tile_id INT NOT NULL,
              INDEX IDX_F01D2374638AF48B (tile_id),
              UNIQUE INDEX uniq_home_quick_tile_locale (tile_id, locale),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE login_totp_challenge (
              id INT AUTO_INCREMENT NOT NULL,
              identity VARCHAR(190) NOT NULL,
              code_hash VARCHAR(255) NOT NULL,
              expires_at DATETIME NOT NULL,
              created_at DATETIME NOT NULL,
              consumed_at DATETIME DEFAULT NULL,
              last_sent_at DATETIME NOT NULL,
              resend_count INT DEFAULT 0 NOT NULL,
              INDEX idx_login_totp_identity (identity),
              INDEX idx_login_totp_expires (expires_at),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE password_reset_request (
              id INT AUTO_INCREMENT NOT NULL,
              user_id INT NOT NULL,
              token VARCHAR(255) NOT NULL,
              expires_at DATETIME NOT NULL,
              consumed TINYINT NOT NULL,
              UNIQUE INDEX UNIQ_C5D0A95A5F37A13B (token),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE profile_email_change_request (
              id INT AUTO_INCREMENT NOT NULL,
              user_id INT NOT NULL,
              new_email VARCHAR(190) NOT NULL,
              expires_at DATETIME NOT NULL,
              consumed TINYINT NOT NULL,
              created_at DATETIME NOT NULL,
              INDEX idx_profile_email_change_active (user_id, consumed, expires_at),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE tracked_company (
              id INT AUTO_INCREMENT NOT NULL,
              code VARCHAR(12) NOT NULL,
              name VARCHAR(255) NOT NULL,
              name_normalized VARCHAR(255) NOT NULL,
              country_code VARCHAR(2) DEFAULT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME NOT NULL,
              archived_at DATETIME DEFAULT NULL,
              recruiter_name VARCHAR(255) DEFAULT NULL,
              address_line1 VARCHAR(255) DEFAULT NULL,
              address_line2 VARCHAR(255) DEFAULT NULL,
              address_postal_code VARCHAR(32) DEFAULT NULL,
              address_city VARCHAR(128) DEFAULT NULL,
              phone VARCHAR(64) DEFAULT NULL,
              email VARCHAR(255) DEFAULT NULL,
              cv_document_variant_id INT DEFAULT NULL,
              lm_document_variant_id INT DEFAULT NULL,
              INDEX IDX_C73A4F58E4736223 (cv_document_variant_id),
              INDEX IDX_C73A4F582DE1E31A (lm_document_variant_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE trusted_device (
              id INT AUTO_INCREMENT NOT NULL,
              user_id INT NOT NULL,
              device_fingerprint VARCHAR(255) NOT NULL,
              trusted_until DATETIME NOT NULL,
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user_deletion_snapshot (
              id INT AUTO_INCREMENT NOT NULL,
              target_user_id INT NOT NULL,
              ciphertext LONGTEXT NOT NULL,
              signature VARCHAR(255) NOT NULL,
              algo VARCHAR(32) NOT NULL,
              key_version VARCHAR(64) NOT NULL,
              created_at DATETIME NOT NULL,
              restored_at DATETIME DEFAULT NULL,
              purged_at DATETIME DEFAULT NULL,
              status VARCHAR(32) NOT NULL,
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user_invitation_token (
              id INT AUTO_INCREMENT NOT NULL,
              user_id INT NOT NULL,
              email VARCHAR(180) NOT NULL,
              token_hash VARCHAR(64) NOT NULL,
              invited_by_user_id INT NOT NULL,
              created_at DATETIME NOT NULL,
              expires_at DATETIME NOT NULL,
              consumed_at DATETIME DEFAULT NULL,
              UNIQUE INDEX UNIQ_4EE977C7B3BC57DA (token_hash),
              INDEX idx_invitation_user (user_id),
              INDEX idx_invitation_expires (expires_at),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              bug_report
            ADD
              CONSTRAINT FK_F6F2DC7ADF3D6D95 FOREIGN KEY (reporter_user_id) REFERENCES app_user (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              bug_report
            ADD
              CONSTRAINT FK_F6F2DC7AAC78F73B FOREIGN KEY (resolved_by_user_id) REFERENCES app_user (id) ON DELETE
            SET
              NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              bug_report
            ADD
              CONSTRAINT FK_F6F2DC7AACEC367 FOREIGN KEY (archived_by_user_id) REFERENCES app_user (id) ON DELETE
            SET
              NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              company_cv_section_override
            ADD
              CONSTRAINT FK_FCEBB17DB5033087 FOREIGN KEY (tracked_company_id) REFERENCES tracked_company (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              company_cv_visit
            ADD
              CONSTRAINT FK_D4ED2C4B979B1AD6 FOREIGN KEY (company_id) REFERENCES tracked_company (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              company_recruiter_visit_notification
            ADD
              CONSTRAINT FK_10FDEA3D979B1AD6 FOREIGN KEY (company_id) REFERENCES tracked_company (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              company_recruiter_visit_notification
            ADD
              CONSTRAINT FK_10FDEA3D75FA0FF2 FOREIGN KEY (visit_id) REFERENCES company_cv_visit (id) ON DELETE
            SET
              NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              cv_connection_log
            ADD
              CONSTRAINT FK_FD2C995D979B1AD6 FOREIGN KEY (company_id) REFERENCES tracked_company (id) ON DELETE
            SET
              NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              cv_connection_log
            ADD
              CONSTRAINT FK_FD2C995D75FA0FF2 FOREIGN KEY (visit_id) REFERENCES company_cv_visit (id) ON DELETE
            SET
              NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              employment_document_locale_asset
            ADD
              CONSTRAINT FK_188B30BB3B69A9AF FOREIGN KEY (variant_id) REFERENCES employment_document_variant (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              home_customization_translation
            ADD
              CONSTRAINT FK_84B52900DE55AE3D FOREIGN KEY (customization_id) REFERENCES home_customization (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              home_quick_tile
            ADD
              CONSTRAINT FK_CBBB6DC9DE55AE3D FOREIGN KEY (customization_id) REFERENCES home_customization (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              home_quick_tile_translation
            ADD
              CONSTRAINT FK_F01D2374638AF48B FOREIGN KEY (tile_id) REFERENCES home_quick_tile (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              tracked_company
            ADD
              CONSTRAINT FK_C73A4F58E4736223 FOREIGN KEY (cv_document_variant_id) REFERENCES employment_document_variant (id) ON DELETE
            SET
              NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              tracked_company
            ADD
              CONSTRAINT FK_C73A4F582DE1E31A FOREIGN KEY (lm_document_variant_id) REFERENCES employment_document_variant (id) ON DELETE
            SET
              NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bug_report DROP FOREIGN KEY FK_F6F2DC7ADF3D6D95');
        $this->addSql('ALTER TABLE bug_report DROP FOREIGN KEY FK_F6F2DC7AAC78F73B');
        $this->addSql('ALTER TABLE bug_report DROP FOREIGN KEY FK_F6F2DC7AACEC367');
        $this->addSql('ALTER TABLE company_cv_section_override DROP FOREIGN KEY FK_FCEBB17DB5033087');
        $this->addSql('ALTER TABLE company_cv_visit DROP FOREIGN KEY FK_D4ED2C4B979B1AD6');
        $this->addSql('ALTER TABLE company_recruiter_visit_notification DROP FOREIGN KEY FK_10FDEA3D979B1AD6');
        $this->addSql('ALTER TABLE company_recruiter_visit_notification DROP FOREIGN KEY FK_10FDEA3D75FA0FF2');
        $this->addSql('ALTER TABLE cv_connection_log DROP FOREIGN KEY FK_FD2C995D979B1AD6');
        $this->addSql('ALTER TABLE cv_connection_log DROP FOREIGN KEY FK_FD2C995D75FA0FF2');
        $this->addSql('ALTER TABLE employment_document_locale_asset DROP FOREIGN KEY FK_188B30BB3B69A9AF');
        $this->addSql('ALTER TABLE home_customization_translation DROP FOREIGN KEY FK_84B52900DE55AE3D');
        $this->addSql('ALTER TABLE home_quick_tile DROP FOREIGN KEY FK_CBBB6DC9DE55AE3D');
        $this->addSql('ALTER TABLE home_quick_tile_translation DROP FOREIGN KEY FK_F01D2374638AF48B');
        $this->addSql('ALTER TABLE tracked_company DROP FOREIGN KEY FK_C73A4F58E4736223');
        $this->addSql('ALTER TABLE tracked_company DROP FOREIGN KEY FK_C73A4F582DE1E31A');
        $this->addSql('DROP TABLE app_user');
        $this->addSql('DROP TABLE bug_report');
        $this->addSql('DROP TABLE company_cv_section_override');
        $this->addSql('DROP TABLE company_cv_visit');
        $this->addSql('DROP TABLE company_recruiter_visit_notification');
        $this->addSql('DROP TABLE cv_connection_log');
        $this->addSql('DROP TABLE cv_profile');
        $this->addSql('DROP TABLE employment_country');
        $this->addSql('DROP TABLE employment_document_locale_asset');
        $this->addSql('DROP TABLE employment_document_variant');
        $this->addSql('DROP TABLE employment_print_placement');
        $this->addSql('DROP TABLE home_customization');
        $this->addSql('DROP TABLE home_customization_translation');
        $this->addSql('DROP TABLE home_quick_tile');
        $this->addSql('DROP TABLE home_quick_tile_translation');
        $this->addSql('DROP TABLE login_totp_challenge');
        $this->addSql('DROP TABLE password_reset_request');
        $this->addSql('DROP TABLE profile_email_change_request');
        $this->addSql('DROP TABLE tracked_company');
        $this->addSql('DROP TABLE trusted_device');
        $this->addSql('DROP TABLE user_deletion_snapshot');
        $this->addSql('DROP TABLE user_invitation_token');
    }
}
