<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\Locale\LocaleConfigurationService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @brief Strip optional locale path prefixes (/fr/, /en/cv/) before routing and set request locale.
 */
final class LocalePathPrefixSubscriber implements EventSubscriberInterface
{
    public const LOCALE_FROM_PATH_ATTRIBUTE = '_locale_from_path_prefix';

    /**
     * @brief Build locale path prefix subscriber.
     *
     * @param LocaleConfigurationService $localeConfigurationService Runtime locale configuration.
     * @param list<string> $supportedLocales Fallback supported locale codes.
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function __construct(
        private readonly LocaleConfigurationService $localeConfigurationService,
        private readonly array $supportedLocales = ['fr', 'en', 'de', 'lt', 'no'],
    ) {
    }

    /**
     * @brief Rewrite PATH_INFO and locale when a supported two-letter prefix is present.
     *
     * @param RequestEvent $event Kernel request event.
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $pathInfo = $request->getPathInfo();
        if (!preg_match('#^/([a-z]{2})(/|$)#', $pathInfo, $matches)) {
            return;
        }

        $candidateLocale = $matches[1];
        $activeLocales = $this->resolveActiveLocales();
        if (!in_array($candidateLocale, $activeLocales, true)) {
            return;
        }

        $strippedPath = substr($pathInfo, 3);
        if ($strippedPath === '' || $strippedPath === false) {
            $strippedPath = '/';
        } elseif (!str_starts_with($strippedPath, '/')) {
            $strippedPath = '/'.$strippedPath;
        }

        $requestUri = (string) $request->server->get('REQUEST_URI', $pathInfo);
        $queryString = parse_url($requestUri, PHP_URL_QUERY);
        $rewrittenRequestUri = $strippedPath.(
            is_string($queryString) && $queryString !== '' ? '?'.$queryString : ''
        );

        $request->server->set('REQUEST_URI', $rewrittenRequestUri);
        $request->server->set('PATH_INFO', $strippedPath);
        $request->initialize(
            $request->query->all(),
            $request->request->all(),
            $request->attributes->all(),
            $request->cookies->all(),
            $request->files->all(),
            $request->server->all(),
            $request->getContent(),
        );
        $request->setLocale($candidateLocale);
        $request->attributes->set(self::LOCALE_FROM_PATH_ATTRIBUTE, true);

        if ($request->hasSession()) {
            $request->getSession()->set('_locale', $candidateLocale);
        }
    }

    /**
     * @brief Register subscriber with priority above the router listener.
     *
     * @return array<string, array<int|string, string|int>>
     * @date 2026-06-21
     * @author Stephane H.
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 33],
        ];
    }

    /**
     * @brief Resolve active locale codes from site configuration.
     *
     * @return list<string>
     * @date 2026-06-21
     * @author Stephane H.
     */
    private function resolveActiveLocales(): array
    {
        $configuration = $this->localeConfigurationService->getConfiguration();
        $activeLocales = is_array($configuration['activeLocales'] ?? null) ? $configuration['activeLocales'] : [];
        if ($activeLocales === []) {
            $activeLocales = $this->supportedLocales;
        }

        return array_values(array_filter(
            $activeLocales,
            static fn (mixed $locale): bool => is_string($locale) && trim($locale) !== '',
        ));
    }
}
