<?php

namespace App\Tests\Unit\Security;

use App\Service\Security\CaptchaImageGenerator;
use App\Service\Security\CaptchaService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Ensures CV captcha image generation matches our multimedia stack contract.
 */
class CaptchaImageGeneratorTest extends TestCase
{
    /**
     * @brief Build generator with in-memory session.
     *
     * @return array{0: CaptchaImageGenerator, 1: Session}
     * @date 2026-05-19
     * @author Stephane H.
     */
    private function createGenerator(): array
    {
        $session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($session);
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $captchaService = new CaptchaService($requestStack);

        return [new CaptchaImageGenerator($captchaService), $session];
    }

    /**
     * @brief PNG response stores a four-digit numeric code in session (multimedia parity).
     *
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function testGenerateResponseStoresFourDigitCodeAndPngHeaders(): void
    {
        if (!extension_loaded('gd')) {
            self::markTestSkipped('GD extension is required for captcha image generation.');
        }

        [$generator, $session] = $this->createGenerator();
        $response = $generator->generateResponse();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('image/png', $response->headers->get('Content-Type'));
        self::assertStringContainsString('no-cache', (string) $response->headers->get('Cache-Control'));
        self::assertNotSame('', $response->getContent());

        $code = (string) $session->get(CaptchaService::SESSION_KEY, '');
        self::assertMatchesRegularExpression('/^\d{4}$/', $code);
        self::assertGreaterThanOrEqual(1000, (int) $code);
        self::assertLessThanOrEqual(9999, (int) $code);
    }
}
