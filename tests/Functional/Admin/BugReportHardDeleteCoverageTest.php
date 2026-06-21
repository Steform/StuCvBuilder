<?php

namespace App\Tests\Functional\Admin;

use PHPUnit\Framework\TestCase;

class BugReportHardDeleteCoverageTest extends TestCase
{
    /**
     * @brief Ensure bug report migration enforces cascade delete for reporter user.
     * @param void No input parameter.
     * @return void
     * @date 2026-05-06
     * @author Stephane H.
     */
    public function testMigrationContainsReporterCascadeDeleteConstraint(): void
    {
        $path = dirname(__DIR__, 3).'/migrations/Version20260621080024.php';
        $source = is_readable($path) ? (string) file_get_contents($path) : '';

        self::assertStringContainsString('FOREIGN KEY (reporter_user_id)', $source);
        self::assertStringContainsString('ON DELETE CASCADE', $source);
    }
}
