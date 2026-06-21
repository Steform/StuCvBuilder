<?php

declare(strict_types=1);

namespace App\Tests\Functional\Cv;

use App\Service\Security\CaptchaService;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * HTTP contract tests for CV captcha and attestation routes.
 */
final class CvAttestationCaptchaTest extends WebTestCase
{
    /**
     * @brief Create client and load captcha image to seed session code.
     *
     * @param void No input parameter.
     * @return KernelBrowser
     * @date 2026-05-19
     * @author Stephane H.
     */
    private function createClientWithCaptcha(): KernelBrowser
    {
        if (!extension_loaded('gd')) {
            self::markTestSkipped('GD extension is required for captcha image generation.');
        }

        $client = static::createClient();
        $client->request('GET', '/cv/captcha');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'image/png');

        return $client;
    }

    /**
     * @brief Load attestation form page on the same client session.
     *
     * @param KernelBrowser $client HTTP test client with session.
     * @return Crawler
     * @date 2026-05-19
     * @author Stephane H.
     */
    private function requestAttestationForm(KernelBrowser $client): Crawler
    {
        return $client->request('GET', '/cv/attestation?return_to=/cv/');
    }

    /**
     * @brief GET /cv/captcha returns PNG (session seeded via client).
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function testCaptchaRouteReturnsPng(): void
    {
        $client = $this->createClientWithCaptcha();
        self::assertGreaterThan(0, strlen((string) $client->getResponse()->getContent()));
    }

    /**
     * @brief GET /cv/attestation renders captcha challenge form.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function testAttestationGetRendersCaptchaForm(): void
    {
        $client = static::createClient();
        $crawler = $this->requestAttestationForm($client);

        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filterXpath('//*[@id="captcha_code"]')->count());
        self::assertGreaterThan(0, $crawler->filterXpath('//*[@id="captcha-img"]')->count());
    }

    /**
     * @brief Wrong captcha does not issue attestation.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function testAttestationPostWithWrongCodeDoesNotIssueAttestation(): void
    {
        $client = $this->createClientWithCaptcha();
        $crawler = $this->requestAttestationForm($client);
        self::assertResponseIsSuccessful();

        $form = $crawler->filterXpath('//form')->form();
        $form['captcha_code'] = '0000';
        $client->submit($form);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('captcha_code', (string) $client->getResponse()->getContent());
    }

    /**
     * @brief Valid captcha issues attestation and redirects to return_to.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function testAttestationPostWithValidCodeIssuesAttestationAndRedirects(): void
    {
        $client = $this->createClientWithCaptcha();
        $session = $client->getRequest()->getSession();
        $expectedCode = (string) $session->get(CaptchaService::SESSION_KEY, '');
        self::assertMatchesRegularExpression('/^\d{4}$/', $expectedCode);

        $crawler = $this->requestAttestationForm($client);
        $form = $crawler->filterXpath('//form')->form();
        $form['captcha_code'] = $expectedCode;
        $client->submit($form);

        self::assertResponseRedirects('/cv/');
        $session->save();
        self::assertTrue($session->has('cv_bot_attestation'));
    }
}
