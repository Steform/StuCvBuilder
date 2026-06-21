(function () {
    'use strict';

    const root = document.querySelector('[data-cv-flagship-projects-admin]');
    if (!root) {
        return;
    }

    const entryTemplate = document.getElementById('cv-flagship-project-entry-template');
    const entriesContainer = root.querySelector('[data-cv-flagship-project-entries]');
    const addButton = root.querySelector('[data-cv-flagship-project-add]');
    const maxProjects = Number.parseInt(root.getAttribute('data-cv-flagship-projects-max') || '6', 10);
    const entryFallbackTemplate = root.getAttribute('data-cv-flagship-project-entry-fallback-template') || 'Projet __INDEX__';
    const confirmDeleteMessage = root.getAttribute('data-cv-flagship-project-confirm-delete') || '';
    const flagshipForm = root.querySelector('.cv-flagship-project-customization__form');
    const customizationEntryInput = root.querySelector('[data-cv-flagship-customization-entry]');

    /**
     * @brief Update flagship project accordion deep link in the page URL.
     *
     * @param {string} projectId Project UUID or empty string to clear.
     * @return {void}
     * @date 2026-06-08
     * @author Stephane H.
     */
    function replaceFlagshipProjectUrlParams(projectId) {
        try {
            const url = new URL(window.location.href);
            url.searchParams.set('tab', 'flagship_projects');
            url.searchParams.delete('panel');
            url.searchParams.delete('locale');
            if (projectId === '') {
                url.searchParams.delete('entry');
            } else {
                url.searchParams.set('entry', projectId);
            }
            window.history.replaceState(null, '', url.toString());
        } catch (e) {
            return;
        }

        if (customizationEntryInput instanceof HTMLInputElement) {
            customizationEntryInput.value = projectId;
        }

        logFlagshipProjectsAdmin(
            'cv-flagship-projects-admin.js:url_sync',
            'Flagship project accordion URL updated',
            {
                projectId: projectId,
                afterUrl: window.location.href,
            },
            'accordion',
            'tab-accordion-post-fix'
        );
    }

    /**
     * @brief Bind accordion open/close handlers to keep the URL entry param in sync.
     *
     * @param {HTMLElement} entry Project accordion item.
     * @return {void}
     * @date 2026-06-08
     * @author Stephane H.
     */
    function bindFlagshipEntryAccordionDeepLink(entry) {
        const collapse = entry.querySelector('[data-cv-flagship-project-entry-collapse]');
        if (!(collapse instanceof HTMLElement)) {
            return;
        }

        collapse.addEventListener('shown.bs.collapse', function () {
            const projectId = entry.getAttribute('data-project-id') || '';
            if (projectId !== '') {
                replaceFlagshipProjectUrlParams(projectId);
            }
        });

        collapse.addEventListener('hidden.bs.collapse', function () {
            try {
                const url = new URL(window.location.href);
                const urlTab = url.searchParams.get('tab') || '';
                const projectId = entry.getAttribute('data-project-id') || '';
                if (urlTab === 'flagship_projects' && url.searchParams.get('entry') === projectId) {
                    replaceFlagshipProjectUrlParams('');
                }
            } catch (e) {
                return;
            }
        });
    }

    /**
     * @brief Open and scroll to the flagship project referenced in the page URL after reload.
     *
     * @return {void}
     * @date 2026-06-08
     * @author Stephane H.
     */
    /**
     * @brief Sync URL entry param when a flagship accordion is already open in the DOM.
     *
     * @return {void}
     * @date 2026-06-08
     * @author Stephane H.
     */
    function syncFlagshipUrlFromOpenAccordion() {
        const openCollapse = root.querySelector('[data-cv-flagship-project-entry-collapse].show');
        if (!(openCollapse instanceof HTMLElement)) {
            return;
        }

        const projectId = openCollapse.getAttribute('data-cv-flagship-project-entry-collapse') || '';
        if (projectId === '') {
            return;
        }

        try {
            const url = new URL(window.location.href);
            const urlTab = url.searchParams.get('tab') || '';
            const urlEntry = url.searchParams.get('entry') || '';
            if (urlTab === 'flagship_projects' && urlEntry === projectId) {
                return;
            }
        } catch (e) {
            return;
        }

        replaceFlagshipProjectUrlParams(projectId);
    }

    function bootstrapFlagshipDeepLinkFromUrl() {
        let entryId = '';
        let urlTab = '';

        try {
            const url = new URL(window.location.href);
            entryId = url.searchParams.get('entry') || '';
            urlTab = url.searchParams.get('tab') || '';
        } catch (e) {
            return;
        }

        if (urlTab !== '' && urlTab !== 'flagship_projects') {
            return;
        }

        if (entryId === '') {
            return;
        }

        const collapse = root.querySelector('[data-cv-flagship-project-entry-collapse="' + entryId + '"]');
        if (!(collapse instanceof HTMLElement)) {
            return;
        }

        const entry = collapse.closest('[data-cv-flagship-project-entry]');
        if (entry instanceof HTMLElement) {
            openProjectEntryAccordion(entry);
            entry.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    /**
     * @brief Summarize current flagship project entries for debug instrumentation.
     *
     * @return {{ count: number, projectIds: string[] }} Entry count and project identifiers.
     * @date 2026-06-08
     * @author Stephane H.
     */
    function summarizeFlagshipProjectEntries() {
        const entries = entriesContainer
            ? entriesContainer.querySelectorAll('[data-cv-flagship-project-entry]')
            : [];

        const projectIds = [];
        entries.forEach(function (entry) {
            if (!(entry instanceof HTMLElement)) {
                return;
            }

            const projectId = entry.getAttribute('data-project-id') || '';
            if (projectId !== '') {
                projectIds.push(projectId);
            }
        });

        return {
            count: projectIds.length,
            projectIds: projectIds,
        };
    }

    /**
     * @brief Emit a debug log entry for flagship projects admin instrumentation.
     *
     * @param {string} location Source location label.
     * @param {string} message Short log message.
     * @param {Record<string, unknown>} data Structured payload.
     * @param {string} hypothesisId Hypothesis identifier.
     * @return {void}
     * @date 2026-06-08
     * @author Stephane H.
     */
    function logFlagshipProjectsAdmin() {
    }

    /**
     * @brief Generate a RFC-4122 UUID for new project entries.
     *
     * @return {string} New entry identifier.
     * @date 2026-06-08
     * @author Stephane H.
     */
    function generateUuid() {
        if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
            return crypto.randomUUID();
        }

        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (char) {
            const random = (Math.random() * 16) | 0;
            const value = char === 'x' ? random : (random & 0x3) | 0x8;

            return value.toString(16);
        });
    }

    /**
     * @brief Read active locale codes from the admin root data attribute.
     *
     * @return {string[]} Active locale codes.
     * @date 2026-06-08
     * @author Stephane H.
     */
    function readActiveLocales() {
        const raw = root.getAttribute('data-active-locales') || '[]';
        try {
            const parsed = JSON.parse(raw);
            if (!Array.isArray(parsed)) {
                return [];
            }

            return parsed.filter(function (locale) {
                return typeof locale === 'string' && locale !== '';
            });
        } catch (e) {
            return [];
        }
    }

    /**
     * @brief Resolve dashboard UI locale used for accordion entry titles.
     *
     * @return {string} Dashboard locale code.
     * @date 2026-06-08
     * @author Stephane H.
     */
    function resolveDashboardLocale() {
        const fromRoot = root.getAttribute('data-dashboard-locale') || '';
        if (fromRoot !== '') {
            return fromRoot;
        }

        return readActiveLocales()[0] || 'fr';
    }

    /**
     * @brief Read project title from the locale pane matching the dashboard UI language.
     *
     * @param {HTMLElement} entry Project accordion item.
     * @return {string} Trimmed title for the dashboard locale, or first non-empty locale title.
     * @date 2026-06-08
     * @author Stephane H.
     */
    function readEntryTitleForDashboardLocale(entry) {
        const dashboardLocale = resolveDashboardLocale();
        const activeLocales = readActiveLocales();
        const localesToTry = [];

        if (activeLocales.indexOf(dashboardLocale) >= 0) {
            localesToTry.push(dashboardLocale);
        }

        activeLocales.forEach(function (locale) {
            if (localesToTry.indexOf(locale) < 0) {
                localesToTry.push(locale);
            }
        });

        for (let index = 0; index < localesToTry.length; index += 1) {
            const locale = localesToTry[index];
            const pane = entry.querySelector('[data-cv-flagship-project-locale-pane][data-locale="' + locale + '"]');
            if (!pane) {
                continue;
            }

            const titleInput = pane.querySelector('[data-cv-flagship-project-title]');
            if (!(titleInput instanceof HTMLInputElement)) {
                continue;
            }

            const value = titleInput.value.trim();
            if (value !== '') {
                return value;
            }
        }

        return '';
    }

    /**
     * @brief Build fallback accordion label when no project title is set.
     *
     * @param {number} displayIndex One-based display index.
     * @return {string} Localized fallback label.
     * @date 2026-06-08
     * @author Stephane H.
     */
    function buildFallbackEntryLabel(displayIndex) {
        return entryFallbackTemplate.replace(/__INDEX__/g, String(displayIndex)).replace(/%index%/g, String(displayIndex));
    }

    /**
     * @brief Refresh accordion header title from field values.
     *
     * @param {HTMLElement} entry Project accordion item.
     * @param {number} [displayIndex] One-based display index for fallback label.
     * @return {void}
     * @date 2026-06-08
     * @author Stephane H.
     */
    function updateEntryAccordionSummary(entry, displayIndex) {
        const titleEl = entry.querySelector('[data-cv-flagship-project-entry-summary-title]');
        if (!titleEl) {
            return;
        }

        const titleValue = readEntryTitleForDashboardLocale(entry);
        if (titleValue !== '') {
            titleEl.textContent = titleValue;
            return;
        }

        const index = typeof displayIndex === 'number' ? displayIndex : 1;
        titleEl.textContent = buildFallbackEntryLabel(index);
    }

    /**
     * @brief Expand one project accordion panel.
     *
     * @param {HTMLElement} entry Project accordion item.
     * @return {void}
     * @date 2026-06-08
     * @author Stephane H.
     */
    function openProjectEntryAccordion(entry) {
        const collapse = entry.querySelector('.accordion-collapse');
        const toggle = entry.querySelector('.accordion-button');
        if (!(collapse instanceof HTMLElement) || !(toggle instanceof HTMLElement)) {
            return;
        }

        if (typeof window.bootstrap !== 'undefined' && window.bootstrap.Collapse) {
            window.bootstrap.Collapse.getOrCreateInstance(collapse, { toggle: false }).show();
            return;
        }

        collapse.classList.add('show');
        toggle.classList.remove('collapsed');
        toggle.setAttribute('aria-expanded', 'true');
    }

    /**
     * @brief Reindex sort order and accordion fallback labels after DOM reorder.
     *
     * @param {HTMLElement} container Entries accordion root.
     * @return {void}
     * @date 2026-06-08
     * @author Stephane H.
     */
    function reindexEntries(container) {
        const entries = container.querySelectorAll('[data-cv-flagship-project-entry]');

        entries.forEach(function (entry, index) {
            if (!(entry instanceof HTMLElement)) {
                return;
            }

            const projectId = entry.getAttribute('data-project-id') || '';
            if (projectId === '') {
                return;
            }

            entry.querySelectorAll('[name]').forEach(function (input) {
                const name = input.getAttribute('name');
                if (!name || !name.startsWith('flagship_projects[entries][')) {
                    return;
                }

                input.setAttribute(
                    'name',
                    name.replace(
                        /flagship_projects\[entries\]\[[^\]]+\]/,
                        'flagship_projects[entries][' + projectId + ']'
                    )
                );
            });

            const sortInput = entry.querySelector('[data-cv-flagship-project-sort-order]');
            if (sortInput instanceof HTMLInputElement) {
                sortInput.value = String(index);
            }

            updateEntryAccordionSummary(entry, index + 1);
        });
    }

    /**
     * @brief Enable or disable the add-project button based on max count.
     *
     * @param {HTMLElement} container Entries accordion root.
     * @return {void}
     * @date 2026-06-08
     * @author Stephane H.
     */
    function syncAddButtonState(container) {
        if (!(addButton instanceof HTMLButtonElement)) {
            return;
        }

        const count = container.querySelectorAll('[data-cv-flagship-project-entry]').length;
        addButton.disabled = count >= maxProjects;
    }

    /**
     * @brief Wire move, remove, and title sync handlers for one project entry.
     *
     * @param {HTMLElement} entry Project accordion item.
     * @return {void}
     * @date 2026-06-08
     * @author Stephane H.
     */
    function bindEntry(entry) {
        entry.querySelectorAll('[data-cv-flagship-project-move]').forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();

                const container = entry.closest('[data-cv-flagship-project-entries]');
                if (!container) {
                    return;
                }

                const direction = button.getAttribute('data-cv-flagship-project-move');
                if (direction === 'up' && entry.previousElementSibling) {
                    container.insertBefore(entry, entry.previousElementSibling);
                } else if (direction === 'down' && entry.nextElementSibling) {
                    container.insertBefore(entry.nextElementSibling, entry);
                }

                reindexEntries(container);
            });
        });

        const removeBtn = entry.querySelector('[data-cv-flagship-project-remove]');
        if (removeBtn) {
            removeBtn.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();

                const container = entry.closest('[data-cv-flagship-project-entries]');
                const projectId = entry.getAttribute('data-project-id') || '';
                const before = summarizeFlagshipProjectEntries();
                const projectTitle = readEntryTitleForDashboardLocale(entry);
                const willAutoSubmit = flagshipForm instanceof HTMLFormElement;

                if (confirmDeleteMessage !== '' && !window.confirm(confirmDeleteMessage)) {
                    logFlagshipProjectsAdmin(
                        'cv-flagship-projects-admin.js:remove_cancelled',
                        'Flagship project remove cancelled by user',
                        {
                            projectId: projectId,
                            projectTitle: projectTitle,
                        },
                        'A',
                        'post-fix'
                    );
                    return;
                }

                logFlagshipProjectsAdmin(
                    'cv-flagship-projects-admin.js:remove_click',
                    'Flagship project remove confirmed',
                    {
                        projectId: projectId,
                        projectTitle: projectTitle,
                        beforeCount: before.count,
                        beforeProjectIds: before.projectIds,
                        formWillSubmit: willAutoSubmit,
                    },
                    'A',
                    'post-fix'
                );

                try {
                    const url = new URL(window.location.href);
                    if (url.searchParams.get('entry') === projectId) {
                        replaceFlagshipProjectUrlParams('');
                    }
                } catch (e) {
                    // Ignore URL parse errors before submit.
                }

                entry.remove();
                if (container instanceof HTMLElement) {
                    reindexEntries(container);
                    syncAddButtonState(container);
                }

                const after = summarizeFlagshipProjectEntries();
                logFlagshipProjectsAdmin(
                    'cv-flagship-projects-admin.js:remove_done',
                    'Flagship project removed from DOM; auto-submit scheduled',
                    {
                        removedProjectId: projectId,
                        afterCount: after.count,
                        afterProjectIds: after.projectIds,
                        persistedToServer: willAutoSubmit,
                    },
                    'A',
                    'post-fix'
                );

                if (willAutoSubmit) {
                    flagshipForm.requestSubmit();
                }
            });
        }

        entry.querySelectorAll('[data-cv-flagship-project-title]').forEach(function (titleInput) {
            if (titleInput instanceof HTMLInputElement) {
                titleInput.addEventListener('input', function () {
                    updateEntryAccordionSummary(entry);
                });
            }
        });

        bindFlagshipEntryAccordionDeepLink(entry);
    }

    if (flagshipForm instanceof HTMLFormElement) {
        flagshipForm.addEventListener('submit', function () {
            const summary = summarizeFlagshipProjectEntries();
            const formData = new FormData(flagshipForm);
            const submittedProjectIds = [];
            formData.forEach(function (_value, key) {
                const match = key.match(/^flagship_projects\[entries\]\[([^\]]+)\]/);
                if (match && submittedProjectIds.indexOf(match[1]) < 0) {
                    submittedProjectIds.push(match[1]);
                }
            });

            logFlagshipProjectsAdmin(
                'cv-flagship-projects-admin.js:form_submit',
                'Flagship projects form submitted',
                {
                    domProjectIds: summary.projectIds,
                    submittedProjectIds: submittedProjectIds,
                    domMatchesSubmitted:
                        summary.projectIds.length === submittedProjectIds.length
                        && summary.projectIds.every(function (id) {
                            return submittedProjectIds.indexOf(id) >= 0;
                        }),
                },
                'B',
                'post-fix'
            );
        });
    }

    if (entriesContainer) {
        const initialSummary = summarizeFlagshipProjectEntries();
        logFlagshipProjectsAdmin(
            'cv-flagship-projects-admin.js:init',
            'Flagship projects admin initialized',
            {
                initialCount: initialSummary.count,
                initialProjectIds: initialSummary.projectIds,
            },
            'E',
            'post-fix'
        );

        entriesContainer.querySelectorAll('[data-cv-flagship-project-entry]').forEach(bindEntry);
        syncAddButtonState(entriesContainer);
        bootstrapFlagshipDeepLinkFromUrl();
        syncFlagshipUrlFromOpenAccordion();
    }

    if (addButton && entriesContainer && entryTemplate instanceof HTMLTemplateElement) {
        addButton.addEventListener('click', function () {
            const count = entriesContainer.querySelectorAll('[data-cv-flagship-project-entry]').length;
            if (count >= maxProjects) {
                return;
            }

            const projectId = generateUuid();
            let html = entryTemplate.innerHTML;
            html = html
                .replace(/__UUID__/g, projectId)
                .replace(/__INDEX__/g, String(count))
                .replace(/__DISPLAY_INDEX__/g, String(count + 1));

            const wrapper = document.createElement('div');
            wrapper.innerHTML = html.trim();
            const entry = wrapper.firstElementChild;
            if (!(entry instanceof HTMLElement)) {
                return;
            }

            entry.setAttribute('data-project-id', projectId);
            entriesContainer.appendChild(entry);
            reindexEntries(entriesContainer);
            bindEntry(entry);
            syncAddButtonState(entriesContainer);
            openProjectEntryAccordion(entry);
        });
    }
})();
