<?php

declare(strict_types=1);

namespace App\Tests\Functional\Cv;

use App\Service\Cv\CvBotSignalEvaluator;
use App\Service\Security\AntiBotScoringService;
use App\Service\Site\SiteConfigurationService;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * UX contract tests for phased CV public access gate (/cv/access).
 */
final class CvAccessGateUxTest extends WebTestCase
{
    /**
     * @brief Extract CSRF token value from access gate HTML.
     *
     * @param string $html Rendered gate page HTML.
     * @return string CSRF token value.
     * @date 2026-05-19
     * @author Stephane H.
     */
    private function extractCsrfToken(string $html): string
    {
        if (preg_match('/name="_csrf_token"[^>]*value="([^"]+)"/', $html, $matches) === 1) {
            return $matches[1];
        }
        if (preg_match('/value="([^"]+)"[^>]*name="_csrf_token"/', $html, $matches) === 1) {
            return $matches[1];
        }

        self::fail('CSRF token input not found on access gate form.');
    }

    /**
     * @brief Load access gate page and return client plus HTML body.
     *
     * @param string $target Safe redirect target query value.
     * @param array<string, string> $query Additional query parameters.
     * @return array{client: KernelBrowser, html: string, csrfToken: string}
     * @date 2026-05-19
     * @author Stephane H.
     */
    private function requestAccessGate(string $target = '/cv/', array $query = []): array
    {
        $client = static::createClient();
        $query['target'] = $target;
        $client->request('GET', '/cv/access', $query);
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();

        return [
            'client' => $client,
            'html' => $html,
            'csrfToken' => $this->extractCsrfToken($html),
        ];
    }

    /**
     * @brief GET /cv/access shows checking phase and hides captcha by default.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function testAccessGetShowsCheckingPhaseByDefault(): void
    {
        $gate = $this->requestAccessGate();

        self::assertStringContainsString('data-cv-access-phase="checking"', $gate['html']);
        self::assertStringContainsString('data-initial-phase="checking"', $gate['html']);
        self::assertStringContainsString('data-cv-access-phase="captcha"', $gate['html']);
        self::assertStringContainsString('data-src=', $gate['html']);
        self::assertDoesNotMatchRegularExpression('/class="[^"]*cv-access__captcha-image[^"]*"[^>]*src="/', $gate['html']);
    }

    /**
     * @brief Build signal evaluator with fixed threshold for gate POST scoring tests.
     *
     * @param int $threshold Minimum score for eligibility.
     * @return CvBotSignalEvaluator
     * @date 2026-05-19
     * @author Stephane H.
     */
    private function createSignalEvaluator(int $threshold = 50): CvBotSignalEvaluator
    {
        $siteConfiguration = $this->createMock(SiteConfigurationService::class);
        $siteConfiguration->method('getCvAntibotThreshold')->willReturn($threshold);

        return new CvBotSignalEvaluator(new AntiBotScoringService($siteConfiguration));
    }

    /**
     * @brief Strong behavioural POST payload passes antibot threshold (auto-check success path).
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function testStrongSignalsPassThreshold(): void
    {
        $request = Request::create('/cv/access', 'POST', [
            'pointer_moves' => '15',
            'focus_events' => '2',
            'time_on_page_ms' => '5000',
            'webdriver' => '0',
        ]);
        $request->headers->set('User-Agent', 'Mozilla/5.0 Test');

        $result = $this->createSignalEvaluator()->evaluateRequest($request);

        self::assertTrue($result['eligibleForCounting']);
    }

    /**
     * @brief Weak behavioural POST payload fails threshold (captcha phase path).
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function testWeakSignalsFailThreshold(): void
    {
        $request = Request::create('/cv/access', 'POST', [
            'pointer_moves' => '0',
            'focus_events' => '0',
            'time_on_page_ms' => '0',
            'webdriver' => '1',
        ]);

        $result = $this->createSignalEvaluator()->evaluateRequest($request);

        self::assertFalse($result['eligibleForCounting']);
    }

    /**
     * @brief GET with phase=captcha shows captcha panel immediately.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function testAccessGetWithPhaseCaptchaShowsCaptcha(): void
    {
        $gate = $this->requestAccessGate('/cv/', ['phase' => 'captcha']);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('data-initial-phase="captcha"', $gate['html']);
        self::assertStringContainsString('id="captcha-code"', $gate['html']);
        self::assertStringContainsString('name="captcha_code"', $gate['html']);
    }

    /**
     * @brief Debug captcha phase shows technical score when dev_score query params are present.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function testAccessGetWithDevScoreQueryShowsDevBanner(): void
    {
        $gate = $this->requestAccessGate('/cv/', [
            'phase' => 'captcha',
            'dev_score' => '35',
            'dev_threshold' => '50',
        ]);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('cv-access__dev-score', $gate['html']);
        self::assertStringContainsString('35', $gate['html']);
        self::assertStringContainsString('50', $gate['html']);
    }
}
