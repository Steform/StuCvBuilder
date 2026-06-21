(function () {
    'use strict';

    const root = document.querySelector('[data-cv-education-admin]');
    if (!root) {
        return;
    }

    const educationDebugEnabled = root.getAttribute('data-cv-education-debug') === '1';
    const educationClientLogUrl = root.getAttribute('data-cv-education-client-log-url') || '';

    /**
     * @brief Summarize education accordion rows currently in the DOM.
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

        return Array.from(container.querySelectorAll('[data-cv-education-entry]'))
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
    function logEducationAdmin(action, context) {
        if (!educationDebugEnabled) {
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

        console.info('[cv_education_admin]', payload);

        if (educationClientLogUrl === '') {
            return;
        }

        try {
            fetch(educationClientLogUrl, {
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

    const previewRoot = root.querySelector('[data-cv-education-preview]');

    /**
     * @param {string} locale
     */
    function showPreviewForLocale(locale) {
        if (!previewRoot || locale === '') {
            return;
        }

        previewRoot.querySelectorAll('[data-cv-education-preview-locale]').forEach(function (pane) {
            const paneLocale = pane.getAttribute('data-cv-education-preview-locale') || '';
            const isActive = paneLocale === locale;
            pane.classList.toggle('show', isActive);
            pane.classList.toggle('active', isActive);
            pane.classList.toggle('d-none', !isActive);
            pane.setAttribute('aria-hidden', isActive ? 'false' : 'true');
        });
    }

    root.querySelectorAll('[data-cv-education-preview-locale-tab]').forEach(function (tabButton) {
        tabButton.addEventListener('shown.bs.tab', function (event) {
            const target = event.target;
            if (!(target instanceof HTMLElement)) {
                return;
            }

            const locale = target.getAttribute('data-cv-education-preview-locale-tab') || '';
            if (locale !== '') {
                showPreviewForLocale(locale);
            }
        });
    });

    const initialPreviewTab = root.querySelector('[data-cv-education-preview-locale-tab].active');
    if (initialPreviewTab instanceof HTMLElement) {
        const initialLocale = initialPreviewTab.getAttribute('data-cv-education-preview-locale-tab') || '';
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
    function syncEducationToneLabel(range) {
        const valueEl = root.querySelector('[data-cv-education-tone-value]');
        if (!valueEl) {
            return;
        }

        const parsed = parseInt(String(range.value), 10);
        const percent = Number.isNaN(parsed) ? 0 : Math.max(-100, Math.min(100, parsed));
        if (percent === 0) {
            const neutralLabel = valueEl.getAttribute('data-cv-education-tone-neutral-label') || '';
            valueEl.textContent = neutralLabel;
            return;
        }

        valueEl.textContent = (percent > 0 ? '+' : '') + percent + '%';
    }

    root.querySelectorAll('[data-cv-education-tone-range]').forEach(function (range) {
        if (!(range instanceof HTMLInputElement)) {
            return;
        }

        syncEducationToneLabel(range);
        range.addEventListener('input', function () {
            syncEducationToneLabel(range);
        });
    });

    const entryTemplate = document.getElementById('cv-education-entry-template');
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
    const confirmDeleteMessage = root.getAttribute('data-cv-education-confirm-delete') || '';
    const educationForm = root.querySelector('.cv-education-customization__form');
    let isSyncingSharedFields = false;

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
     * @param {HTMLElement} entry Education accordion item.
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

            const titleInput = pane.querySelector('[data-cv-education-title]');
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
        const container = root.querySelector('[data-cv-education-entries-root]');

        return container instanceof HTMLElement ? container : null;
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
     * @brief Read the shared entry UUID from an education fieldset.
     *
     * @param {HTMLElement} entry Education fieldset root.
     * @return {string}
     * @date 2026-06-03
     * @author Stephane H.
     */
    function readEntryId(entry) {
        const idInput = entry.querySelector('[data-cv-education-id]');

        return idInput instanceof HTMLInputElement ? idInput.value.trim() : '';
    }

    /**
     * @brief Find one education accordion item by entry UUID.
     *
     * @param {string} entryId Entry UUID.
     * @return {HTMLElement|null}
     * @date 2026-06-03
     * @author Stephane H.
     */
    function findEducationEntryById(entryId) {
        if (entryId === '') {
            return null;
        }

        const container = getEntriesRoot();
        if (!container) {
            return null;
        }

        const entries = container.querySelectorAll('[data-cv-education-entry]');
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
     * @brief Find the localized pane inside one education accordion item.
     *
     * @param {HTMLElement} entry Education accordion item.
     * @param {string} locale Locale code.
     * @return {HTMLElement|null}
     * @date 2026-06-03
     * @author Stephane H.
     */
    function findLocalePaneInEntry(entry, locale) {
        const pane = entry.querySelector(
            '[data-cv-education-entry-locale-pane][data-locale="' + locale + '"]'
        );

        return pane instanceof HTMLElement ? pane : null;
    }

    /**
     * @brief Read active locale tab code inside one experience entry.
     *
     * @param {HTMLElement} entry Education accordion item.
     * @return {string}
     * @date 2026-06-03
     * @author Stephane H.
     */
    function readActiveLocaleForEntry(entry) {
        const activeTab = entry.querySelector('[data-cv-education-entry-locale-tab].active');
        if (activeTab instanceof HTMLElement) {
            return activeTab.getAttribute('data-cv-education-entry-locale-tab') || '';
        }

        return resolveCanonicalLocale();
    }

    /**
     * @brief Mirror shared structural fields into every locale pane hidden inputs.
     *
     * @param {HTMLElement} entry Education accordion item.
     * @return {void}
     * @date 2026-06-03
     * @author Stephane H.
     */
    function syncSharedFieldsToAllLocalePanes(entry) {
        if (isSyncingSharedFields) {
            return;
        }

        const shared = entry.querySelector('[data-cv-education-shared-fields]');
        if (!(shared instanceof HTMLElement)) {
            return;
        }

        const startInput = shared.querySelector('[data-cv-education-start-date]');
        const endInput = shared.querySelector('[data-cv-education-end-date]');
        const isCurrentInput = shared.querySelector('[data-cv-education-is-current]');
        const companyInput = shared.querySelector('[data-cv-education-institution-name]');
        const websiteInput = shared.querySelector('[data-cv-education-website]');
        const locationInput = shared.querySelector('[data-cv-education-location]');
        const hideCompanyInput = shared.querySelector('[data-cv-education-hide-institution-name]');
        const isPrimaryInput = shared.querySelector('[data-cv-education-is-primary]');
        const logoPathInput = entry.querySelector('[data-cv-education-logo-path]');

        isSyncingSharedFields = true;
        entry.querySelectorAll('[data-cv-education-entry-locale-pane]').forEach(function (pane) {
            if (!(pane instanceof HTMLElement)) {
                return;
            }

            const startSync = pane.querySelector('[data-cv-education-start-date-sync]');
            if (startSync instanceof HTMLInputElement && startInput instanceof HTMLInputElement) {
                startSync.value = startInput.value;
            }

            const endSync = pane.querySelector('[data-cv-education-end-date-sync]');
            if (endSync instanceof HTMLInputElement && endInput instanceof HTMLInputElement) {
                endSync.value = endInput.value;
            }

            const isCurrentSync = pane.querySelector('[data-cv-education-is-current-sync]');
            if (isCurrentSync instanceof HTMLInputElement && isCurrentInput instanceof HTMLInputElement) {
                isCurrentSync.value = isCurrentInput.checked ? '1' : '0';
            }

            const companySync = pane.querySelector('[data-cv-education-institution-name-sync]');
            if (companySync instanceof HTMLInputElement && companyInput instanceof HTMLInputElement) {
                companySync.value = companyInput.value;
            }

            const websiteSync = pane.querySelector('[data-cv-education-website-sync]');
            if (websiteSync instanceof HTMLInputElement && websiteInput instanceof HTMLInputElement) {
                websiteSync.value = websiteInput.value;
            }

            const locationSync = pane.querySelector('[data-cv-education-location-sync]');
            if (locationSync instanceof HTMLInputElement && locationInput instanceof HTMLInputElement) {
                locationSync.value = locationInput.value;
            }

            const hideCompanySync = pane.querySelector('[data-cv-education-hide-institution-name-sync]');
            if (hideCompanySync instanceof HTMLInputElement && hideCompanyInput instanceof HTMLInputElement) {
                hideCompanySync.value = hideCompanyInput.checked ? '1' : '0';
            }

            const isPrimarySync = pane.querySelector('[data-cv-education-is-primary-sync]');
            if (isPrimarySync instanceof HTMLInputElement && isPrimaryInput instanceof HTMLInputElement) {
                isPrimarySync.value = isPrimaryInput.checked ? '1' : '0';
            }

            if (logoPathInput instanceof HTMLInputElement) {
                const logoPathSync = pane.querySelector('[data-cv-education-logo-path]');
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
        const entries = container.querySelectorAll('[data-cv-education-entry]');
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
        const logoPath = entry.querySelector('[data-cv-education-logo-path]');
        const logoFile = entry.querySelector('[data-cv-education-logo-file]');
        const removeLogo = entry.querySelector('[data-cv-education-remove-logo]');

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
    function syncInstitutionNameRequirement(entry) {
        const companyInput = entry.querySelector('[data-cv-education-institution-name]');
        const help = entry.querySelector('[data-cv-education-company-optional-help]');
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
     * @brief Reindex one education accordion item and its locale panes.
     *
     * @param {HTMLElement} entry Education accordion item.
     * @param {number} index Shared chronological index.
     * @param {string} canonicalLocale Locale used for shared field names.
     * @return {void}
     * @date 2026-06-03
     * @author Stephane H.
     */
    function reindexSingleEntry(entry, index, canonicalLocale) {
        const shared = entry.querySelector('[data-cv-education-shared-fields]');
        if (shared instanceof HTMLElement) {
            shared.querySelectorAll('[name]').forEach(function (input) {
                const name = input.getAttribute('name');
                if (!name || !name.startsWith('education_entries[')) {
                    return;
                }

                input.setAttribute(
                    'name',
                    name.replace(
                        /education_entries\[[^\]]+\]\[\d+\]/,
                        'education_entries[' + canonicalLocale + '][' + index + ']'
                    )
                );
            });

            shared.querySelectorAll('[id^="edu-"]').forEach(function (element) {
                const id = element.getAttribute('id');
                if (!id) {
                    return;
                }

                const match = /^edu-([^-]+)-(\d+)-(.+)$/.exec(id);
                if (match) {
                    element.setAttribute('id', 'edu-' + canonicalLocale + '-' + index + '-' + match[3]);
                }
            });
        }

        entry.querySelectorAll('[data-cv-education-entry-locale-pane]').forEach(function (pane) {
            if (!(pane instanceof HTMLElement)) {
                return;
            }

            const locale = pane.getAttribute('data-locale') || '';
            if (locale === '') {
                return;
            }

            pane.querySelectorAll('[name]').forEach(function (input) {
                const name = input.getAttribute('name');
                if (!name || !name.startsWith('education_entries[')) {
                    return;
                }

                input.setAttribute(
                    'name',
                    name.replace(
                        /education_entries\[[^\]]+\]\[\d+\]/,
                        'education_entries[' + locale + '][' + index + ']'
                    )
                );
            });

            pane.querySelectorAll('[id^="edu-"]').forEach(function (element) {
                const id = element.getAttribute('id');
                if (!id) {
                    return;
                }

                const match = /^edu-([^-]+)-(\d+)-(.+)$/.exec(id);
                if (match) {
                    element.setAttribute('id', 'edu-' + locale + '-' + index + '-' + match[3]);
                }
            });

            const sortInput = pane.querySelector('[data-cv-education-sort-order]');
            if (sortInput instanceof HTMLInputElement) {
                sortInput.value = String(index);
            }
        });

        const entryId = readEntryId(entry);
        updateEntryPermalinkHrefs(entry, entryId);
    }

    /**
     * @brief Wire shared-field sync and accordion summary updates for one education fieldset.
     *
     * @param {HTMLElement} entry Education fieldset root.
     * @return {void}
     * @date 2026-06-03
     * @author Stephane H.
     */
    function bindSharedFieldSync(entry) {
        const pushSharedSync = function () {
            syncSharedFieldsToAllLocalePanes(entry);
        };

        const companyInput = entry.querySelector('[data-cv-education-institution-name]');
        if (companyInput instanceof HTMLInputElement) {
            companyInput.addEventListener('input', function () {
                pushSharedSync();
                updateEntryAccordionSummary(entry);
            });
        }

        entry.querySelectorAll('[data-cv-education-title]').forEach(function (titleInput) {
            if (titleInput instanceof HTMLInputElement) {
                titleInput.addEventListener('input', function () {
                    updateEntryAccordionSummary(entry);
                });
            }
        });

        const websiteInput = entry.querySelector('[data-cv-education-website]');
        if (websiteInput instanceof HTMLInputElement) {
            websiteInput.addEventListener('input', pushSharedSync);
        }

        const locationInput = entry.querySelector('[data-cv-education-location]');
        if (locationInput instanceof HTMLInputElement) {
            locationInput.addEventListener('input', pushSharedSync);
        }

        const startDateInput = entry.querySelector('[data-cv-education-start-date]');
        if (startDateInput instanceof HTMLInputElement) {
            startDateInput.addEventListener('change', function () {
                pushSharedSync();
                updateEntryAccordionSummary(entry);
            });
        }

        const endDateInput = entry.querySelector('[data-cv-education-end-date]');
        if (endDateInput instanceof HTMLInputElement) {
            endDateInput.addEventListener('change', function () {
                pushSharedSync();
                updateEntryAccordionSummary(entry);
            });
        }

        const hideCompany = entry.querySelector('[data-cv-education-hide-institution-name]');
        if (hideCompany instanceof HTMLInputElement) {
            hideCompany.addEventListener('change', function () {
                pushSharedSync();
                updateEntryAccordionSummary(entry);
            });
        }

        const isPrimary = entry.querySelector('[data-cv-education-is-primary]');
        if (isPrimary instanceof HTMLInputElement) {
            isPrimary.addEventListener('change', pushSharedSync);
        }

        const removeLogo = entry.querySelector('[data-cv-education-remove-logo]');
        if (removeLogo instanceof HTMLInputElement) {
            removeLogo.addEventListener('change', function () {
                pushSharedSync();
                syncInstitutionNameRequirement(entry);
            });
        }

        const logoFile = entry.querySelector('[data-cv-education-logo-file]');
        if (logoFile instanceof HTMLInputElement) {
            logoFile.addEventListener('change', function () {
                if (!logoFile.files || logoFile.files.length === 0) {
                    pushSharedSync();
                    syncInstitutionNameRequirement(entry);

                    return;
                }

                const file = logoFile.files[0];
                const reader = new FileReader();
                reader.addEventListener('load', function () {
                    let preview = entry.querySelector('[data-cv-education-logo-preview]');
                    if (!preview) {
                        preview = document.createElement('div');
                        preview.setAttribute('data-cv-education-logo-preview', '');
                        preview.className = 'mt-2';
                        preview.innerHTML =
                            '<img class="cv-education-customization__logo-preview img-thumbnail" alt="">';
                        logoFile.parentElement?.insertAdjacentElement('afterend', preview);
                    }

                    const image = preview.querySelector('img');
                    if (image instanceof HTMLImageElement && typeof reader.result === 'string') {
                        image.src = reader.result;
                    }

                    const removeCheckbox = entry.querySelector('[data-cv-education-remove-logo]');
                    if (removeCheckbox instanceof HTMLInputElement) {
                        removeCheckbox.checked = false;
                    }

                    pushSharedSync();
                    syncInstitutionNameRequirement(entry);
                });
                reader.readAsDataURL(file);
            });
        }
    }

    /**
     * @param {HTMLElement} entry
     */
    function bindEntry(entry) {
        const isCurrent = entry.querySelector('[data-cv-education-is-current]');
        const endDate = entry.querySelector('[data-cv-education-end-date]');

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

        syncInstitutionNameRequirement(entry);
        bindSharedFieldSync(entry);

        entry.querySelectorAll('[data-cv-education-move]').forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();

                const container = getEntriesRoot();
                if (!container) {
                    return;
                }

                const direction = button.getAttribute('data-cv-education-move');
                if (direction === 'up' && entry.previousElementSibling) {
                    container.insertBefore(entry, entry.previousElementSibling);
                } else if (direction === 'down' && entry.nextElementSibling) {
                    container.insertBefore(entry.nextElementSibling, entry);
                }

                reindexAllLocaleEntries();
                syncActiveEducationDeepLinkFromDom();
                logEducationAdmin('move', {
                    direction: direction,
                    entryId: readEntryId(entry),
                    after: summarizeDomEntries(),
                });
            });
        });

        const removeBtn = entry.querySelector('[data-cv-education-remove]');
        if (removeBtn) {
            removeBtn.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();

                const idInput = entry.querySelector('[data-cv-education-id]');
                const entryId = idInput instanceof HTMLInputElement ? idInput.value.trim() : '';

                if (confirmDeleteMessage !== '' && !window.confirm(confirmDeleteMessage)) {
                    logEducationAdmin('remove_cancelled', { entryId: entryId });

                    return;
                }

                removeEntryFromAllLocales(entryId, entry, { autoSubmit: true });
            });
        }

        entry.querySelectorAll('[data-cv-education-highlight-row]').forEach(bindHighlightRow);
        entry.querySelectorAll('[data-cv-education-add-highlight]').forEach(function (addBtn) {
            addBtn.addEventListener('click', function () {
                const list = entry.querySelector('[data-cv-education-highlights]');
                const firstRow = entry.querySelector('[data-cv-education-highlight-row]');
                if (!list) {
                    return;
                }

                const row = firstRow ? firstRow.cloneNode(true) : document.createElement('div');
                if (!firstRow) {
                    row.className = 'input-group';
                    row.setAttribute('data-cv-education-highlight-row', '');
                    row.innerHTML =
                        '<input type="text" class="form-control" maxlength="500">' +
                        '<button type="button" class="btn btn-outline-danger" data-cv-education-remove-highlight></button>';
                }

                const input = row.querySelector('input');
                if (input instanceof HTMLInputElement) {
                    input.value = '';
                    if (firstRow) {
                        const templateInput = firstRow.querySelector('input');
                        const name = templateInput instanceof HTMLInputElement ? templateInput.getAttribute('name') : '';
                        if (name) {
                            input.setAttribute('name', name);
                        }
                    }
                }

                list.appendChild(row);
                bindHighlightRow(row);
            });
        });
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

        return container.querySelectorAll('[data-cv-education-entry]').length;
    }

    /**
     * @brief Append one education accordion with all locale panes.
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
        logEducationAdmin('append_accordion', {
            entryId: entryId,
            index: index,
            after: summarizeDomEntries(),
        });

        return entry;
    }

    /**
     * @brief Read admin i18n strings from the root data attribute.
     *
     * @return {{ validationRequired: string, validationTitleLocale: string, validationInstitutionOrLogo: string }}
     * @date 2026-06-03
     * @author Stephane H.
     */
    function readEducationI18n() {
        const fallback = {
            validationRequired: '',
            validationTitleLocale: '',
            validationInstitutionOrLogo: '',
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
                validationInstitutionOrLogo:
                    typeof parsed.validationInstitutionOrLogo === 'string'
                        ? parsed.validationInstitutionOrLogo
                        : fallback.validationInstitutionOrLogo,
            };
        } catch (e) {
            return fallback;
        }
    }

    const educationI18n = readEducationI18n();
    const formValidationAlert = root.querySelector('[data-cv-education-form-validation]');
    const customizationEntryInput = root.querySelector('[data-cv-education-customization-entry]');

    /**
     * @brief Read permalink route and base query params from the admin root element.
     *
     * @return {{ route: string, base: Record<string, string> }}
     * @date 2026-06-03
     * @author Stephane H.
     */
    function readPermalinkConfig() {
        const route = root.getAttribute('data-cv-education-permalink-route') || 'admin_cv_index';
        const fallbackBase = { tab: 'education', panel: 'education_entries' };
        const raw = root.getAttribute('data-cv-education-permalink-base') || '';

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
     * @param {HTMLElement} entry Education accordion item.
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
            const toggle = entry.querySelector('[data-cv-education-entry-toggle]');
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

            entry.querySelectorAll('[data-cv-education-entry-locale-tab]').forEach(function (tab) {
                if (!(tab instanceof HTMLElement)) {
                    return;
                }

                const locale = tab.getAttribute('data-cv-education-entry-locale-tab') || '';
                const permalink = tab.getAttribute('data-cv-education-entry-locale-permalink') || '';
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
                        'data-cv-education-entry-locale-permalink',
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
     * @param {HTMLElement} entry Education accordion item.
     * @return {void}
     * @date 2026-06-03
     * @author Stephane H.
     */
    function updateEntryAccordionSummary(entry) {
        const titleEl = entry.querySelector('[data-cv-education-entry-summary-title]');
        const metaEl = entry.querySelector('[data-cv-education-entry-summary-meta]');
        const companyInput = entry.querySelector('[data-cv-education-institution-name]');
        const hideCompany = entry.querySelector('[data-cv-education-hide-institution-name]');
        const startInput = entry.querySelector('[data-cv-education-start-date]');
        const endInput = entry.querySelector('[data-cv-education-end-date]');
        const isCurrentInput = entry.querySelector('[data-cv-education-is-current]');

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
        const hideInstitutionName = hideCompany instanceof HTMLInputElement && hideCompany.checked;
        const companyValue = companyInput instanceof HTMLInputElement ? companyInput.value.trim() : '';
        if (companyValue !== '' && !hideInstitutionName) {
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
    function setActiveEducationEntryInUrl(entryId, locale) {
        try {
            const cfg = readPermalinkConfig();
            const url = new URL(window.location.href);
            const urlTab = url.searchParams.get('tab') || '';
            if (urlTab !== '' && urlTab !== 'education') {
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
    function syncActiveEducationDeepLinkFromDom() {
        const openCollapse = root.querySelector('[data-cv-education-entry-collapse].show');
        if (!(openCollapse instanceof HTMLElement)) {
            return;
        }

        const entryId = openCollapse.getAttribute('data-cv-education-entry-collapse') || '';
        if (entryId === '') {
            return;
        }

        const entryRoot = openCollapse.closest('[data-cv-education-entry]');
        const locale =
            entryRoot instanceof HTMLElement ? readActiveLocaleForEntry(entryRoot) : resolveCanonicalLocale();

        setActiveEducationEntryInUrl(entryId, locale);
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
        const entry = findEducationEntryById(entryId);
        if (!entry || locale === '') {
            return;
        }

        const tab = entry.querySelector('[data-cv-education-entry-locale-tab="' + locale + '"]');
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
    function openEducationEntryAccordion(entryId, locale) {
        if (entryId === '') {
            return;
        }

        const collapse = root.querySelector('[data-cv-education-entry-collapse="' + entryId + '"]');
        if (!(collapse instanceof HTMLElement)) {
            return;
        }

        if (typeof window.bootstrap !== 'undefined' && window.bootstrap.Collapse) {
            window.bootstrap.Collapse.getOrCreateInstance(collapse, { toggle: false }).show();
        } else {
            collapse.classList.add('show');
        }

        activateEntryLocaleTab(entryId, locale);
        setActiveEducationEntryInUrl(entryId, locale);
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
     * @brief Fill one appended education fieldset from modal payload data.
     *
     * @param {HTMLElement} entry Education fieldset in the main form.
     * @param {string} locale Locale code.
     * @param {{ shared: object, locales: Record<string, { title: string, detailHtml: string }> }} payload Modal data.
     * @return {void}
     * @date 2026-06-03
     * @author Stephane H.
     */
    function populateEntryFromModalData(entry, locale, payload) {
        const localeData = payload.locales[locale] || { title: '', highlights: [] };
        const shared = payload.shared;
        const localePane = findLocalePaneInEntry(entry, locale);

        if (localePane) {
            const titleInput = localePane.querySelector('[data-cv-education-title]');
            if (titleInput instanceof HTMLInputElement) {
                titleInput.value = localeData.title || '';
            }

            setHighlightsInLocalePane(localePane, localeData.highlights || []);
        }

        const startInput = entry.querySelector('[data-cv-education-start-date]');
        if (startInput instanceof HTMLInputElement) {
            startInput.value = shared.startDate || '';
        }

        const endInput = entry.querySelector('[data-cv-education-end-date]');
        if (endInput instanceof HTMLInputElement) {
            endInput.value = shared.isCurrent ? '' : shared.endDate || '';
            endInput.disabled = shared.isCurrent;
        }

        const isCurrentInput = entry.querySelector('[data-cv-education-is-current]');
        if (isCurrentInput instanceof HTMLInputElement) {
            isCurrentInput.checked = shared.isCurrent;
        }

        const companyInput = entry.querySelector('[data-cv-education-institution-name]');
        if (companyInput instanceof HTMLInputElement) {
            companyInput.value = shared.institutionName || '';
        }

        const websiteInput = entry.querySelector('[data-cv-education-website]');
        if (websiteInput instanceof HTMLInputElement) {
            websiteInput.value = shared.institutionWebsiteUrl || '';
        }

        const locationInput = entry.querySelector('[data-cv-education-location]');
        if (locationInput instanceof HTMLInputElement) {
            locationInput.value = shared.location || '';
        }

        const hideCompanyInput = entry.querySelector('[data-cv-education-hide-institution-name]');
        if (hideCompanyInput instanceof HTMLInputElement) {
            hideCompanyInput.checked = shared.hideInstitutionName;
        }

        const isPrimaryInput = entry.querySelector('[data-cv-education-is-primary]');
        if (isPrimaryInput instanceof HTMLInputElement) {
            isPrimaryInput.checked = shared.isPrimary;
        }

        syncSharedFieldsToAllLocalePanes(entry);
        syncInstitutionNameRequirement(entry);
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

        const entry = findEducationEntryById(entryId);
        if (!entry) {
            return;
        }

        const logoInput = entry.querySelector('[data-cv-education-logo-file]');
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
     * @return {void}
     * @date 2026-06-03
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
        openEducationEntryAccordion(entryId, resolveCanonicalLocale());
        logEducationAdmin('modal_add_commit', {
            entryId: entryId,
            locales: Object.keys(payload.locales || {}),
            after: summarizeDomEntries(),
        });
    }

    const addModalElement = document.getElementById('cvEducationAddModal');
    const addForm = root.querySelector('[data-cv-education-add-form]');
    const addConfirmButton = root.querySelector('[data-cv-education-modal-confirm]');
    const validationAlert = root.querySelector('[data-cv-education-modal-validation]');
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
    function resetEducationAddModal() {
        if (!addForm) {
            return;
        }

        addForm.reset();
        setModalValidationMessage('');

        const isPrimary = addForm.querySelector('[data-cv-education-modal-is-primary]');
        if (isPrimary instanceof HTMLInputElement) {
            isPrimary.checked = true;
        }

        const preview = addForm.querySelector('[data-cv-education-modal-logo-preview]');
        if (preview instanceof HTMLElement) {
            preview.classList.add('d-none');
            const image = preview.querySelector('img');
            if (image instanceof HTMLImageElement) {
                image.removeAttribute('src');
            }
        }

        const companyHelp = addForm.querySelector('[data-cv-education-modal-institution-optional-help]');
        if (companyHelp) {
            companyHelp.classList.add('d-none');
        }

        resolveLocalesForSync().forEach(function (locale) {
            const highlightsTextarea = addForm.querySelector(
                '[data-cv-education-modal-highlights][data-locale="' + locale + '"]'
            );
            if (highlightsTextarea instanceof HTMLTextAreaElement) {
                highlightsTextarea.value = '';
            }
        });

        const endDate = addForm.querySelector('[data-cv-education-modal-end-date]');
        const isCurrent = addForm.querySelector('[data-cv-education-modal-is-current]');
        if (endDate instanceof HTMLInputElement && isCurrent instanceof HTMLInputElement) {
            endDate.disabled = isCurrent.checked;
        }

        const companyInput = addForm.querySelector('[data-cv-education-modal-institution-name]');
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

        const isCurrent = addForm.querySelector('[data-cv-education-modal-is-current]');
        const endDate = addForm.querySelector('[data-cv-education-modal-end-date]');
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

        const logoFile = addForm.querySelector('[data-cv-education-modal-logo-file]');
        const companyInput = addForm.querySelector('[data-cv-education-modal-institution-name]');
        const companyHelp = addForm.querySelector('[data-cv-education-modal-institution-optional-help]');
        const preview = addForm.querySelector('[data-cv-education-modal-logo-preview]');

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
     * @brief Split modal highlights textarea into trimmed non-empty lines.
     *
     * @param {string} text Raw textarea value.
     * @return {string[]}
     * @date 2026-06-08
     * @author Stephane H.
     */
    function parseHighlightsText(text) {
        return String(text || '')
            .split('\n')
            .map(function (line) {
                return line.trim();
            })
            .filter(function (line) {
                return line !== '';
            });
    }

    /**
     * @brief Collect highlight lines for one modal locale pane.
     *
     * @param {string} locale Locale code.
     * @return {string[]}
     * @date 2026-06-08
     * @author Stephane H.
     */
    function collectModalHighlightsForLocale(locale) {
        const textarea = addForm
            ? addForm.querySelector('[data-cv-education-modal-highlights][data-locale="' + locale + '"]')
            : null;

        return textarea instanceof HTMLTextAreaElement ? parseHighlightsText(textarea.value) : [];
    }

    /**
     * @brief Wire highlight row remove handler.
     *
     * @param {HTMLElement} row Highlight input group row.
     * @return {void}
     * @date 2026-06-08
     * @author Stephane H.
     */
    function bindHighlightRow(row) {
        const removeBtn = row.querySelector('[data-cv-education-remove-highlight]');
        if (!removeBtn || removeBtn.dataset.bound === '1') {
            return;
        }

        removeBtn.dataset.bound = '1';
        removeBtn.addEventListener('click', function () {
            row.remove();
        });
    }

    /**
     * @brief Render highlight rows inside one locale pane.
     *
     * @param {HTMLElement} localePane Locale tab pane.
     * @param {string[]} highlights Highlight lines.
     * @return {void}
     * @date 2026-06-08
     * @author Stephane H.
     */
    function setHighlightsInLocalePane(localePane, highlights) {
        const list = localePane.querySelector('[data-cv-education-highlights]');
        if (!(list instanceof HTMLElement)) {
            return;
        }

        let fieldPrefix = '';
        const titleInput = localePane.querySelector('[data-cv-education-title]');
        if (titleInput instanceof HTMLInputElement) {
            const name = titleInput.getAttribute('name') || '';
            const match = /^(education_entries\[[^\]]+\]\[\d+\])/.exec(name);
            fieldPrefix = match ? match[1] : '';
        }

        list.innerHTML = '';
        highlights.forEach(function (highlight) {
            const row = document.createElement('div');
            row.className = 'input-group';
            row.setAttribute('data-cv-education-highlight-row', '');
            row.innerHTML =
                '<input type="text" class="form-control" maxlength="500">' +
                '<button type="button" class="btn btn-outline-danger" data-cv-education-remove-highlight></button>';
            const input = row.querySelector('input');
            if (input instanceof HTMLInputElement) {
                input.value = highlight;
                if (fieldPrefix !== '') {
                    input.setAttribute('name', fieldPrefix + '[highlights][]');
                }
            }
            list.appendChild(row);
            bindHighlightRow(row);
        });
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

        const startDate = addForm.querySelector('[data-cv-education-modal-start-date]');
        const endDate = addForm.querySelector('[data-cv-education-modal-end-date]');
        const isCurrent = addForm.querySelector('[data-cv-education-modal-is-current]');
        const companyInput = addForm.querySelector('[data-cv-education-modal-institution-name]');
        const websiteInput = addForm.querySelector('[data-cv-education-modal-website]');
        const locationInput = addForm.querySelector('[data-cv-education-modal-location]');
        const hideCompany = addForm.querySelector('[data-cv-education-modal-hide-institution-name]');
        const isPrimary = addForm.querySelector('[data-cv-education-modal-is-primary]');
        const logoFile = addForm.querySelector('[data-cv-education-modal-logo-file]');

        if (!(startDate instanceof HTMLInputElement) || startDate.value.trim() === '') {
            setModalValidationMessage(educationI18n.validationRequired);

            return null;
        }

        const isCurrentChecked = isCurrent instanceof HTMLInputElement && isCurrent.checked;
        if (!isCurrentChecked && endDate instanceof HTMLInputElement && endDate.value.trim() === '') {
            setModalValidationMessage(educationI18n.validationRequired);

            return null;
        }

        const institutionName = companyInput instanceof HTMLInputElement ? companyInput.value.trim() : '';
        const hasLogo =
            logoFile instanceof HTMLInputElement && logoFile.files !== null && logoFile.files.length > 0;
        if (institutionName === '' && !hasLogo) {
            setModalValidationMessage(educationI18n.validationInstitutionOrLogo);

            return null;
        }

        const localesPayload = {};
        const locales = resolveLocalesForSync();
        for (let index = 0; index < locales.length; index += 1) {
            const locale = locales[index];
            const titleInput = addForm.querySelector('[data-cv-education-modal-title][data-locale="' + locale + '"]');
            const title = titleInput instanceof HTMLInputElement ? titleInput.value.trim() : '';
            if (title === '') {
                const message = educationI18n.validationTitleLocale.replace('%code%', locale.toUpperCase());
                setModalValidationMessage(message);

                return null;
            }

            const highlights = collectModalHighlightsForLocale(locale);
            localesPayload[locale] = {
                title: title,
                highlights: highlights,
            };
        }

        setModalValidationMessage('');

        return {
            shared: {
                startDate: startDate.value.trim(),
                endDate: isCurrentChecked ? '' : endDate instanceof HTMLInputElement ? endDate.value.trim() : '',
                isCurrent: isCurrentChecked,
                institutionName: institutionName,
                institutionWebsiteUrl: websiteInput instanceof HTMLInputElement ? websiteInput.value.trim() : '',
                location: locationInput instanceof HTMLInputElement ? locationInput.value.trim() : '',
                hideInstitutionName: hideCompany instanceof HTMLInputElement && hideCompany.checked,
                isPrimary: !(isPrimary instanceof HTMLInputElement) || isPrimary.checked,
                logoFile: hasLogo && logoFile instanceof HTMLInputElement ? logoFile.files[0] : null,
            },
            locales: localesPayload,
        };
    }

    bindModalIsCurrentToggle();
    bindModalLogoRequirement();

    if (addConfirmButton) {
        addConfirmButton.addEventListener('click', function () {
            const payload = collectValidatedModalPayload();
            if (!payload) {
                return;
            }

            commitModalEntry(payload);
            if (addModal) {
                addModal.hide();
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
            educationForm instanceof HTMLFormElement;

        logEducationAdmin('remove_start', {
            entryId: entryId,
            before: summarizeDomEntries(),
        });

        if (entryId !== '') {
            try {
                const url = new URL(window.location.href);
                if (url.searchParams.get('entry') === entryId) {
                    setActiveEducationEntryInUrl('', '');
                }
            } catch (e) {
                // Ignore URL parse errors before DOM removal.
            }
        }

        if (entryId === '') {
            fallbackEntry.remove();
            reindexAllLocaleEntries();
            logEducationAdmin('remove_done', {
                entryId: '',
                after: summarizeDomEntries(),
                persistedToServer: willAutoSubmit,
            });

            if (willAutoSubmit) {
                educationForm.requestSubmit();
            }

            return;
        }

        const entry = findEducationEntryById(entryId);
        if (entry) {
            entry.remove();
        }

        reindexAllLocaleEntries();
        logEducationAdmin('remove_done', {
            entryId: entryId,
            after: summarizeDomEntries(),
            persistedToServer: willAutoSubmit,
        });

        if (willAutoSubmit) {
            educationForm.requestSubmit();
        }
    }

    root.querySelectorAll('[data-cv-education-entry]').forEach(function (entry) {
        if (entry instanceof HTMLElement) {
            bindEntry(entry);
            syncSharedFieldsToAllLocalePanes(entry);
            updateEntryPermalinkHrefs(entry, readEntryId(entry));
        }
    });

    root.querySelectorAll('[data-cv-education-entry-locale-tab]').forEach(function (tab) {
        tab.addEventListener('shown.bs.tab', function (event) {
            const target = event.target;
            if (!(target instanceof HTMLElement)) {
                return;
            }

            const activeMainTab = document.querySelector('#cvCustomizationTabs .nav-link.active[data-cv-tab]');
            const domTab =
                activeMainTab instanceof HTMLElement ? activeMainTab.getAttribute('data-cv-tab') || '' : '';
            if (domTab !== '' && domTab !== 'education') {
                return;
            }

            const entryRoot = target.closest('[data-cv-education-entry]');
            const locale = target.getAttribute('data-cv-education-entry-locale-tab') || '';
            if (entryRoot instanceof HTMLElement && locale !== '') {
                updateEntryPermalinkHrefs(entryRoot, readEntryId(entryRoot));
                setActiveEducationEntryInUrl(readEntryId(entryRoot), locale);
            }

            if (locale !== '') {
                showPreviewForLocale(locale);
            }
        });
    });

    if (educationForm instanceof HTMLFormElement) {
        educationForm.addEventListener('submit', function (event) {
            syncActiveEducationDeepLinkFromDom();
            logEducationAdmin('form_submit', {
                entries: summarizeDomEntries(),
                customizationEntry:
                    customizationEntryInput instanceof HTMLInputElement
                        ? customizationEntryInput.value
                        : '',
            });

            root.querySelectorAll('[data-cv-education-entry]').forEach(function (entry) {
                if (entry instanceof HTMLElement) {
                    syncSharedFieldsToAllLocalePanes(entry);
                }
            });
        });
    }

    root.querySelectorAll('[data-cv-education-add]').forEach(function (button) {
        button.addEventListener('click', function () {
            resetEducationAddModal();
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
    function bootstrapEducationDeepLinkFromUrl() {
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
        if ((urlTab !== '' && urlTab !== 'education') || (domTab !== '' && domTab !== 'education')) {
            return;
        }

        openEducationEntryAccordion(entryId, locale);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootstrapEducationDeepLinkFromUrl);
    } else {
        bootstrapEducationDeepLinkFromUrl();
    }
})();
