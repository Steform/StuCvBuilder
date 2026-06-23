<?php

namespace App\Tests\Unit\Security;

use App\Service\Security\CaptchaService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class CaptchaServiceTest extends TestCase
{
    /**
     * @brief Build CaptchaService with in-memory session.
     *
     * @return CaptchaService
     * @date 2026-05-19
     * @author Stephane H.
     */
    private function createService(): CaptchaService
    {
        $session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($session);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        return new CaptchaService($requestStack);
    }

    /**
     * @brief Ensure verifyCaptcha matches case-insensitively.
     *
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function testVerifyCaptchaReturnsTrueOnCorrectCode(): void
    {
        $service = $this->createService();
        $service->storeCaptchaCode('AbC123');
        $request = new Request([], ['captcha_code' => 'abc123']);

        self::assertTrue($service->verifyCaptcha($request));
    }

    /**
     * @brief Ensure verifyCaptcha rejects wrong code.
     *
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function testVerifyCaptchaReturnsFalseOnIncorrectCode(): void
    {
        $service = $this->createService();
        $service->storeCaptchaCode('AbC123');
        $request = new Request([], ['captcha_code' => 'wrong']);

        self::assertFalse($service->verifyCaptcha($request));
    }
}
