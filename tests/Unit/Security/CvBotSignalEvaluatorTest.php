<?php

namespace App\Tests\Unit\Security;

use App\Service\Security\CvBotSignalEvaluator;
use PHPUnit\Framework\TestCase;

class CvBotSignalEvaluatorTest extends TestCase
{
    /**
     * @brief Human-like signals should pass threshold 50.
     *
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function testHumanSignalsPassThreshold(): void
    {
        $evaluator = new CvBotSignalEvaluator(50);
        $result = $evaluator->evaluate([
            'elapsedMs' => 2500,
            'pointerMoves' => 12,
            'scrollEvents' => 2,
            'keyEvents' => 1,
            'honeypot' => '',
            'webdriver' => false,
        ]);

        self::assertTrue($result['passed']);
        self::assertFalse($result['requireCaptcha']);
        self::assertGreaterThanOrEqual(50, $result['technicalScore']);
    }

    /**
     * @brief Honeypot triggers zero score and captcha requirement.
     *
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function testHoneypotFailsImmediately(): void
    {
        $evaluator = new CvBotSignalEvaluator(50);
        $result = $evaluator->evaluate([
            'elapsedMs' => 5000,
            'pointerMoves' => 50,
            'honeypot' => 'spam',
        ]);

        self::assertSame(0, $result['technicalScore']);
        self::assertFalse($result['passed']);
        self::assertTrue($result['requireCaptcha']);
    }
}
