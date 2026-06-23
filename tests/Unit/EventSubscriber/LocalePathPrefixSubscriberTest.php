<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\EventSubscriber\LocalePathPrefixSubscriber;
use App\Service\Locale\LocaleConfigurationService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @brief Unit tests for {@see LocalePathPrefixSubscriber}.
 * @date 2026-06-21
 * @author Stephane H.
 */
final class LocalePathPrefixSubscriberTest extends TestCase
{
    /**
     * @brief Supported locale prefix must rewrite PATH_INFO and set request locale.
     *
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testStripsLocalePrefixFromCvPath(): void
    {
        $localeConfigurationService = $this->createMock(LocaleConfigurationService::class);
        $localeConfigurationService->method('getConfiguration')->willReturn([
            'activeLocales' => ['fr', 'en'],
            'defaultLocale' => 'fr',
        ]);

        $subscriber = new LocalePathPrefixSubscriber($localeConfigurationService, ['fr', 'en']);
        $request = Request::create('/fr/cv/');
        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        self::assertSame('/cv/', $request->getPathInfo());
        self::assertSame('fr', $request->getLocale());
        self::assertTrue($request->attributes->get(LocalePathPrefixSubscriber::LOCALE_FROM_PATH_ATTRIBUTE));
    }

    /**
     * @brief Home path with locale prefix must map to root PATH_INFO.
     *
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testStripsLocalePrefixFromHomePath(): void
    {
        $localeConfigurationService = $this->createMock(LocaleConfigurationService::class);
        $localeConfigurationService->method('getConfiguration')->willReturn([
            'activeLocales' => ['fr', 'en'],
            'defaultLocale' => 'fr',
        ]);

        $subscriber = new LocalePathPrefixSubscriber($localeConfigurationService, ['fr', 'en']);
        $request = Request::create('/en/');
        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        self::assertSame('/', $request->getPathInfo());
        self::assertSame('en', $request->getLocale());
    }

    /**
     * @brief Unknown two-letter prefix must leave PATH_INFO unchanged.
     *
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testIgnoresUnsupportedLocalePrefix(): void
    {
        $localeConfigurationService = $this->createMock(LocaleConfigurationService::class);
        $localeConfigurationService->method('getConfiguration')->willReturn([
            'activeLocales' => ['fr', 'en'],
            'defaultLocale' => 'fr',
        ]);

        $subscriber = new LocalePathPrefixSubscriber($localeConfigurationService, ['fr', 'en']);
        $request = Request::create('/xx/cv/');
        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        self::assertSame('/xx/cv/', $request->getPathInfo());
        self::assertFalse($request->attributes->get(LocalePathPrefixSubscriber::LOCALE_FROM_PATH_ATTRIBUTE, false));
    }
}
