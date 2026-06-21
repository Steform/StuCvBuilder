<?php

namespace App\Service\Security;

/**
 * Server-side behavioural signal scoring for public CV visitors.
 */
class CvBotSignalEvaluator
{
    /**
     * @brief Build signal evaluator with anti-bot threshold.
     *
     * @param int $threshold Minimum score required to pass without captcha.
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function __construct(
        private readonly int $threshold = 50,
    ) {
    }

    /**
     * @brief Evaluate client behavioural signals and return score metadata.
     *
     * @param array<string, mixed> $signals Raw signal payload from the browser.
     * @return array{technicalScore: int, threshold: int, passed: bool, requireCaptcha: bool}
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function evaluate(array $signals): array
    {
        if ($this->isHoneypotTriggered($signals)) {
            return $this->buildResult(0);
        }

        $score = 25;
        $elapsedMs = max(0, (int) ($signals['elapsedMs'] ?? 0));
        $pointerMoves = max(0, (int) ($signals['pointerMoves'] ?? 0));
        $scrollEvents = max(0, (int) ($signals['scrollEvents'] ?? 0));
        $keyEvents = max(0, (int) ($signals['keyEvents'] ?? 0));
        $webdriver = (bool) ($signals['webdriver'] ?? false);

        if ($elapsedMs >= 2000) {
            $score += 20;
        } elseif ($elapsedMs >= 800) {
            $score += 10;
        } elseif ($elapsedMs < 400) {
            $score -= 25;
        }

        if ($pointerMoves >= 8) {
            $score += 20;
        } elseif ($pointerMoves >= 3) {
            $score += 10;
        }

        if ($scrollEvents > 0) {
            $score += 15;
        }

        if ($keyEvents > 0) {
            $score += 10;
        }

        if ($webdriver) {
            $score -= 35;
        }

        return $this->buildResult(max(0, min(100, $score)));
    }

    /**
     * @brief Detect honeypot field abuse.
     *
     * @param array<string, mixed> $signals Raw signal payload.
     * @return bool True when honeypot was filled.
     * @date 2026-05-19
     * @author Stephane H.
     */
    private function isHoneypotTriggered(array $signals): bool
    {
        $honeypot = trim((string) ($signals['honeypot'] ?? ''));

        return $honeypot !== '';
    }

    /**
     * @brief Build normalized evaluation result array.
     *
     * @param int $technicalScore Computed score between 0 and 100.
     * @return array{technicalScore: int, threshold: int, passed: bool, requireCaptcha: bool}
     * @date 2026-05-19
     * @author Stephane H.
     */
    private function buildResult(int $technicalScore): array
    {
        $passed = $technicalScore >= $this->threshold;

        return [
            'technicalScore' => $technicalScore,
            'threshold' => $this->threshold,
            'passed' => $passed,
            'requireCaptcha' => !$passed,
        ];
    }
}
