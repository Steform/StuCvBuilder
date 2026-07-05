<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Service\Locale\LocaleLegacyDataRewriter;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * @brief Migrate legacy site locale code `no` to canonical bokmål `nb`.
 *
 * @date 2026-07-05
 * @author Stephane H.
 */
final class Version20260705220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migrate legacy Norwegian site locale code no to canonical bokmål nb across SQL and JSON payloads.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE home_customization_translation SET locale = 'nb' WHERE locale = 'no'");
        $this->addSql("UPDATE home_quick_tile_translation SET locale = 'nb' WHERE locale = 'no'");
        $this->addSql("UPDATE employment_document_locale_asset SET locale = 'nb' WHERE locale = 'no'");
        $this->addSql("UPDATE employment_country SET presentation_locale = 'nb' WHERE presentation_locale = 'no'");
        $this->addSql("UPDATE bug_report SET locale = 'nb' WHERE locale = 'no'");
    }

    public function postUp(Schema $schema): void
    {
        $rewriter = new LocaleLegacyDataRewriter();
        $this->rewriteJsonColumn('cv_profile', 'content_json', $rewriter);
        $this->rewriteJsonColumn('home_customization', 'mail_templates_json', $rewriter);
        $this->rewriteJsonColumn('company_cv_section_override', 'content_json', $rewriter);
        $this->rewriteLocaleConfigurationFile($rewriter);
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Locale migration no to nb cannot be reversed safely.');
    }

    /**
     * @brief Rewrite legacy locale keys inside a nullable JSON text column.
     *
     * @param string $table Table name.
     * @param string $column JSON column name.
     * @param LocaleLegacyDataRewriter $rewriter Legacy locale rewriter.
     * @return void
     * @date 2026-07-05
     * @author Stephane H.
     */
    private function rewriteJsonColumn(string $table, string $column, LocaleLegacyDataRewriter $rewriter): void
    {
        $rows = $this->connection->fetchAllAssociative(
            sprintf('SELECT id, %s AS payload FROM %s WHERE %s IS NOT NULL AND %s != \'\'', $column, $table, $column, $column)
        );

        foreach ($rows as $row) {
            $id = $row['id'] ?? null;
            $payload = $row['payload'] ?? null;
            if (!is_numeric($id) || !is_string($payload) || trim($payload) === '') {
                continue;
            }

            $decoded = json_decode($payload, true);
            if (!is_array($decoded)) {
                continue;
            }

            $rewritten = $rewriter->rewriteStructure($decoded);
            $encoded = json_encode($rewritten, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

            $this->connection->update($table, [$column => $encoded], ['id' => (int) $id]);
        }
    }

    /**
     * @brief Rewrite persisted locale configuration file when present.
     *
     * @param LocaleLegacyDataRewriter $rewriter Legacy locale rewriter.
     * @return void
     * @date 2026-07-05
     * @author Stephane H.
     */
    private function rewriteLocaleConfigurationFile(LocaleLegacyDataRewriter $rewriter): void
    {
        $configPath = dirname(__DIR__).'/var/config/locale_configuration.json';
        if (!is_file($configPath)) {
            return;
        }

        $raw = file_get_contents($configPath);
        if (!is_string($raw) || trim($raw) === '') {
            return;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return;
        }

        $rewritten = $rewriter->rewriteLocaleConfiguration($decoded);
        if ($rewritten['active_locales'] === [] || $rewritten['default_locale'] === '') {
            return;
        }

        file_put_contents(
            $configPath,
            (string) json_encode([
                'active_locales' => $rewritten['active_locales'],
                'default_locale' => $rewritten['default_locale'],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }
}
