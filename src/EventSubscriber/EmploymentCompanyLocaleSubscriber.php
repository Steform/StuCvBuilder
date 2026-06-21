<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\Cv\CvAccessSessionService;
use App\Service\Employment\EmploymentCountryPresentationLocaleResolver;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Applies company-country presentation locale on public CV routes when no explicit lang query is set.
 */
class EmploymentCompanyLocaleSubscriber implements EventSubscriberInterface
{
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

        $langQuery = trim((string) $request->query->get('lang', ''));
        if ($langQuery !== '') {
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

        $request->setLocale($locale);
        if ($request->hasSession()) {
            $request->getSession()->set('_locale', $locale);
        }
    }

    /**
     * @brief Return subscribed events.
     *
     * @param void No input parameter.
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
