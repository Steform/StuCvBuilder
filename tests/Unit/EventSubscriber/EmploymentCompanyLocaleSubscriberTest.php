<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\EventSubscriber\EmploymentCompanyLocaleSubscriber;
use App\Service\Cv\CvAccessSessionService;
use App\Service\Employment\EmploymentCountryPresentationLocaleResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @brief Unit tests for company-country locale forcing on public CV routes.
 */
final class EmploymentCompanyLocaleSubscriberTest extends TestCase
{
    /**
     * @brief Company presentation locale is applied when no lang override exists.
     *
     * @return void
     * @date 2026-10-05
     * @author Stephane H.
     */
    public function testForcesCountryPresentationLocaleWithoutOverride(): void
    {
        $subscriber = $this->createSubscriber('lt', 'marino');
        $request = Request::create('/cv', 'GET', ['format' => 'marino']);
        $session = new Session(new MockArraySessionStorage());
        $session->set('_locale', 'fr');
        $request->setSession($session);
        $request->setLocale('fr');

        $subscriber->onKernelRequest($this->createRequestEvent($request));

        self::assertSame('lt', $request->getLocale());
        self::assertSame('fr', $request->getSession()->get('_locale'));
    }

    /**
     * @brief Explicit lang query must win and persist as a CV locale override.
     *
     * @return void
     * @date 2026-10-05
     * @author Stephane H.
     */
    public function testLangQueryPersistsOverrideAndSkipsCountryLocale(): void
    {
        $subscriber = $this->createSubscriber('lt', 'marino');
        $request = Request::create('/cv', 'GET', ['format' => 'marino', 'lang' => 'en']);
        $session = new Session(new MockArraySessionStorage());
        $session->set('_locale', 'fr');
        $request->setSession($session);
        $request->setLocale('en');

        $subscriber->onKernelRequest($this->createRequestEvent($request));

        self::assertSame('en', $request->getLocale());
        self::assertSame('en', $request->getSession()->get(EmploymentCompanyLocaleSubscriber::SESSION_LOCALE_OVERRIDE));
        self::assertSame('fr', $request->getSession()->get('_locale'));
    }

    /**
     * @brief Session override must keep the chosen language on later CV navigations.
     *
     * @return void
     * @date 2026-10-05
     * @author Stephane H.
     */
    public function testSessionOverrideKeepsChosenLocale(): void
    {
        $subscriber = $this->createSubscriber('lt', 'marino');
        $request = Request::create('/cv', 'GET', ['format' => 'marino']);
        $session = new Session(new MockArraySessionStorage());
        $session->set('_locale', 'fr');
        $session->set(EmploymentCompanyLocaleSubscriber::SESSION_LOCALE_OVERRIDE, 'en');
        $request->setSession($session);
        $request->setLocale('lt');

        $subscriber->onKernelRequest($this->createRequestEvent($request));

        self::assertSame('en', $request->getLocale());
        self::assertSame('fr', $request->getSession()->get('_locale'));
    }

    /**
     * @brief Build subscriber with stubbed format and presentation locale resolution.
     *
     * @param string $presentationLocale Country presentation locale.
     * @param string $formatCode Expected company format code.
     * @return EmploymentCompanyLocaleSubscriber
     * @date 2026-10-05
     * @author Stephane H.
     */
    private function createSubscriber(string $presentationLocale, string $formatCode): EmploymentCompanyLocaleSubscriber
    {
        $presentationLocaleResolver = $this->createMock(EmploymentCountryPresentationLocaleResolver::class);
        $presentationLocaleResolver->method('resolveForFormatCode')->willReturnCallback(
            static fn (string $code): ?string => $code === $formatCode ? $presentationLocale : null,
        );

        $cvAccessSessionService = $this->createMock(CvAccessSessionService::class);
        $cvAccessSessionService->method('resolveTargetFormatCode')->willReturnCallback(
            static function (string $rawFormat) use ($formatCode): string {
                $rawFormat = trim($rawFormat);

                return $rawFormat !== '' ? $rawFormat : $formatCode;
            },
        );

        return new EmploymentCompanyLocaleSubscriber($presentationLocaleResolver, $cvAccessSessionService);
    }

    /**
     * @brief Build a main RequestEvent for the subscriber.
     *
     * @param Request $request HTTP request under test.
     * @return RequestEvent
     * @date 2026-10-05
     * @author Stephane H.
     */
    private function createRequestEvent(Request $request): RequestEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);

        return new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
    }
}
