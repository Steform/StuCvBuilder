<?php

declare(strict_types=1);

namespace App\Tests\PHPUnit;

use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;

/**
 * @brief Registers PHPUnit event subscribers that stream failures/errors to debug-edb78d.log (NDJSON).
 *
 * @date 2026-05-02
 * @author Stephane H.
 */
final class CursorAgentPhpunitLogExtension implements Extension
{
    /**
     * @brief Wire subscribers so each run produces agent-readable NDJSON at the project log path.
     *
     * @param Configuration $configuration PHPUnit configuration (unused).
     * @param Facade $facade Extension facade for subscriber registration.
     * @param ParameterCollection $parameters Extension parameters (unused).
     *
     * @return void
     *
     * @date 2026-05-02
     * @author Stephane H.
     */
    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        $sessionId = $_SERVER['CURSOR_DEBUG_SESSION_ID'] ?? $_ENV['CURSOR_DEBUG_SESSION_ID'] ?? 'edb78d';
        $projectRoot = dirname(__DIR__, 2);
        $logPath = $_SERVER['CURSOR_AGENT_LOG_FILE'] ?? $_ENV['CURSOR_AGENT_LOG_FILE'] ?? ($projectRoot.'/debug-edb78d.log');

        $buffer = new CursorAgentPhpunitRunBuffer();

        $facade->registerSubscriber(new CursorAgentPhpunitStartedSubscriber($logPath, $sessionId, $buffer));
        $facade->registerSubscriber(new CursorAgentPhpunitFailedSubscriber($logPath, $sessionId, $buffer));
        $facade->registerSubscriber(new CursorAgentPhpunitErroredSubscriber($logPath, $sessionId, $buffer));
        $facade->registerSubscriber(new CursorAgentPhpunitFinishedSubscriber($logPath, $sessionId, $buffer));
    }
}
