/**
 * @file Sync customization admin URL query params and hidden form fields (tab, panel, locale).
 */
(function () {
    'use strict';

    var FIELD_PANEL = 'customization_panel';
    var FIELD_LOCALE = 'customization_locale';
    var FIELD_TAB = 'customization_tab';
    var FIELD_ENTRY = 'entry';
    var FIELD_ENTRY_HIDDEN = 'customization_entry';

    /**
     * @brief Update or create a hidden input on a form.
     * @param {HTMLFormElement} form Target form.
     * @param {string} name Input name.
     * @param {string} value Input value.
     * @return {void}
     */
    function setHiddenField(form, name, value) {
        if (value === '') {
            return;
        }

        var input = form.querySelector('input[type="hidden"][name="' + name + '"]');
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            form.appendChild(input);
        }

        input.value = value;
    }

    /**
     * @brief Apply query parameters to the current URL without reloading.
     * @param {Record<string, string>} params Key-value map.
     * @return {void}
     */
    function replaceUrlParams(params) {
        if (typeof window.history === 'undefined' || typeof window.history.replaceState !== 'function') {
            return;
        }

        try {
            var url = new URL(window.location.href);
            Object.keys(params).forEach(function (key) {
                var value = params[key];
                if (value === '') {
                    url.searchParams.delete(key);
                } else {
                    url.searchParams.set(key, value);
                }
            });
            window.history.replaceState(null, '', url.toString());
        } catch (e) {
            return;
        }
    }

    /**
     * @brief Read current UI state from URL search params.
     * @return {{ tab: string, panel: string, locale: string, entry: string }}
     */
    function readStateFromUrl() {
        var url = new URL(window.location.href);

        return {
            tab: url.searchParams.get('tab') || '',
            panel: url.searchParams.get('panel') || '',
            locale: url.searchParams.get('locale') || '',
            entry: url.searchParams.get('entry') || '',
        };
    }

    /**
     * @brief Resolve Bootstrap main tab pane DOM id from a CV tab slug.
     * @param {string} tabSlug CV customization tab slug.
     * @return {string} Pane element id or empty string.
     */
    function cvMainTabPaneId(tabSlug) {
        if (tabSlug === '') {
            return '';
        }

        return 'cv-custom-pane-' + tabSlug.replace(/_/g, '-');
    }

    /**
     * @brief Read the active CV main tab slug from the dashboard nav.
     * @return {string} Active tab slug or empty string.
     */
    function readActiveCvMainTabSlug() {
        var activeTabButton = document.querySelector('#cvCustomizationTabs .nav-link.active[data-cv-tab]');
        if (!(activeTabButton instanceof HTMLElement)) {
            return '';
        }

        return activeTabButton.getAttribute('data-cv-tab') || '';
    }

    /**
     * @brief Check whether a CV main tab pane is currently visible.
     * @param {string} tabSlug CV customization tab slug.
     * @return {boolean}
     */
    function isCvMainTabPaneActive(tabSlug) {
        var paneId = cvMainTabPaneId(tabSlug);
        if (paneId === '') {
            return false;
        }

        var pane = document.getElementById(paneId);
        return pane instanceof HTMLElement && pane.classList.contains('active') && pane.classList.contains('show');
    }

    /**
     * @brief Check whether a DOM node lives inside the visible CV main tab pane.
     * @param {EventTarget|null} target Event target node.
     * @param {string} tabSlug Expected CV tab slug.
     * @return {boolean}
     */
    function isTargetInActiveCvMainTabPane(target, tabSlug) {
        if (!(target instanceof HTMLElement) || tabSlug === '') {
            return false;
        }

        if (!isCvMainTabPaneActive(tabSlug)) {
            return false;
        }

        var pane = document.getElementById(cvMainTabPaneId(tabSlug));
        return pane instanceof HTMLElement && pane.contains(target);
    }

    /**
     * @brief Find the active locale tab button inside a root element.
     * @param {ParentNode} root Scope element.
     * @return {string} Locale code or empty string.
     */
    function readActiveLocaleFromDom(root) {
        var openEntry = root.querySelector('[data-cv-experience-entry-collapse].show');
        if (openEntry instanceof HTMLElement) {
            var entryLocaleTab = openEntry.querySelector('[data-cv-experience-entry-locale-tab].active');
            if (entryLocaleTab instanceof HTMLElement) {
                return entryLocaleTab.getAttribute('data-cv-experience-entry-locale-tab') || '';
            }
        }

        var activeLocaleTab = root.querySelector('[data-customization-locale-tab].active');
        if (activeLocaleTab instanceof HTMLElement) {
            return activeLocaleTab.getAttribute('data-customization-locale-tab') || '';
        }

        return '';
    }

    /**
     * @brief Find the open accordion panel slug inside a root element.
     * @param {ParentNode} root Scope element.
     * @return {string} Panel slug or empty string.
     */
    function readActivePanelFromDom(root) {
        var openCollapse = root.querySelector('.accordion-collapse.show[data-customization-panel-collapse]');
        if (openCollapse instanceof HTMLElement) {
            return openCollapse.getAttribute('data-customization-panel-collapse') || '';
        }

        var item = root.querySelector('.accordion-item[data-customization-panel] .accordion-collapse.show');
        if (item && item.closest('.accordion-item')) {
            var parentItem = item.closest('.accordion-item');
            if (parentItem instanceof HTMLElement) {
                return parentItem.getAttribute('data-customization-panel') || '';
            }
        }

        return '';
    }

    /**
     * @brief Find the open experience entry accordion id from the DOM.
     * @param {ParentNode} root Scope element.
     * @return {string} Entry UUID or empty string.
     */
    function readActiveExperienceEntryFromDom(root) {
        var openCollapse = root.querySelector('[data-cv-experience-entry-collapse].show');
        if (openCollapse instanceof HTMLElement) {
            return openCollapse.getAttribute('data-cv-experience-entry-collapse') || '';
        }

        return '';
    }

    /**
     * @brief Resolve the DOM scope used to detect open accordion panels and locale tabs for a form.
     * @param {HTMLFormElement} form Customization form.
     * @return {ParentNode}
     */
    function resolveFormUiScope(form) {
        return (
            form.closest('.customization-home') ||
            form.closest('.cv-about-customization') ||
            form.closest('.cv-customization') ||
            form.closest('.cv-section-customization') ||
            form.closest('.cv-experience-customization') ||
            form.closest('.cv-situation-customization') ||
            document
        );
    }

    /**
     * @brief Copy live accordion, locale tab, and main tab state into hidden fields before POST.
     * @param {HTMLFormElement} form Customization form.
     * @return {void}
     */
    function syncFormStateFromUi(form) {
        var urlState = readStateFromUrl();
        var scope = resolveFormUiScope(form);

        var panel = readActivePanelFromDom(scope) || urlState.panel || '';
        var locale = readActiveLocaleFromDom(scope) || urlState.locale || '';
        var entry = readActiveExperienceEntryFromDom(scope) || urlState.entry || '';

        var staticPanel = form.querySelector('input[type="hidden"][name="' + FIELD_PANEL + '"]');
        if (panel === '' && staticPanel instanceof HTMLInputElement) {
            panel = staticPanel.value;
        }

        var staticLocale = form.querySelector('input[type="hidden"][name="' + FIELD_LOCALE + '"]');
        if (locale === '' && staticLocale instanceof HTMLInputElement) {
            locale = staticLocale.value;
        }

        var cvTabButton = document.querySelector('#cvCustomizationTabs .nav-link.active[data-cv-tab]');
        var domTab = cvTabButton instanceof HTMLElement ? cvTabButton.getAttribute('data-cv-tab') || '' : '';
        var tab = domTab || urlState.tab || '';
        var staticTab = form.querySelector('input[type="hidden"][name="' + FIELD_TAB + '"]');
        if (tab === '' && staticTab instanceof HTMLInputElement && staticTab.value !== '') {
            tab = staticTab.value;
        }

        setHiddenField(form, FIELD_PANEL, panel);
        setHiddenField(form, FIELD_LOCALE, locale);
        if (tab !== '') {
            setHiddenField(form, FIELD_TAB, tab);
        }

        var entryInput = form.querySelector('[data-cv-experience-customization-entry]');
        if (entryInput instanceof HTMLInputElement) {
            entryInput.value = entry;
        } else {
            setHiddenField(form, FIELD_ENTRY_HIDDEN, entry);
        }
    }

    /**
     * @brief Keep hidden fields aligned when accordion or locale tabs change (not only on submit).
     * @return {void}
     */
    function syncAllCustomizationFormsFromUi() {
        document
            .querySelectorAll(
                '.customization-home-form, .cv-about-customization-form, .cv-experience-customization__form, .cv-situation-customization__form, .cv-flagship-project-customization__form, .cv-section-customization__background-form, .cv-section-customization__transition-form, .cv-data-customization form'
            )
            .forEach(function (form) {
                if (form instanceof HTMLFormElement) {
                    syncFormStateFromUi(form);
                }
            });
    }

    /**
     * @brief Bind submit handlers to copy URL/DOM state into hidden fields.
     * @param {string} formSelector CSS selector for customization forms.
     * @return {void}
     */
    function bindFormSubmitState(formSelector) {
        document.querySelectorAll(formSelector).forEach(function (form) {
            if (!(form instanceof HTMLFormElement)) {
                return;
            }

            form.addEventListener('submit', function () {
                syncFormStateFromUi(form);
            });
        });
    }

    /**
     * @brief Bind CV main tab buttons to update `tab` query param.
     * @return {void}
     */
    function bindCvMainTabs() {
        var root = document.getElementById('cvCustomizationTabs');
        if (!root) {
            return;
        }

        root.addEventListener('shown.bs.tab', function (event) {
            var target = event.target;
            if (!(target instanceof HTMLElement)) {
                return;
            }

            var slug = target.getAttribute('data-cv-tab');
            if (!slug) {
                return;
            }

            var current = readStateFromUrl();
            var panel = '';
            var locale = '';
            if (slug === 'about') {
                panel = current.panel || '';
                locale = current.locale || '';
            } else if (slug === 'experience') {
                panel = current.panel || 'professional_entries';
                locale = current.locale || '';
            } else if (slug === 'education') {
                panel = current.panel || 'education_entries';
                locale = current.locale || '';
            } else if (slug === 'certification') {
                panel = current.panel || 'certification_entries';
                locale = current.locale || '';
            } else if (slug === 'skills') {
                panel = current.panel || 'skills_catalog';
            } else if (slug === 'flagship_projects') {
                panel = current.panel || '';
            }
            var tabParams = {
                tab: slug,
                panel: panel,
                locale:
                    slug === 'about' || slug === 'experience' || slug === 'situation' || slug === 'cv_data'
                        ? current.locale
                        : '',
                entry: '',
            };
            if (slug === 'flagship_projects' || slug === 'cv_data') {
                tabParams.panel = '';
            }
            logCvTabUiState(
                'customization-ui-state.js:main_tab_shown',
                'CV main tab switched',
                {
                    slug: slug,
                    beforeUrl: window.location.href,
                    beforeEntry: current.entry,
                    panel: tabParams.panel,
                },
                'B',
                'tab-accordion-post-fix'
            );
            replaceUrlParams(tabParams);
            logCvTabUiState(
                'customization-ui-state.js:main_tab_url_updated',
                'CV main tab URL updated',
                {
                    slug: slug,
                    afterUrl: window.location.href,
                    afterEntry: readStateFromUrl().entry,
                },
                'B',
                'tab-accordion-post-fix'
            );
        });
    }

    /**
     * @brief Bind locale sub-tabs to update `locale` query param.
     * @return {void}
     */
    function bindLocaleTabs() {
        document.addEventListener('shown.bs.tab', function (event) {
            var target = event.target;
            if (!(target instanceof HTMLElement)) {
                return;
            }

            var locale = target.getAttribute('data-customization-locale-tab');
            var experienceLocale = target.getAttribute('data-cv-experience-entry-locale-tab');
            if (!locale && !experienceLocale) {
                return;
            }

            if (experienceLocale && !isTargetInActiveCvMainTabPane(target, 'experience')) {
                return;
            }

            if (locale && !isTargetInActiveCvMainTabPane(target, readActiveCvMainTabSlug())) {
                return;
            }

            var current = readStateFromUrl();
            var panel = current.panel || readActivePanelFromDom(document);
            var params = {
                tab: current.tab,
                panel: panel,
                locale: locale || experienceLocale || '',
            };

            if (experienceLocale) {
                var entryRoot = target.closest('[data-cv-experience-entry]');
                var entryId = '';
                if (entryRoot instanceof HTMLElement) {
                    entryId = entryRoot.getAttribute('data-customization-entry') || '';
                }
                if (entryId === '') {
                    var entryCollapse = target.closest('[data-cv-experience-entry-collapse]');
                    if (entryCollapse instanceof HTMLElement) {
                        entryId = entryCollapse.getAttribute('data-cv-experience-entry-collapse') || '';
                    }
                }
                if (entryId !== '') {
                    params.entry = entryId;
                }
                params.panel = 'professional_entries';
                if (current.tab === '' || current.tab === 'experience') {
                    params.tab = 'experience';
                }
            } else if (current.entry !== '') {
                params.entry = current.entry;
            }

            replaceUrlParams(params);
            syncAllCustomizationFormsFromUi();
        });
    }

    /**
     * @brief Bind accordion panels to update `panel` query param.
     * @return {void}
     */
    function bindAccordionPanels() {
        document.addEventListener('shown.bs.collapse', function (event) {
            var collapse = event.target;
            if (!(collapse instanceof HTMLElement)) {
                return;
            }

            if (collapse.hasAttribute('data-cv-about-root-collapse')) {
                var aboutCurrent = readStateFromUrl();
                var innerAccordion = document.getElementById('cvAboutCustomizationAccordion');
                var nestedPanel = 'section';
                if (innerAccordion instanceof HTMLElement) {
                    nestedPanel = readActivePanelFromDom(innerAccordion) || 'section';
                }
                if (['section', 'photo', 'presentation'].indexOf(nestedPanel) === -1) {
                    nestedPanel = 'section';
                }

                replaceUrlParams({
                    tab: aboutCurrent.tab !== '' ? aboutCurrent.tab : 'about',
                    panel: nestedPanel,
                    locale: aboutCurrent.locale,
                });
                syncAllCustomizationFormsFromUi();
                return;
            }

            var panel = collapse.getAttribute('data-customization-panel-collapse');
            if (!panel) {
                var item = collapse.closest('[data-customization-panel]');
                if (item instanceof HTMLElement) {
                    panel = item.getAttribute('data-customization-panel') || '';
                }
            }

            if (!panel) {
                return;
            }

            var current = readStateFromUrl();
            replaceUrlParams({
                tab: current.tab,
                panel: panel,
                locale: current.locale,
            });
            syncAllCustomizationFormsFromUi();
        });
    }

    /**
     * @brief Scroll to the active accordion panel after PRG redirect.
     * @return {void}
     */
    function scrollToActivePanel() {
        var state = readStateFromUrl();
        if (state.panel === '') {
            return;
        }

        var target = document.querySelector('[data-customization-panel="' + state.panel + '"]');
        if (target instanceof HTMLElement) {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    /**
     * @brief Keep accordion header permalinks shareable without full page reload on primary click.
     * @return {void}
     */
    function bindAccordionPermalinks() {
        document
            .querySelectorAll(
                '.accordion-item[data-customization-panel] .accordion-button[href], [data-cv-experience-entry-toggle][href]'
            )
            .forEach(function (control) {
                control.addEventListener('click', function (event) {
                    if (event.ctrlKey || event.metaKey || event.shiftKey || event.button !== 0) {
                        return;
                    }

                    event.preventDefault();
                });
            });
    }

    /**
     * @brief Sync `entry` query param when an experience entry accordion opens.
     * @return {void}
     */
    function bindExperienceEntryAccordions() {
        document.addEventListener('shown.bs.collapse', function (event) {
            var collapse = event.target;
            if (!(collapse instanceof HTMLElement)) {
                return;
            }

            var entryId = collapse.getAttribute('data-cv-experience-entry-collapse');
            if (!entryId || entryId === '') {
                return;
            }

            var currentTab = readActiveCvMainTabSlug() || readStateFromUrl().tab;
            if (currentTab !== '' && currentTab !== 'experience') {
                return;
            }

            if (!isTargetInActiveCvMainTabPane(collapse, 'experience')) {
                return;
            }

            var locale = '';
            var activeLocaleTab = collapse.querySelector('[data-cv-experience-entry-locale-tab].active');
            if (activeLocaleTab instanceof HTMLElement) {
                locale = activeLocaleTab.getAttribute('data-cv-experience-entry-locale-tab') || '';
            }
            if (locale === '') {
                locale = readStateFromUrl().locale;
            }

            var current = readStateFromUrl();
            var params = {
                tab: current.tab || 'experience',
                panel: 'professional_entries',
                locale: locale,
                entry: entryId,
            };
            replaceUrlParams(params);
            syncAllCustomizationFormsFromUi();
        });
    }

    /**
     * @brief Scroll to the active experience entry accordion after PRG redirect.
     * @return {void}
     */
    function scrollToActiveExperienceEntry() {
        var state = readStateFromUrl();
        var activeTab = readActiveCvMainTabSlug() || state.tab;
        if (state.entry === '' || activeTab !== 'experience') {
            return;
        }

        var collapse = document.querySelector('[data-cv-experience-entry-collapse="' + state.entry + '"]');
        if (!(collapse instanceof HTMLElement)) {
            return;
        }

        if (state.locale !== '') {
            var localeTab = collapse.querySelector(
                '[data-cv-experience-entry-locale-tab="' + state.locale + '"]'
            );
            if (
                localeTab instanceof HTMLElement &&
                typeof window.bootstrap !== 'undefined' &&
                window.bootstrap.Tab
            ) {
                window.bootstrap.Tab.getOrCreateInstance(localeTab).show();
            }
        }

        var item = collapse.closest('.cv-experience-customization__entry');
        if (item instanceof HTMLElement) {
            item.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    /**
     * @brief Emit debug log for CV tab URL state instrumentation.
     * @param {string} location Source location label.
     * @param {string} message Short log message.
     * @param {Record<string, unknown>} data Structured payload.
     * @param {string} hypothesisId Hypothesis identifier.
     * @return {void}
     */
    function logCvTabUiState() {
    }

    document.addEventListener('DOMContentLoaded', function () {
        var urlState = readStateFromUrl();
        var activeTabButton = document.querySelector('#cvCustomizationTabs .nav-link.active[data-cv-tab]');
        var domTab = activeTabButton instanceof HTMLElement
            ? activeTabButton.getAttribute('data-cv-tab') || ''
            : '';
        logCvTabUiState(
            'customization-ui-state.js:dom_ready',
            'CV customization page loaded',
            {
                urlTab: urlState.tab,
                urlPanel: urlState.panel,
                urlEntry: urlState.entry,
                domActiveTab: domTab,
                fullUrl: window.location.href,
            },
            'A',
            'tab-accordion-post-fix-v2'
        );

        bindCvMainTabs();
        bindLocaleTabs();
        bindAccordionPanels();
        bindAccordionPermalinks();
        bindExperienceEntryAccordions();
        bindFormSubmitState('.customization-home-form, .cv-about-customization-form, .cv-experience-customization__form, .cv-situation-customization__form, .cv-flagship-project-customization__form, .cv-section-customization__background-form, .cv-section-customization__transition-form, .cv-data-customization form');
        scrollToActivePanel();
        scrollToActiveExperienceEntry();

        window.setTimeout(function () {
            var delayedState = readStateFromUrl();
            logCvTabUiState(
                'customization-ui-state.js:post_init_url',
                'CV customization URL after async tab handlers',
                {
                    urlTab: delayedState.tab,
                    urlEntry: delayedState.entry,
                    domActiveTab: readActiveCvMainTabSlug(),
                    fullUrl: window.location.href,
                },
                'D',
                'tab-accordion-post-fix-v2'
            );
        }, 0);
    });
})();
