<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\Cv\CvAccessSessionService;
use App\Service\Employment\EmploymentCountryPresentationLocaleResolver;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Applies company-country presentation locale on public CV routes when no explicit lang query is set.
 *
 * Locale is applied to the current /cv request only and must not overwrite session `_locale`
 * (admin dashboard UI language stays independent from company country).
 */
class EmploymentCompanyLocaleSubscriber implements EventSubscriberInterface
{
    public const SESSION_LOCALE_OVERRIDE = 'cv_locale_override';

    /**
     * @brief Build employment company locale subscriber.
     *
     * @param EmploymentCountryPresentationLocaleResolver $presentationLocaleResolver Locale resolver.
     * @param CvAccessSessionService $cvAccessSessionService CV format session helper.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function __construct(
        private readonly EmploymentCountryPresentationLocaleResolver $presentationLocaleResolver,
        private readonly CvAccessSessionService $cvAccessSessionService,
    ) {
    }

    /**
     * @brief Apply country presentation locale on CV paths after default locale resolution.
     *
     * @param RequestEvent $event Kernel request event.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();
        if (!str_starts_with($path, '/cv')) {
            return;
        }

        $langQuery = strtolower(trim((string) $request->query->get('lang', '')));
        if ($langQuery !== '') {
            $this->persistLocaleOverride($request, $langQuery);

            return;
        }

        if ($this->applyLocaleOverrideIfPresent($request)) {
            return;
        }

        $formatCode = $this->cvAccessSessionService->resolveTargetFormatCode(
            (string) $request->query->get('format', ''),
            $request,
        );
        $locale = $this->presentationLocaleResolver->resolveForFormatCode($formatCode);
        if ($locale === null) {
            return;
        }

        // Request-only: never write company presentation locale into session `_locale`
        // (admin dashboard UI language must stay independent from company country / public CV).
        $request->setLocale($locale);
    }

    /**
     * @brief Persist an explicit CV locale override chosen via ?lang= or the language switcher.
     *
     * @param Request $request Current request.
     * @param string $locale Locale code from the lang query.
     * @return void
     * @date 2026-10-05
     * @author Stephane H.
     */
    private function persistLocaleOverride(Request $request, string $locale): void
    {
        if (!$request->hasSession()) {
            return;
        }

        $request->getSession()->set(self::SESSION_LOCALE_OVERRIDE, $locale);
    }

    /**
     * @brief Re-apply a previously chosen CV locale override when present.
     *
     * @param Request $request Current request.
     * @return bool True when an override was applied.
     * @date 2026-10-05
     * @author Stephane H.
     */
    private function applyLocaleOverrideIfPresent(Request $request): bool
    {
        if (!$request->hasSession()) {
            return false;
        }

        $override = $request->getSession()->get(self::SESSION_LOCALE_OVERRIDE);
        if (!is_string($override)) {
            return false;
        }

        $override = strtolower(trim($override));
        if ($override === '') {
            return false;
        }

        // Request-only on /cv: do not overwrite admin UI session locale.
        $request->setLocale($override);

        return true;
    }

    /**
     * @brief Return subscribed events.
     *
     * @return array<string, array{0: string, 1?: int}>
     * @date 2026-06-01
     * @author Stephane H.
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', -8],
        ];
    }
}
