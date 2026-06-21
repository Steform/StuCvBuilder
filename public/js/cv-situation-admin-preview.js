(function () {
    'use strict';

    const root = document.querySelector('.cv-situation-customization');
    if (!root) {
        return;
    }

    const previewRoot = root.querySelector('[data-cv-situation-preview]');
    if (!previewRoot) {
        return;
    }

    /**
     * @brief Show Situation preview pane for one locale.
     *
     * @param {string} locale Locale code.
     * @return {void}
     * @date 2026-06-08
     * @author Stephane H.
     */
    function showPreviewForLocale(locale) {
        if (locale === '') {
            return;
        }

        previewRoot.querySelectorAll('[data-cv-situation-preview-locale]').forEach(function (pane) {
            const paneLocale = pane.getAttribute('data-cv-situation-preview-locale') || '';
            const isActive = paneLocale === locale;
            pane.classList.toggle('show', isActive);
            pane.classList.toggle('active', isActive);
            pane.classList.toggle('d-none', !isActive);
            pane.setAttribute('aria-hidden', isActive ? 'false' : 'true');
        });
    }

    root.querySelectorAll('#cvSituationContentLocaleTabs [data-customization-locale-tab]').forEach(function (tabButton) {
        tabButton.addEventListener('shown.bs.tab', function (event) {
            const target = event.target;
            if (!(target instanceof HTMLElement)) {
                return;
            }

            const locale = target.getAttribute('data-customization-locale-tab') || '';
            if (locale !== '') {
                showPreviewForLocale(locale);
            }
        });
    });

    const initialTab = root.querySelector('#cvSituationContentLocaleTabs [data-customization-locale-tab].active');
    if (initialTab instanceof HTMLElement) {
        const initialLocale = initialTab.getAttribute('data-customization-locale-tab') || '';
        if (initialLocale !== '') {
            showPreviewForLocale(initialLocale);
        }
    }
})();
