<?php

namespace App\Tests\Unit\Security;

use App\Service\Http\RequestSessionResolver;
use App\Service\Security\CvBotAttestationService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class CvBotAttestationServiceTest extends TestCase
{
    /**
     * @brief Build attestation service with mock session.
     *
     * @return CvBotAttestationService
     * @date 2026-05-19
     * @author Stephane H.
     */
    private function createService(): CvBotAttestationService
    {
        $session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($session);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        return new CvBotAttestationService(new RequestSessionResolver($requestStack), 'test-secret', 3600);
    }

    /**
     * @brief Signals attestation should be valid and countable at high score.
     *
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function testIssueFromSignalsIsValidAndCountable(): void
    {
        $service = $this->createService();
        $service->issueFromSignals(72);

        self::assertTrue($service->isValid());
        self::assertSame(72, $service->getScore());
        self::assertTrue($service->isCountableForKpi(50));
    }

    /**
     * @brief Captcha attestation grants access and company visit eligibility.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testIssueFromCaptchaIsValidAndCountable(): void
    {
        $service = $this->createService();
        $service->issueFromCaptcha();

        self::assertTrue($service->isValid());
        self::assertTrue($service->isCountableForKpi(50));
        self::assertTrue($service->hasValidGateAttestation());
    }
}
