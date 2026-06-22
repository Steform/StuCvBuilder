(function () {
    'use strict';

    const root = document.querySelector('[data-cv-experience-admin]');
    if (!root) {
        return;
    }

    const experienceDebugEnabled = root.getAttribute('data-cv-experience-debug') === '1';
    const experienceClientLogUrl = root.getAttribute('data-cv-experience-client-log-url') || '';

    /**
     * @brief Summarize experience accordion rows currently in the DOM.
     *
     * @return {Array<{ id: string, title: string, domIndex: number }>}
     * @date 2026-06-04
     * @author Stephane H.
     */
    function summarizeDomEntries() {
        const container = getEntriesRoot();
        if (!container) {
            return [];
        }

        return Array.from(container.querySelectorAll('[data-cv-experience-entry]'))
            .map(function (entry, domIndex) {
                if (!(entry instanceof HTMLElement)) {
                    return null;
                }

                return {
                    id: readEntryId(entry),
                    title: readEntryTitleForDashboardLocale(entry),
                    domIndex: domIndex,
                };
            })
            .filter(function (row) {
                return row !== null;
            });
    }

    /**
     * @brief Emit dev-only audit logs to the browser console and optional server endpoint.
     *
     * @param {string} action Action slug (add, remove, submit, move, ...).
     * @param {Record<string, unknown>} [context] Extra structured fields.
     * @return {void}
     * @date 2026-06-04
     * @author Stephane H.
     */
    function logExperienceAdmin(action, context) {
        if (!experienceDebugEnabled) {
            return;
        }

        const payload = Object.assign(
            {
                action: action,
                ts: new Date().toISOString(),
                url: window.location.href,
            },
            context || {}
        );

        console.info('[cv_experience_admin]', payload);

        if (experienceClientLogUrl === '') {
            return;
        }

        try {
            fetch(experienceClientLogUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
                credentials: 'same-origin',
                keepalive: true,
            }).catch(function () {
                return;
            });
        } catch (e) {
            return;
        }
    }

    const previewRoot = root.querySelector('[data-cv-experience-preview]');

    /**
     * @param {string} locale
     */
    function showPreviewForLocale(locale) {
        if (!previewRoot || locale === '') {
            return;
        }

        previewRoot.querySelectorAll('[data-cv-experience-preview-locale]').forEach(function (pane) {
            const paneLocale = pane.getAttribute('data-cv-experience-preview-locale') || '';
            const isActive = paneLocale === locale;
            pane.classList.toggle('show', isActive);
            pane.classList.toggle('active', isActive);
            pane.classList.toggle('d-none', !isActive);
            pane.setAttribute('aria-hidden', isActive ? 'false' : 'true');
        });
    }

    root.querySelectorAll('[data-cv-experience-preview-locale-tab]').forEach(function (tabButton) {
        tabButton.addEventListener('shown.bs.tab', function (event) {
            const target = event.target;
            if (!(target instanceof HTMLElement)) {
                return;
            }

            const locale = target.getAttribute('data-cv-experience-preview-locale-tab') || '';
            if (locale !== '') {
                showPreviewForLocale(locale);
            }
        });
    });

    const initialPreviewTab = root.querySelector('[data-cv-experience-preview-locale-tab].active');
    if (initialPreviewTab instanceof HTMLElement) {
        const initialLocale = initialPreviewTab.getAttribute('data-cv-experience-preview-locale-tab') || '';
        if (initialLocale !== '') {
            showPreviewForLocale(initialLocale);
        }
    }

    /**
     * @brief Sync About tone adjustment label from range input (-100..100).
     *
     * @param {HTMLInputElement} range Range control.
     * @return {void}
     * @date 2026-05-31
     * @author Stephane H.
     */
    function syncExperienceToneLabel(range) {
        const valueEl = root.querySelector('[data-cv-experience-tone-value]');
        if (!valueEl) {
            return;
        }

        const parsed = parseInt(String(range.value), 10);
        const percent = Number.isNaN(parsed) ? 0 : Math.max(-100, Math.min(100, parsed));
        if (percent === 0) {
            const neutralLabel = valueEl.getAttribute('data-cv-experience-tone-neutral-label') || '';
            valueEl.textContent = neutralLabel;
            return;
        }

        valueEl.textContent = (percent > 0 ? '+' : '') + percent + '%';
    }

    root.querySelectorAll('[data-cv-experience-tone-range]').forEach(function (range) {
        if (!(range instanceof HTMLInputElement)) {
            return;
        }

        syncExperienceToneLabel(range);
        range.addEventListener('input', function () {
            syncExperienceToneLabel(range);
        });
    });

    const entryTemplate = document.getElementById('cv-experience-entry-template');
    if (!entryTemplate) {
        return;
    }

    /**
     * @brief Read active locale codes from the admin root data attribute.
     *
     * @return {string[]}
     * @date 2026-06-03
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

    const activeLocales = readActiveLocales();
    const confirmDeleteMessage = root.getAttribute('data-cv-experience-confirm-delete') || '';
    const experienceForm = root.querySelector('.cv-experience-customization__form');
    let isSyncingSharedFields = false;
    let experienceFormSubmitting = false;

    /**
     * @brief Resolve canonical locale used for shared field POST names.
     *
     * @return {string}
     * @date 2026-06-03
     * @author Stephane H.
     */
    function resolveCanonicalLocale() {
        const fromRoot = root.getAttribute('data-default-locale') || '';
        if (fromRoot !== '' && activeLocales.indexOf(fromRoot) >= 0) {
            return fromRoot;
        }

        if (activeLocales.indexOf('fr') >= 0) {
            return 'fr';
        }

        return activeLocales[0] || 'fr';
    }

    /**
     * @brief Resolve dashboard UI locale used for accordion entry titles.
     *
     * @return {string}
     * @date 2026-06-04
     * @author Stephane H.
     */
    function resolveDashboardLocale() {
        const fromRoot = root.getAttribute('data-dashboard-locale') || '';

        return fromRoot !== '' ? fromRoot : resolveCanonicalLocale();
    }

    /**
     * @brief Read job title from the locale pane matching the dashboard UI language.
     *
     * @param {HTMLElement} entry Experience accordion item.
     * @return {string}
     * @date 2026-06-04
     * @author Stephane H.
     */
    function readEntryTitleForDashboardLocale(entry) {
        const dashboardLocale = resolveDashboardLocale();
        const localesToTry = [];

        if (activeLocales.indexOf(dashboardLocale) >= 0) {
            localesToTry.push(dashboardLocale);
        } else {
            localesToTry.push(resolveCanonicalLocale());
            activeLocales.forEach(function (locale) {
                if (localesToTry.indexOf(locale) < 0) {
                    localesToTry.push(locale);
                }
            });
        }

        for (let index = 0; index < localesToTry.length; index += 1) {
            const locale = localesToTry[index];
            const pane = findLocalePaneInEntry(entry, locale);
            if (!pane) {
                continue;
            }

            const titleInput = pane.querySelector('[data-cv-experience-title]');
            if (!(titleInput instanceof HTMLInputElement)) {
                continue;
            }

            const titleValue = titleInput.value.trim();
            if (titleValue !== '') {
                return titleValue;
            }

            if (locale === dashboardLocale && activeLocales.indexOf(dashboardLocale) >= 0) {
                return '';
            }
        }

        return '';
    }

    /**
     * @brief Return the single accordion root for all experience entries.
     *
     * @return {HTMLElement|null}
     * @date 2026-06-03
     * @author Stephane H.
     */
    function getEntriesRoot() {
        const container = root.querySelector('[data-cv-experience-entries-root]');

        return container instanceof HTMLElement ? container : null;
    }

    /**
     * @brief Access centralized CKEditor bridge when available.
     *
     * @return {{ initTextarea?: Function, syncTextarea?: Function, syncAllInRoot?: Function, getHtml?: Function, setHtml?: Function }|null}
     * @date 2026-06-03
     * @author Stephane H.
     */
    function getCkeditorBridge() {
        if (typeof window.CvCkeditorBridge === 'object' && window.CvCkeditorBridge !== null) {
            return window.CvCkeditorBridge;
        }

        return null;
    }

    /**
     * @brief Read HTML from a detail textarea, syncing the editor model first.
     *
     * @param {HTMLTextAreaElement|null} textarea Detail field.
     * @return {string}
     * @date 2026-06-03
     * @author Stephane H.
     */
    function readDetailHtmlFromTextarea(textarea) {
        if (!(textarea instanceof HTMLTextAreaElement)) {
            return '';
        }

        const bridge = getCkeditorBridge();
        if (bridge && typeof bridge.syncTextarea === 'function') {
            bridge.syncTextarea(textarea);
        }
        if (bridge && typeof bridge.getHtml === 'function') {
            return bridge.getHtml(textarea);
        }

        return textarea.value;
    }

    /**
     * @brief Write HTML into a detail textarea and ensure CKEditor is mounted.
     *
     * @param {HTMLTextAreaElement|null} textarea Detail field.
     * @param {string} html Rich-text HTML.
     * @return {void}
     * @date 2026-06-03
     * @author Stephane H.
     */
    function writeDetailHtmlToTextarea(textarea, html) {
        if (!(textarea instanceof HTMLTextAreaElement)) {
            return;
        }

        const normalizedHtml = html || '';
        textarea.value = normalizedHtml;

        const bridge = getCkeditorBridge();
        if (bridge && typeof bridge.setHtml === 'function') {
            bridge.setHtml(textarea, normalizedHtml);
            if (
                typeof bridge.initTextarea === 'function' &&
                textarea.dataset.ckeditorReady !== '1' &&
                textarea.dataset.ckeditorPending !== '1'
            ) {
                bridge.initTextarea(textarea);
            }

            return;
        }
    }

    /**
     * @brief Initialize CKEditor on the detail field inside one experience fieldset.
     *
     * @param {HTMLElement} entry Experience fieldset root.
     * @return {void}
     * @date 2026-06-03
     * @author Stephane H.
     */
    function initDetailHtmlEditorInEntry(entry) {
        const activePane = entry.querySelector('[data-cv-experience-entry-locale-pane].active');
        const pane =
            activePane instanceof HTMLElement
                ? activePane
                : entry.querySelector('[data-cv-experience-entry-locale-pane]');
        if (!(pane instanceof HTMLElement)) {
            return;
        }

        const textarea = pane.querySelector('[data-cv-experience-detail-html]');
        if (!(textarea instanceof HTMLTextAreaElement)) {
            return;
        }

        const bridge = getCkeditorBridge();
        if (!bridge || typeof bridge.initTextarea !== 'function') {
            if (typeof window.ClassicEditor === 'undefined') {
                window.setTimeout(function () {
                    initDetailHtmlEditorInEntry(entry);
                }, 50);
            }

            return;
        }

        bridge.initTextarea(textarea);
    }

    /**
     * @brief Resolve locale list used for cross-locale entry operations.
     *
     * @return {string[]}
     * @date 2026-06-03
     * @author Stephane H.
     */
    function resolveLocalesForSync() {
        return activeLocales.length > 0 ? activeLocales : ['fr'];
    }

    /**
     * @brief Read the shared entry UUID from an experience fieldset.
     *
     * @param {HTMLElement} entry Experience fieldset root.
     * @return {string}
     * @date 2026-06-03
     * @author Stephane H.
     */
    function readEntryId(entry) {
        const idInput = entry.querySelector('[data-cv-experience-id]');

        return idInput instanceof HTMLInputElement ? idInput.value.trim() : '';
    }

    /**
     * @brief Find one experience accordion item by entry UUID.
     *
     * @param {string} entryId Entry UUID.
     * @return {HTMLElement|null}
     * @date 2026-06-03
     * @author Stephane H.
     */
    function findExperienceEntryById(entryId) {
        if (entryId === '') {
            return null;
        }

        const container = getEntriesRoot();
        if (!container) {
            return null;
        }

        const entries = container.querySelectorAll('[data-cv-experience-entry]');
        for (let index = 0; index < entries.length; index += 1) {
            const candidate = entries[index];
            if (!(candidate instanceof HTMLElement)) {
                continue;
            }

            if (readEntryId(candidate) === entryId) {
                return candidate;
            }
        }

        return null;
    }

    /**
     * @brief Find the localized pane inside one experience accordion item.
     *
     * @param {HTMLElement} entry Experience accordion item.
     * @param {string} locale Locale code.
     * @return {HTMLElement|null}
     * @date 2026-06-03
     * @author Stephane H.
     */
    function findLocalePaneInEntry(entry, locale) {
        const pane = entry.querySelector(
            '[data-cv-experience-entry-locale-pane][data-locale="' + locale + '"]'
        );

        return pane instanceof HTMLElement ? pane : null;
    }

    /**
     * @brief Read active locale tab code inside one experience entry.
     *
     * @param {HTMLElement} entry Experience accordion item.
     * @return {string}
     * @date 2026-06-03
     * @author Stephane H.
     */
    function readActiveLocaleForEntry(entry) {
        const activeTab = entry.querySelector('[data-cv-experience-entry-locale-tab].active');
        if (activeTab instanceof HTMLElement) {
            return activeTab.getAttribute('data-cv-experience-entry-locale-tab') || '';
        }

        return resolveCanonicalLocale();
    }

    /**
     * @brief Mirror shared structural fields into every locale pane hidden inputs.
     *
     * @param {HTMLElement} entry Experience accordion item.
     * @return {void}
     * @date 2026-06-03
     * @author Stephane H.
     */
    function syncSharedFieldsToAllLocalePanes(entry) {
        if (isSyncingSharedFields) {
            return;
        }

        const shared = entry.querySelector('[data-cv-experience-shared-fields]');
        if (!(shared instanceof HTMLElement)) {
            return;
        }

        const startInput = shared.querySelector('[data-cv-experience-start-date]');
        const endInput = shared.querySelector('[data-cv-experience-end-date]');
        const isCurrentInput = shared.querySelector('[data-cv-experience-is-current]');
        const companyInput = shared.querySelector('[data-cv-experience-company-name]');
        const websiteInput = shared.querySelector('[data-cv-experience-website]');
        const locationInput = shared.querySelector('[data-cv-experience-location]');
        const hideCompanyInput = shared.querySelector('[data-cv-experience-hide-company-name]');
        const isPrimaryInput = shared.querySelector('[data-cv-experience-is-primary]');
        const logoPathInput = entry.querySelector('[data-cv-experience-logo-path]');

        isSyncingSharedFields = true;
        entry.querySelectorAll('[data-cv-experience-entry-locale-pane]').forEach(function (pane) {
            if (!(pane instanceof HTMLElement)) {
                return;
            }

            const startSync = pane.querySelector('[data-cv-experience-start-date-sync]');
            if (startSync instanceof HTMLInputElement && startInput instanceof HTMLInputElement) {
                startSync.value = startInput.value;
            }

            const endSync = pane.querySelector('[data-cv-experience-end-date-sync]');
            if (endSync instanceof HTMLInputElement && endInput instanceof HTMLInputElement) {
                endSync.value = endInput.value;
            }

            const isCurrentSync = pane.querySelector('[data-cv-experience-is-current-sync]');
            if (isCurrentSync instanceof HTMLInputElement && isCurrentInput instanceof HTMLInputElement) {
                isCurrentSync.value = isCurrentInput.checked ? '1' : '0';
            }

            const companySync = pane.querySelector('[data-cv-experience-company-name-sync]');
            if (companySync instanceof HTMLInputElement && companyInput instanceof HTMLInputElement) {
                companySync.value = companyInput.value;
            }

            const websiteSync = pane.querySelector('[data-cv-experience-website-sync]');
            if (websiteSync instanceof HTMLInputElement && websiteInput instanceof HTMLInputElement) {
                websiteSync.value = websiteInput.value;
            }

            const locationSync = pane.querySelector('[data-cv-experience-location-sync]');
            if (locationSync instanceof HTMLInputElement && locationInput instanceof HTMLInputElement) {
                locationSync.value = locationInput.value;
            }

            const hideCompanySync = pane.querySelector('[data-cv-experience-hide-company-name-sync]');
            if (hideCompanySync instanceof HTMLInputElement && hideCompanyInput instanceof HTMLInputElement) {
                hideCompanySync.value = hideCompanyInput.checked ? '1' : '0';
            }

            const isPrimarySync = pane.querySelector('[data-cv-experience-is-primary-sync]');
            if (isPrimarySync instanceof HTMLInputElement && isPrimaryInput instanceof HTMLInputElement) {
                isPrimarySync.value = isPrimaryInput.checked ? '1' : '0';
            }

            if (logoPathInput instanceof HTMLInputElement) {
                const logoPathSync = pane.querySelector('[data-cv-experience-logo-path]');
                if (logoPathSync instanceof HTMLInputElement) {
                    logoPathSync.value = logoPathInput.value;
                }
            }
        });
        isSyncingSharedFields = false;
    }

    /**
     * @brief Reindex shared and localized field names for every experience entry.
     *
     * @return {void}
     * @date 2026-06-03
     * @author Stephane H.
     */
    function reindexAllLocaleEntries() {
        const container = getEntriesRoot();
        if (!container) {
            return;
        }

        const canonicalLocale = resolveCanonicalLocale();
        const entries = container.querySelectorAll('[data-cv-experience-entry]');
        entries.forEach(function (entry, index) {
            if (entry instanceof HTMLElement) {
                reindexSingleEntry(entry, index, canonicalLocale);
            }
        });
    }

    /**
     * @returns {string}
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
     * @param {HTMLElement} entry
     * @returns {boolean}
     */
    function entryHasLogo(entry) {
        const logoPath = entry.querySelector('[data-cv-experience-logo-path]');
        const logoFile = entry.querySelector('[data-cv-experience-logo-file]');
        const removeLogo = entry.querySelector('[data-cv-experience-remove-logo]');

        const hasStoredLogo =
            logoPath !== null &&
            logoPath.value.trim() !== '' &&
            !(removeLogo instanceof HTMLInputElement && removeLogo.checked);

        const hasNewLogo =
            logoFile instanceof HTMLInputElement && logoFile.files !== null && logoFile.files.length > 0;

        return hasStoredLogo || hasNewLogo;
    }

    /**
     * @param {HTMLElement} entry
     */
    function syncCompanyNameRequirement(entry) {
        const companyInput = entry.querySelector('[data-cv-experience-company-name]');
        const help = entry.querySelector('[data-cv-experience-company-optional-help]');
        const hasLogo = entryHasLogo(entry);

        if (companyInput instanceof HTMLInputElement) {
            if (hasLogo) {
                companyInput.removeAttribute('required');
            } else {
                companyInput.setAttribute('required', 'required');
            }
        }

        if (help) {
            help.classList.toggle('d-none', !hasLogo);
        }
    }

    /**
     * @brief Reindex one experience accordion item and its locale panes.
     *
     * @param {HTMLElement} entry Experience accordion item.
     * @param {number} index Shared chronological index.
     * @param {string} canonicalLocale Locale used for shared field names.
     * @return {void}
     * @date 2026-06-03
     * @author Stephane H.
     */
    function reindexSingleEntry(entry, index, canonicalLocale) {
        const shared = entry.querySelector('[data-cv-experience-shared-fields]');
        if (shared instanceof HTMLElement) {
            shared.querySelectorAll('[name]').forEach(function (input) {
                const name = input.getAttribute('name');
                if (!name || !name.startsWith('experience_entries[')) {
                    return;
                }

                input.setAttribute(
                    'name',
                    name.replace(
                        /experience_entries\[[^\]]+\]\[\d+\]/,
                        'experience_entries[' + canonicalLocale + '][' + index + ']'
                    )
                );
            });

            shared.querySelectorAll('[id^="exp-"]').forEach(function (element) {
                const id = element.getAttribute('id');
                if (!id) {
                    return;
                }

                const match = /^exp-([^-]+)-(\d+)-(.+)$/.exec(id);
                if (match) {
                    element.setAttribute('id', 'exp-' + canonicalLocale + '-' + index + '-' + match[3]);
                }
            });
        }

        entry.querySelectorAll('[data-cv-experience-entry-locale-pane]').forEach(function (pane) {
            if (!(pane instanceof HTMLElement)) {
                return;
            }

            const locale = pane.getAttribute('data-locale') || '';
            if (locale === '') {
                return;
            }

            pane.querySelectorAll('[name]').forEach(function (input) {
                const name = input.getAttribute('name');
                if (!name || !name.startsWith('experience_entries[')) {
                    return;
                }

                input.setAttribute(
                    'name',
                    name.replace(
                        /experience_entries\[[^\]]+\]\[\d+\]/,
                        'experience_entries[' + locale + '][' + index + ']'
                    )
                );
            });

            pane.querySelectorAll('[id^="exp-"]').forEach(function (element) {
                const id = element.getAttribute('id');
                if (!id) {
                    return;
                }

                const match = /^exp-([^-]+)-(\d+)-(.+)$/.exec(id);
                if (match) {
                    element.setAttribute('id', 'exp-' + locale + '-' + index + '-' + match[3]);
                }
            });

            const sortInput = pane.querySelector('[data-cv-experience-sort-order]');
            if (sortInput instanceof HTMLInputElement) {
                sortInput.value = String(index);
            }
        });

        const entryId = readEntryId(entry);
        updateEntryPermalinkHrefs(entry, entryId);
    }

    /**
     * @brief Wire shared-field sync and accordion summary updates for one experience fieldset.
     *
     * @param {HTMLElement} entry Experience fieldset root.
     * @return {void}
     * @date 2026-06-03
     * @author Stephane H.
     */
    function bindSharedFieldSync(entry) {
        const pushSharedSync = function () {
            syncSharedFieldsToAllLocalePanes(entry);
        };

        const companyInput = entry.querySelector('[data-cv-experience-company-name]');
        if (companyInput instanceof HTMLInputElement) {
            companyInput.addEventListener('input', function () {
                pushSharedSync();
                updateEntryAccordionSummary(entry);
            });
        }

        entry.querySelectorAll('[data-cv-experience-title]').forEach(function (titleInput) {
            if (titleInput instanceof HTMLInputElement) {
                titleInput.addEventListener('input', function () {
                    updateEntryAccordionSummary(entry);
                });
            }
        });

        const websiteInput = entry.querySelector('[data-cv-experience-website]');
        if (websiteInput instanceof HTMLInputElement) {
            websiteInput.addEventListener('input', pushSharedSync);
        }

        const locationInput = entry.querySelector('[data-cv-experience-location]');
        if (locationInput instanceof HTMLInputElement) {
            locationInput.addEventListener('input', pushSharedSync);
        }

        const startDateInput = entry.querySelector('[data-cv-experience-start-date]');
        if (startDateInput instanceof HTMLInputElement) {
            startDateInput.addEventListener('change', function () {
                pushSharedSync();
                updateEntryAccordionSummary(entry);
            });
        }

        const endDateInput = entry.querySelector('[data-cv-experience-end-date]');
        if (endDateInput instanceof HTMLInputElement) {
            endDateInput.addEventListener('change', function () {
                pushSharedSync();
                updateEntryAccordionSummary(entry);
            });
        }

        const hideCompany = entry.querySelector('[data-cv-experience-hide-company-name]');
        if (hideCompany instanceof HTMLInputElement) {
            hideCompany.addEventListener('change', function () {
                pushSharedSync();
                updateEntryAccordionSummary(entry);
            });
        }

        const isPrimary = entry.querySelector('[data-cv-experience-is-primary]');
        if (isPrimary instanceof HTMLInputElement) {
            isPrimary.addEventListener('change', pushSharedSync);
        }

        const removeLogo = entry.querySelector('[data-cv-experience-remove-logo]');
        if (removeLogo instanceof HTMLInputElement) {
            removeLogo.addEventListener('change', function () {
                pushSharedSync();
                syncCompanyNameRequirement(entry);
            });
        }

        const logoFile = entry.querySelector('[data-cv-experience-logo-file]');
        if (logoFile instanceof HTMLInputElement) {
            logoFile.addEventListener('change', function () {
                if (!logoFile.files || logoFile.files.length === 0) {
                    pushSharedSync();
                    syncCompanyNameRequirement(entry);

                    return;
                }

                const file = logoFile.files[0];
                const reader = new FileReader();
                reader.addEventListener('load', function () {
                    let preview = entry.querySelector('[data-cv-experience-logo-preview]');
                    if (!preview) {
                        preview = document.createElement('div');
                        preview.setAttribute('data-cv-experience-logo-preview', '');
                        preview.className = 'mt-2';
                        preview.innerHTML =
                            '<img class="cv-experience-customization__logo-preview img-thumbnail" alt="">';
                        logoFile.parentElement?.insertAdjacentElement('afterend', preview);
                    }

                    const image = preview.querySelector('img');
                    if (image instanceof HTMLImageElement && typeof reader.result === 'string') {
                        image.src = reader.result;
                    }

                    const removeCheckbox = entry.querySelector('[data-cv-experience-remove-logo]');
                    if (removeCheckbox instanceof HTMLInputElement) {
                        removeCheckbox.checked = false;
                    }

                    pushSharedSync();
                    syncCompanyNameRequirement(entry);
                });
                reader.readAsDataURL(file);
            });
        }
    }

    /**
     * @param {HTMLElement} entry
     */
    function bindEntry(entry) {
        const isCurrent = entry.querySelector('[data-cv-experience-is-current]');
        const endDate = entry.querySelector('[data-cv-experience-end-date]');

        if (isCurrent instanceof HTMLInputElement && endDate instanceof HTMLInputElement) {
            const syncEndDate = function () {
                endDate.disabled = isCurrent.checked;
                if (isCurrent.checked) {
                    endDate.value = '';
                }
            };

            isCurrent.addEventListener('change', function () {
                syncEndDate();
                syncSharedFieldsToAllLocalePanes(entry);
                updateEntryAccordionSummary(entry);
            });
            syncEndDate();
        }

        syncCompanyNameRequirement(entry);
        bindSharedFieldSync(entry);

        entry.querySelectorAll('[data-cv-experience-move]').forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();

                const container = getEntriesRoot();
                if (!container) {
                    return;
                }

                const direction = button.getAttribute('data-cv-experience-move');
                if (direction === 'up' && entry.previousElementSibling) {
                    container.insertBefore(entry, entry.previousElementSibling);
                } else if (direction === 'down' && entry.nextElementSibling) {
                    container.insertBefore(entry.nextElementSibling, entry);
                }

                reindexAllLocaleEntries();
                syncActiveExperienceDeepLinkFromDom();
                logExperienceAdmin('move', {
                    direction: direction,
                    entryId: readEntryId(entry),
                    after: summarizeDomEntries(),
                });
            });
        });

        const removeBtn = entry.querySelector('[data-cv-experience-remove]');
        if (removeBtn) {
            removeBtn.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();

                const idInput = entry.querySelector('[data-cv-experience-id]');
                const entryId = idInput instanceof HTMLInputElement ? idInput.value.trim() : '';

                if (confirmDeleteMessage !== '' && !window.confirm(confirmDeleteMessage)) {
                    logExperienceAdmin('remove_cancelled', { entryId: entryId });

                    return;
                }

                removeEntryFromAllLocales(entryId, entry, { autoSubmit: true });
            });
        }

        const accordionToggle = entry.querySelector('[data-cv-experience-entry-toggle]');
        if (accordionToggle instanceof HTMLAnchorElement) {
            accordionToggle.addEventListener('click', function (event) {
                event.preventDefault();
            });
        }

        const entryCollapse = entry.querySelector('[data-cv-experience-entry-collapse]');
        if (entryCollapse instanceof HTMLElement) {
            if (entryCollapse.dataset.cvExperienceEntryEditorBound !== '1') {
                entryCollapse.dataset.cvExperienceEntryEditorBound = '1';
                entryCollapse.addEventListener('shown.bs.collapse', function () {
                    initDetailHtmlEditorInEntry(entry);
                });
            }

            if (entryCollapse.classList.contains('show')) {
                initDetailHtmlEditorInEntry(entry);
            }
        }
    }

    /**
     * @brief Resolve the next sort index shared across all locale entry lists.
     *
     * @return {number}
     * @date 2026-06-03
     * @author Stephane H.
     */
    function resolveNextSharedEntryIndex() {
        const container = getEntriesRoot();
        if (!container) {
            return 0;
        }

        return container.querySelectorAll('[data-cv-experience-entry]').length;
    }

    /**
     * @brief Append one experience accordion with all locale panes.
     *
     * @param {number} index Shared sort index.
     * @param {string} entryId Shared entry UUID.
     * @return {HTMLElement|null}
     * @date 2026-06-03
     * @author Stephane H.
     */
    function appendEntryAccordion(index, entryId) {
        const container = getEntriesRoot();
        if (!container || !entryTemplate.content) {
            return null;
        }

        const canonicalLocale = resolveCanonicalLocale();
        let html = entryTemplate.innerHTML;
        html = html
            .replace(/__CANONICAL_LOCALE__/g, canonicalLocale)
            .replace(/__INDEX__/g, String(index))
            .replace(/__DISPLAY_INDEX__/g, String(index + 1))
            .replace(/__UUID__/g, entryId);

        const wrapper = document.createElement('div');
        wrapper.innerHTML = html.trim();
        const entry = wrapper.firstElementChild;
        if (!(entry instanceof HTMLElement)) {
            return null;
        }

        container.insertBefore(entry, container.firstElementChild);
        reindexAllLocaleEntries();
        bindEntry(entry);
        syncSharedFieldsToAllLocalePanes(entry);
        logExperienceAdmin('append_accordion', {
            entryId: entryId,
            index: index,
            after: summarizeDomEntries(),
        });

        return entry;
    }

    /**
     * @brief Read admin i18n strings from the root data attribute.
     *
     * @return {{ validationRequired: string, validationTitleLocale: string, validationCompanyOrLogo: string }}
     * @date 2026-06-03
     * @author Stephane H.
     */
    function readExperienceI18n() {
        const fallback = {
            validationRequired: '',
            validationTitleLocale: '',
            validationCompanyOrLogo: '',
        };

        const raw = root.getAttribute('data-i18n') || '';
        try {
            const parsed = JSON.parse(raw);
            if (!parsed || typeof parsed !== 'object') {
                return fallback;
            }

            return {
                validationRequired:
                    typeof parsed.validationRequired === 'string' ? parsed.validationRequired : fallback.validationRequired,
                validationTitleLocale:
                    typeof parsed.validationTitleLocale === 'string'
                        ? parsed.validationTitleLocale
                        : fallback.validationTitleLocale,
                validationCompanyOrLogo:
                    typeof parsed.validationCompanyOrLogo === 'string'
                        ? parsed.validationCompanyOrLogo
                        : fallback.validationCompanyOrLogo,
            };
        } catch (e) {
            return fallback;
        }
    }

    const experienceI18n = readExperienceI18n();
    const formValidationAlert = root.querySelector('[data-cv-experience-form-validation]');
    const customizationEntryInput = root.querySelector('[data-cv-experience-customization-entry]');

    /**
     * @brief Read permalink route and base query params from the admin root element.
     *
     * @return {{ route: string, base: Record<string, string> }}
     * @date 2026-06-03
     * @author Stephane H.
     */
    function readPermalinkConfig() {
        const route = root.getAttribute('data-cv-experience-permalink-route') || 'admin_cv_index';
        const fallbackBase = { tab: 'experience', panel: 'professional_entries' };
        const raw = root.getAttribute('data-cv-experience-permalink-base') || '';

        try {
            const parsed = JSON.parse(raw);
            if (parsed && typeof parsed === 'object') {
                return { route: route, base: parsed };
            }
        } catch (e) {
            return { route: route, base: fallbackBase };
        }

        return { route: route, base: fallbackBase };
    }

    /**
     * @brief Update the accordion header permalink for one entry fieldset.
     *
     * @param {HTMLElement} entry Experience accordion item.
     * @param {string} locale Locale code.
     * @param {string} entryId Entry UUID.
     * @return {void}
     * @date 2026-06-03
     * @author Stephane H.
     */
    function updateEntryPermalinkHrefs(entry, entryId) {
        if (entryId === '') {
            return;
        }

        try {
            const cfg = readPermalinkConfig();
            const toggle = entry.querySelector('[data-cv-experience-entry-toggle]');
            const activeLocale = readActiveLocaleForEntry(entry);

            if (toggle instanceof HTMLAnchorElement) {
                const toggleUrl = new URL(toggle.href || window.location.href, window.location.origin);
                Object.keys(cfg.base).forEach(function (key) {
                    toggleUrl.searchParams.set(key, String(cfg.base[key]));
                });
                toggleUrl.searchParams.set('locale', activeLocale);
                toggleUrl.searchParams.set('entry', entryId);
                toggle.href = toggleUrl.pathname + toggleUrl.search + toggleUrl.hash;
            }

            entry.querySelectorAll('[data-cv-experience-entry-locale-tab]').forEach(function (tab) {
                if (!(tab instanceof HTMLElement)) {
                    return;
                }

                const locale = tab.getAttribute('data-cv-experience-entry-locale-tab') || '';
                const permalink = tab.getAttribute('data-cv-experience-entry-locale-permalink') || '';
                if (locale === '' || permalink === '') {
                    return;
                }

                try {
                    const tabUrl = new URL(permalink, window.location.origin);
                    Object.keys(cfg.base).forEach(function (key) {
                        tabUrl.searchParams.set(key, String(cfg.base[key]));
                    });
                    tabUrl.searchParams.set('locale', locale);
                    tabUrl.searchParams.set('entry', entryId);
                    tab.setAttribute(
                        'data-cv-experience-entry-locale-permalink',
                        tabUrl.pathname + tabUrl.search + tabUrl.hash
                    );
                } catch (e) {
                    return;
                }
            });
        } catch (e) {
            return;
        }
    }

    /**
     * @brief Refresh accordion header title and meta line from field values.
     *
     * @param {HTMLElement} entry Experience accordion item.
     * @return {void}
     * @date 2026-06-03
     * @author Stephane H.
     */
    function updateEntryAccordionSummary(entry) {
        const titleEl = entry.querySelector('[data-cv-experience-entry-summary-title]');
        const metaEl = entry.querySelector('[data-cv-experience-entry-summary-meta]');
        const companyInput = entry.querySelector('[data-cv-experience-company-name]');
        const hideCompany = entry.querySelector('[data-cv-experience-hide-company-name]');
        const startInput = entry.querySelector('[data-cv-experience-start-date]');
        const endInput = entry.querySelector('[data-cv-experience-end-date]');
        const isCurrentInput = entry.querySelector('[data-cv-experience-is-current]');

        if (titleEl) {
            const titleValue = readEntryTitleForDashboardLocale(entry);

            if (titleValue !== '') {
                titleEl.textContent = titleValue;
            }
        }

        if (!metaEl) {
            return;
        }

        const metaParts = [];
        const hideCompanyName = hideCompany instanceof HTMLInputElement && hideCompany.checked;
        const companyValue = companyInput instanceof HTMLInputElement ? companyInput.value.trim() : '';
        if (companyValue !== '' && !hideCompanyName) {
            metaParts.push(companyValue);
        }

        const startValue = startInput instanceof HTMLInputElement ? startInput.value.trim() : '';
        const isCurrent = isCurrentInput instanceof HTMLInputElement && isCurrentInput.checked;
        const endValue = endInput instanceof HTMLInputElement ? endInput.value.trim() : '';
        if (startValue !== '') {
            const period =
                startValue +
                ' – ' +
                (isCurrent
                    ? '…'
                    : endValue !== ''
                      ? endValue
                      : '…');
            metaParts.push(period);
        }

        if (metaParts.length === 0) {
            metaEl.textContent = '';
            metaEl.classList.add('d-none');

            return;
        }

        metaEl.textContent = metaParts.join(' · ');
        metaEl.classList.remove('d-none');
    }

    /**
     * @brief Write active entry id into URL and hidden form field.
     *
     * @param {string} entryId Entry UUID or empty to clear.
     * @param {string} locale Locale code.
     * @return {void}
     * @date 2026-06-03
     * @author Stephane H.
     */
    function setActiveExperienceEntryInUrl(entryId, locale) {
        try {
            const cfg = readPermalinkConfig();
            const url = new URL(window.location.href);
            const urlTab = url.searchParams.get('tab') || '';
            if (urlTab !== '' && urlTab !== 'experience') {
                return;
            }
            Object.keys(cfg.base).forEach(function (key) {
                url.searchParams.set(key, String(cfg.base[key]));
            });
            if (locale !== '') {
                url.searchParams.set('locale', locale);
            }
            if (entryId === '') {
                url.searchParams.delete('entry');
            } else {
                url.searchParams.set('entry', entryId);
            }
            window.history.replaceState(null, '', url.toString());
        } catch (e) {
            return;
        }

        if (customizationEntryInput instanceof HTMLInputElement) {
            customizationEntryInput.value = entryId;
        }

        const localeInput = root.querySelector('input[type="hidden"][name="customization_locale"]');
        if (localeInput instanceof HTMLInputElement && locale !== '') {
            localeInput.value = locale;
        }
    }

    /**
     * @brief Sync URL and hidden fields from the currently open experience entry accordion.
     *
     * @return {void}
     * @date 2026-06-04
     * @author Stephane H.
     */
    function syncActiveExperienceDeepLinkFromDom() {
        const openCollapse = root.querySelector('[data-cv-experience-entry-collapse].show');
        if (!(openCollapse instanceof HTMLElement)) {
            return;
        }

        const entryId = openCollapse.getAttribute('data-cv-experience-entry-collapse') || '';
        if (entryId === '') {
            return;
        }

        const entryRoot = openCollapse.closest('[data-cv-experience-entry]');
        const locale =
            entryRoot instanceof HTMLElement ? readActiveLocaleForEntry(entryRoot) : resolveCanonicalLocale();

        setActiveExperienceEntryInUrl(entryId, locale);
    }

    /**
     * @brief Expand one experience entry accordion across locale lists.
     *
     * @param {string} entryId Entry UUID.
     * @param {string} locale Preferred locale tab.
     * @return {void}
     * @date 2026-06-03
     * @author Stephane H.
     */
    function activateEntryLocaleTab(entryId, locale) {
        const entry = findExperienceEntryById(entryId);
        if (!entry || locale === '') {
            return;
        }

        const tab = entry.querySelector('[data-cv-experience-entry-locale-tab="' + locale + '"]');
        if (tab instanceof HTMLElement && typeof window.bootstrap !== 'undefined' && window.bootstrap.Tab) {
            window.bootstrap.Tab.getOrCreateInstance(tab).show();
        }
    }

    /**
     * @brief Expand one experience entry accordion and activate a locale tab.
     *
     * @param {string} entryId Entry UUID.
     * @param {string} locale Preferred locale tab.
     * @return {void}
     * @date 2026-06-03
     * @author Stephane H.
     */
    function openExperienceEntryAccordion(entryId, locale) {
        if (entryId === '') {
            return;
        }

        const collapse = root.querySelector('[data-cv-experience-entry-collapse="' + entryId + '"]');
        if (!(collapse instanceof HTMLElement)) {
            return;
        }

        if (typeof window.bootstrap !== 'undefined' && window.bootstrap.Collapse) {
            window.bootstrap.Collapse.getOrCreateInstance(collapse, { toggle: false }).show();
        } else {
            collapse.classList.add('show');
        }

        activateEntryLocaleTab(entryId, locale);
        setActiveExperienceEntryInUrl(entryId, locale);
    }

    /**
     * @brief Show or hide inline validation on the main experience form.
     *
     * @param {string} message Error message or empty to hide.
     * @return {void}
     * @date 2026-06-03
     * @author Stephane H.
     */
    function setFormValidationMessage(message) {
        if (!(formValidationAlert instanceof HTMLElement)) {
            return;
        }

        if (message === '') {
            formValidationAlert.textContent = '';
            formValidationAlert.classList.add('d-none');

            return;
        }

        formValidationAlert.textContent = message;
        formValidationAlert.classList.remove('d-none');
    }

    /**
     * @brief Fill one appended experience fieldset from modal payload data.
     *
     * @param {HTMLElement} entry Experience fieldset in the main form.
     * @param {string} locale Locale code.
     * @param {{ shared: object, locales: Record<string, { title: string, detailHtml: string }> }} payload Modal data.
     * @return {void}
     * @date 2026-06-03
     * @author Stephane H.
     */
    function populateEntryFromModalData(entry, locale, payload) {
        const localeData = payload.locales[locale] || { title: '', detailHtml: '' };
        const shared = payload.shared;
        const localePane = findLocalePaneInEntry(entry, locale);

        if (localePane) {
            const titleInput = localePane.querySelector('[data-cv-experience-title]');
            if (titleInput instanceof HTMLInputElement) {
                titleInput.value = localeData.title || '';
            }

            const detailTextarea = localePane.querySelector('[data-cv-experience-detail-html]');
            const detailHtml = localeData.detailHtml || '';
            writeDetailHtmlToTextarea(
                detailTextarea instanceof HTMLTextAreaElement ? detailTextarea : null,
                detailHtml
            );
            if (detailTextarea instanceof HTMLTextAreaElement) {
                detailTextarea.value = detailHtml;
            }
        }

        const startInput = entry.querySelector('[data-cv-experience-start-date]');
        if (startInput instanceof HTMLInputElement) {
            startInput.value = shared.startDate || '';
        }

        const endInput = entry.querySelector('[data-cv-experience-end-date]');
        if (endInput instanceof HTMLInputElement) {
            endInput.value = shared.isCurrent ? '' : shared.endDate || '';
            endInput.disabled = shared.isCurrent;
        }

        const isCurrentInput = entry.querySelector('[data-cv-experience-is-current]');
        if (isCurrentInput instanceof HTMLInputElement) {
            isCurrentInput.checked = shared.isCurrent;
        }

        const companyInput = entry.querySelector('[data-cv-experience-company-name]');
        if (companyInput instanceof HTMLInputElement) {
            companyInput.value = shared.companyName || '';
        }

        const websiteInput = entry.querySelector('[data-cv-experience-website]');
        if (websiteInput instanceof HTMLInputElement) {
            websiteInput.value = shared.companyWebsiteUrl || '';
        }

        const locationInput = entry.querySelector('[data-cv-experience-location]');
        if (locationInput instanceof HTMLInputElement) {
            locationInput.value = shared.location || '';
        }

        const hideCompanyInput = entry.querySelector('[data-cv-experience-hide-company-name]');
        if (hideCompanyInput instanceof HTMLInputElement) {
            hideCompanyInput.checked = shared.hideCompanyName;
        }

        const isPrimaryInput = entry.querySelector('[data-cv-experience-is-primary]');
        if (isPrimaryInput instanceof HTMLInputElement) {
            isPrimaryInput.checked = shared.isPrimary;
        }

        syncSharedFieldsToAllLocalePanes(entry);
        syncCompanyNameRequirement(entry);
        updateEntryAccordionSummary(entry);
    }

    /**
     * @brief Assign a logo file to the first linked entry file input for upload on save.
     *
     * @param {string} entryId Shared entry UUID.
     * @param {File|null} logoFile Selected logo file.
     * @return {void}
     * @date 2026-06-03
     * @author Stephane H.
     */
    function assignLogoFileToEntries(entryId, logoFile) {
        if (!logoFile || entryId === '') {
            return;
        }

        const entry = findExperienceEntryById(entryId);
        if (!entry) {
            return;
        }

        const logoInput = entry.querySelector('[data-cv-experience-logo-file]');
        if (!(logoInput instanceof HTMLInputElement)) {
            return;
        }

        try {
            const transfer = new DataTransfer();
            transfer.items.add(logoFile);
            logoInput.files = transfer.files;
            logoInput.dispatchEvent(new Event('change', { bubbles: true }));
        } catch (e) {
            return;
        }
    }

    /**
     * @brief Create linked experience rows in the main form from modal payload.
     *
     * @param {{ shared: object, locales: Record<string, { title: string, detailHtml: string }> }} payload Modal data.
     * @return {string} Created entry UUID.
     * @date 2026-06-09
     * @author Stephane H.
     */
    function commitModalEntry(payload) {
        const locales = resolveLocalesForSync();
        const index = resolveNextSharedEntryIndex();
        const entryId = generateUuid();
        const entry = appendEntryAccordion(index, entryId);

        if (entry) {
            locales.forEach(function (locale) {
                populateEntryFromModalData(entry, locale, payload);
            });
        }

        assignLogoFileToEntries(entryId, payload.shared.logoFile);
        setFormValidationMessage('');
        openExperienceEntryAccordion(entryId, resolveCanonicalLocale());
        logExperienceAdmin('modal_add_commit', {
            entryId: entryId,
            locales: Object.keys(payload.locales || {}),
            after: summarizeDomEntries(),
        });

        return entryId;
    }

    /**
     * @brief Mirror shared fields into locale hidden inputs before POST.
     *
     * @return {void}
     * @date 2026-06-09
     * @author Stephane H.
     */
    function prepareExperienceFormForSubmit() {
        root.querySelectorAll('[data-cv-experience-entry]').forEach(function (entry) {
            if (entry instanceof HTMLElement) {
                syncSharedFieldsToAllLocalePanes(entry);
            }
        });
    }

    /**
     * @brief Validate and submit the experience form once the add modal is fully closed.
     *
     * @param {string} [entryId] Optional entry UUID to expand when validation fails.
     * @return {void}
     * @date 2026-06-09
     * @author Stephane H.
     */
    function scheduleExperienceFormSubmit(entryId) {
        if (!(experienceForm instanceof HTMLFormElement) || experienceFormSubmitting) {
            return;
        }

        window.requestAnimationFrame(function () {
            prepareExperienceFormForSubmit();
            if (!experienceForm.reportValidity()) {
                setFormValidationMessage(experienceI18n.validationRequired);
                if (typeof entryId === 'string' && entryId !== '') {
                    openExperienceEntryAccordion(entryId, resolveCanonicalLocale());
                }

                return;
            }

            experienceForm.requestSubmit();
        });
    }

    const addModalElement = document.getElementById('cvExperienceAddModal');
    const addForm = root.querySelector('[data-cv-experience-add-form]');
    const addConfirmButton = root.querySelector('[data-cv-experience-modal-confirm]');
    const validationAlert = root.querySelector('[data-cv-experience-modal-validation]');
    const addModal =
        addModalElement && typeof bootstrap !== 'undefined' && bootstrap.Modal
            ? bootstrap.Modal.getOrCreateInstance(addModalElement)
            : null;

    /**
     * @brief Show or hide modal validation alert text.
     *
     * @param {string} message Error message or empty to hide.
     * @return {void}
     * @date 2026-06-03
     * @author Stephane H.
     */
    function setModalValidationMessage(message) {
        if (!(validationAlert instanceof HTMLElement)) {
            return;
        }

        if (message === '') {
            validationAlert.textContent = '';
            validationAlert.classList.add('d-none');

            return;
        }

        validationAlert.textContent = message;
        validationAlert.classList.remove('d-none');
    }

    /**
     * @brief Reset modal form fields before opening.
     *
     * @return {void}
     * @date 2026-06-03
     * @author Stephane H.
     */
    function resetExperienceAddModal() {
        if (!addForm) {
            return;
        }

        addForm.reset();
        setModalValidationMessage('');

        const isPrimary = addForm.querySelector('[data-cv-experience-modal-is-primary]');
        if (isPrimary instanceof HTMLInputElement) {
            isPrimary.checked = true;
        }

        const preview = addForm.querySelector('[data-cv-experience-modal-logo-preview]');
        if (preview instanceof HTMLElement) {
            preview.classList.add('d-none');
            const image = preview.querySelector('img');
            if (image instanceof HTMLImageElement) {
                image.removeAttribute('src');
            }
        }

        const companyHelp = addForm.querySelector('[data-cv-experience-modal-company-optional-help]');
        if (companyHelp) {
            companyHelp.classList.add('d-none');
        }

        if (addModalElement instanceof HTMLElement) {
            const bridge = getCkeditorBridge();
            if (bridge && typeof bridge.destroyAllInRoot === 'function') {
                bridge.destroyAllInRoot(addModalElement);
            }
        }

        resolveLocalesForSync().forEach(function (locale) {
            const detailTextarea = addForm.querySelector(
                '[data-cv-experience-modal-detail-html][data-locale="' + locale + '"]'
            );
            writeDetailHtmlToTextarea(
                detailTextarea instanceof HTMLTextAreaElement ? detailTextarea : null,
                ''
            );
        });

        const endDate = addForm.querySelector('[data-cv-experience-modal-end-date]');
        const isCurrent = addForm.querySelector('[data-cv-experience-modal-is-current]');
        if (endDate instanceof HTMLInputElement && isCurrent instanceof HTMLInputElement) {
            endDate.disabled = isCurrent.checked;
        }

        const companyInput = addForm.querySelector('[data-cv-experience-modal-company-name]');
        if (companyInput instanceof HTMLInputElement) {
            companyInput.setAttribute('required', 'required');
        }
    }

    /**
     * @brief Sync modal end-date disabled state when "current role" toggles.
     *
     * @return {void}
     * @date 2026-06-03
     * @author Stephane H.
     */
    function bindModalIsCurrentToggle() {
        if (!addForm) {
            return;
        }

        const isCurrent = addForm.querySelector('[data-cv-experience-modal-is-current]');
        const endDate = addForm.querySelector('[data-cv-experience-modal-end-date]');
        if (!(isCurrent instanceof HTMLInputElement) || !(endDate instanceof HTMLInputElement)) {
            return;
        }

        const sync = function () {
            endDate.disabled = isCurrent.checked;
            if (isCurrent.checked) {
                endDate.value = '';
            }
        };

        isCurrent.addEventListener('change', sync);
        sync();
    }

    /**
     * @brief Toggle company name required state when a logo file is selected in the modal.
     *
     * @return {void}
     * @date 2026-06-03
     * @author Stephane H.
     */
    function bindModalLogoRequirement() {
        if (!addForm) {
            return;
        }

        const logoFile = addForm.querySelector('[data-cv-experience-modal-logo-file]');
        const companyInput = addForm.querySelector('[data-cv-experience-modal-company-name]');
        const companyHelp = addForm.querySelector('[data-cv-experience-modal-company-optional-help]');
        const preview = addForm.querySelector('[data-cv-experience-modal-logo-preview]');

        if (!(logoFile instanceof HTMLInputElement)) {
            return;
        }

        logoFile.addEventListener('change', function () {
            const hasFile = logoFile.files !== null && logoFile.files.length > 0;
            if (companyInput instanceof HTMLInputElement) {
                if (hasFile) {
                    companyInput.removeAttribute('required');
                } else {
                    companyInput.setAttribute('required', 'required');
                }
            }

            if (companyHelp) {
                companyHelp.classList.toggle('d-none', !hasFile);
            }

            if (preview instanceof HTMLElement) {
                const image = preview.querySelector('img');
                if (!hasFile || !(image instanceof HTMLImageElement)) {
                    preview.classList.add('d-none');
                    return;
                }

                const file = logoFile.files[0];
                const reader = new FileReader();
                reader.addEventListener('load', function () {
                    if (typeof reader.result === 'string') {
                        image.src = reader.result;
                        preview.classList.remove('d-none');
                    }
                });
                reader.readAsDataURL(file);
            }
        });
    }

    /**
     * @brief Collect rich-text detail HTML for one modal locale pane.
     *
     * @param {string} locale Locale code.
     * @return {string}
     * @date 2026-06-03
     * @author Stephane H.
     */
    function collectModalDetailHtmlForLocale(locale) {
        const textarea = addForm
            ? addForm.querySelector('[data-cv-experience-modal-detail-html][data-locale="' + locale + '"]')
            : null;

        return readDetailHtmlFromTextarea(textarea instanceof HTMLTextAreaElement ? textarea : null);
    }

    /**
     * @brief Validate modal form and return payload or null.
     *
     * @return {{ shared: object, locales: Record<string, { title: string, detailHtml: string }> }|null}
     * @date 2026-06-03
     * @author Stephane H.
     */
    function collectValidatedModalPayload() {
        if (!addForm) {
            return null;
        }

        const bridge = getCkeditorBridge();
        if (bridge && typeof bridge.syncAllInRoot === 'function' && addModalElement instanceof HTMLElement) {
            bridge.syncAllInRoot(addModalElement);
        }

        const startDate = addForm.querySelector('[data-cv-experience-modal-start-date]');
        const endDate = addForm.querySelector('[data-cv-experience-modal-end-date]');
        const isCurrent = addForm.querySelector('[data-cv-experience-modal-is-current]');
        const companyInput = addForm.querySelector('[data-cv-experience-modal-company-name]');
        const websiteInput = addForm.querySelector('[data-cv-experience-modal-website]');
        const locationInput = addForm.querySelector('[data-cv-experience-modal-location]');
        const hideCompany = addForm.querySelector('[data-cv-experience-modal-hide-company-name]');
        const isPrimary = addForm.querySelector('[data-cv-experience-modal-is-primary]');
        const logoFile = addForm.querySelector('[data-cv-experience-modal-logo-file]');

        if (!(startDate instanceof HTMLInputElement) || startDate.value.trim() === '') {
            setModalValidationMessage(experienceI18n.validationRequired);

            return null;
        }

        const isCurrentChecked = isCurrent instanceof HTMLInputElement && isCurrent.checked;
        if (!isCurrentChecked && endDate instanceof HTMLInputElement && endDate.value.trim() === '') {
            setModalValidationMessage(experienceI18n.validationRequired);

            return null;
        }

        const companyName = companyInput instanceof HTMLInputElement ? companyInput.value.trim() : '';
        const hasLogo =
            logoFile instanceof HTMLInputElement && logoFile.files !== null && logoFile.files.length > 0;
        if (companyName === '' && !hasLogo) {
            setModalValidationMessage(experienceI18n.validationCompanyOrLogo);

            return null;
        }

        const localesPayload = {};
        const locales = resolveLocalesForSync();
        for (let index = 0; index < locales.length; index += 1) {
            const locale = locales[index];
            const titleInput = addForm.querySelector('[data-cv-experience-modal-title][data-locale="' + locale + '"]');
            const title = titleInput instanceof HTMLInputElement ? titleInput.value.trim() : '';
            if (title === '') {
                const message = experienceI18n.validationTitleLocale.replace('%code%', locale.toUpperCase());
                setModalValidationMessage(message);

                return null;
            }

            const detailHtml = collectModalDetailHtmlForLocale(locale);
            localesPayload[locale] = {
                title: title,
                detailHtml: detailHtml,
            };
        }

        setModalValidationMessage('');

        return {
            shared: {
                startDate: startDate.value.trim(),
                endDate: isCurrentChecked ? '' : endDate instanceof HTMLInputElement ? endDate.value.trim() : '',
                isCurrent: isCurrentChecked,
                companyName: companyName,
                companyWebsiteUrl: websiteInput instanceof HTMLInputElement ? websiteInput.value.trim() : '',
                location: locationInput instanceof HTMLInputElement ? locationInput.value.trim() : '',
                hideCompanyName: hideCompany instanceof HTMLInputElement && hideCompany.checked,
                isPrimary: !(isPrimary instanceof HTMLInputElement) || isPrimary.checked,
                logoFile: hasLogo && logoFile instanceof HTMLInputElement ? logoFile.files[0] : null,
            },
            locales: localesPayload,
        };
    }

    bindModalIsCurrentToggle();
    bindModalLogoRequirement();

    if (addModalElement) {
        addModalElement.addEventListener('hidden.bs.modal', function () {
            const addButton = root.querySelector('[data-cv-experience-add]');
            if (addButton instanceof HTMLElement) {
                addButton.focus();
            }
        });
    }

    if (addConfirmButton) {
        addConfirmButton.addEventListener('click', function () {
            const payload = collectValidatedModalPayload();
            if (!payload) {
                return;
            }

            const entryId = commitModalEntry(payload);
            if (addConfirmButton instanceof HTMLElement) {
                addConfirmButton.blur();
            }

            const submitAfterModalHidden = function () {
                if (addModalElement) {
                    addModalElement.removeEventListener('hidden.bs.modal', submitAfterModalHidden);
                }
                scheduleExperienceFormSubmit(entryId);
            };

            if (addModalElement) {
                addModalElement.addEventListener('hidden.bs.modal', submitAfterModalHidden);
            }
            if (addModal) {
                addModal.hide();
            } else {
                submitAfterModalHidden();
            }
        });
    }

    /**
     * @brief Remove one experience row (and matching ids in other locales) then reindex lists.
     *
     * @param {string} entryId Shared entry UUID.
     * @param {HTMLElement} fallbackEntry Entry element clicked when id is missing.
     * @param {{ autoSubmit?: boolean }} [options] When autoSubmit is true, persist removal via form POST.
     * @return {void}
     * @date 2026-06-03
     * @author Stephane H.
     */
    function removeEntryFromAllLocales(entryId, fallbackEntry, options) {
        const willAutoSubmit =
            options !== undefined &&
            options.autoSubmit === true &&
            experienceForm instanceof HTMLFormElement;

        logExperienceAdmin('remove_start', {
            entryId: entryId,
            before: summarizeDomEntries(),
        });

        if (entryId !== '') {
            try {
                const url = new URL(window.location.href);
                if (url.searchParams.get('entry') === entryId) {
                    setActiveExperienceEntryInUrl('', '');
                }
            } catch (e) {
                // Ignore URL parse errors before DOM removal.
            }
        }

        if (entryId === '') {
            fallbackEntry.remove();
            reindexAllLocaleEntries();
            logExperienceAdmin('remove_done', {
                entryId: '',
                after: summarizeDomEntries(),
                persistedToServer: willAutoSubmit,
            });

            if (willAutoSubmit) {
                scheduleExperienceFormSubmit('');
            }

            return;
        }

        const entry = findExperienceEntryById(entryId);
        if (entry) {
            entry.remove();
        }

        reindexAllLocaleEntries();
        logExperienceAdmin('remove_done', {
            entryId: entryId,
            after: summarizeDomEntries(),
            persistedToServer: willAutoSubmit,
        });

        if (willAutoSubmit) {
            scheduleExperienceFormSubmit('');
        }
    }

    root.querySelectorAll('[data-cv-experience-entry]').forEach(function (entry) {
        if (entry instanceof HTMLElement) {
            bindEntry(entry);
            syncSharedFieldsToAllLocalePanes(entry);
            updateEntryPermalinkHrefs(entry, readEntryId(entry));
        }
    });

    root.querySelectorAll('[data-cv-experience-entry-locale-tab]').forEach(function (tab) {
        tab.addEventListener('shown.bs.tab', function (event) {
            const target = event.target;
            if (!(target instanceof HTMLElement)) {
                return;
            }

            const activeMainTab = document.querySelector('#cvCustomizationTabs .nav-link.active[data-cv-tab]');
            const domTab =
                activeMainTab instanceof HTMLElement ? activeMainTab.getAttribute('data-cv-tab') || '' : '';
            if (domTab !== '' && domTab !== 'experience') {
                return;
            }

            const entryRoot = target.closest('[data-cv-experience-entry]');
            const locale = target.getAttribute('data-cv-experience-entry-locale-tab') || '';
            if (entryRoot instanceof HTMLElement && locale !== '') {
                updateEntryPermalinkHrefs(entryRoot, readEntryId(entryRoot));
                setActiveExperienceEntryInUrl(readEntryId(entryRoot), locale);
            }

            if (locale !== '') {
                showPreviewForLocale(locale);
            }

            if (entryRoot instanceof HTMLElement) {
                initDetailHtmlEditorInEntry(entryRoot);
            }
        });
    });

    if (experienceForm instanceof HTMLFormElement) {
        experienceForm.addEventListener('invalid', function (event) {
            const target = event.target;
            if (!(target instanceof HTMLElement)) {
                return;
            }

            const entry = target.closest('[data-cv-experience-entry]');
            if (entry instanceof HTMLElement) {
                openExperienceEntryAccordion(readEntryId(entry), readActiveLocaleForEntry(entry));
            }

            setFormValidationMessage(experienceI18n.validationRequired);
        }, true);

        experienceForm.addEventListener('submit', function (event) {
            if (experienceFormSubmitting) {
                event.preventDefault();

                return;
            }

            experienceFormSubmitting = true;
            const saveButton = experienceForm.querySelector('button[type="submit"]');
            if (saveButton instanceof HTMLButtonElement) {
                saveButton.disabled = true;
            }

            syncActiveExperienceDeepLinkFromDom();
            prepareExperienceFormForSubmit();
            logExperienceAdmin('form_submit', {
                entries: summarizeDomEntries(),
                customizationEntry:
                    customizationEntryInput instanceof HTMLInputElement
                        ? customizationEntryInput.value
                        : '',
            });

            setFormValidationMessage('');
        });
    }

    root.querySelectorAll('[data-cv-experience-add]').forEach(function (button) {
        button.addEventListener('click', function () {
            resetExperienceAddModal();
            if (addModal) {
                addModal.show();
            }
        });
    });

    /**
     * @brief Open and scroll to the experience entry referenced in the page URL after PRG redirect.
     *
     * @return {void}
     * @date 2026-06-04
     * @author Stephane H.
     */
    function bootstrapExperienceDeepLinkFromUrl() {
        let entryId = '';
        let locale = resolveCanonicalLocale();
        let urlTab = '';

        try {
            const url = new URL(window.location.href);
            entryId = url.searchParams.get('entry') || '';
            urlTab = url.searchParams.get('tab') || '';
            const localeParam = url.searchParams.get('locale') || '';
            if (localeParam !== '' && activeLocales.indexOf(localeParam) >= 0) {
                locale = localeParam;
            }
        } catch (e) {
            return;
        }

        if (entryId === '') {
            return;
        }

        const activeMainTab = document.querySelector('#cvCustomizationTabs .nav-link.active[data-cv-tab]');
        const domTab =
            activeMainTab instanceof HTMLElement ? activeMainTab.getAttribute('data-cv-tab') || '' : '';
        if ((urlTab !== '' && urlTab !== 'experience') || (domTab !== '' && domTab !== 'experience')) {
            return;
        }

        openExperienceEntryAccordion(entryId, locale);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootstrapExperienceDeepLinkFromUrl);
    } else {
        bootstrapExperienceDeepLinkFromUrl();
    }
})();
