(function () {

    'use strict';



    const root = document.querySelector('[data-cv-certification-admin]');

    if (!root) {

        return;

    }



    const activeLocales = JSON.parse(root.getAttribute('data-active-locales') || '[]');

    const defaultLocale = root.getAttribute('data-default-locale') || 'fr';

    const i18n = JSON.parse(root.getAttribute('data-i18n') || '{}');

    const mainForm = root.querySelector('.cv-certification-customization__form');

    const storageEl = root.querySelector('[data-cv-certification-entries-storage]');

    const listEl = root.querySelector('[data-cv-certification-list]');

    const listEmptyEl = root.querySelector('[data-cv-certification-list-empty]');

    const entryModalEl = document.getElementById('cvCertificationEntryModal');

    const entryFormEl = root.querySelector('[data-cv-certification-entry-form]');

    const startDateInput = root.querySelector('[data-cv-certification-entry-start-date]');

    const endDateInput = root.querySelector('[data-cv-certification-entry-end-date]');

    const isCurrentInput = root.querySelector('[data-cv-certification-entry-is-current]');

    const websiteInput = root.querySelector('[data-cv-certification-entry-website]');

    const proofFileInput = root.querySelector('[data-cv-certification-entry-proof-file]');

    const proofUrlInput = root.querySelector('[data-cv-certification-entry-proof-url]');

    const proofCurrentWrap = root.querySelector('[data-cv-certification-entry-proof-current-wrap]');

    const proofCurrentLink = root.querySelector('[data-cv-certification-entry-proof-current-link]');

    const removeProofWrap = root.querySelector('[data-cv-certification-entry-remove-proof-wrap]');

    const removeProofInput = root.querySelector('[data-cv-certification-entry-remove-proof]');

    const isPrimaryInput = root.querySelector('[data-cv-certification-entry-is-primary]');

    const entrySaveBtn = root.querySelector('[data-cv-certification-entry-save]');



    /** @type {Array<object>} */

    let entries = [];

    /** @type {number|null} */

    let editingIndex = null;



    try {

        const dataEl = document.getElementById('cv-certification-entries-data');

        entries = JSON.parse(dataEl?.textContent || '[]');

        if (!Array.isArray(entries)) {

            entries = [];

        }

        entries = entries.map(normalizeLoadedEntry);

    } catch (error) {

        entries = [];

    }



    /**

     * @brief Normalize a certification entry loaded from server JSON.

     *

     * @param {object} entry Raw entry object.

     * @return {object} Normalized entry.

     * @date 2026-06-11

     * @author Stephane H.

     */

    function normalizeLoadedEntry(entry) {

        const titleByLocale = entry.titleByLocale || {};

        const providerNameByLocale = entry.providerNameByLocale || {};

        const locationByLocale = entry.locationByLocale || {};

        const highlightsByLocale = entry.highlightsByLocale || {};



        if (entry.title && !titleByLocale[defaultLocale]) {

            titleByLocale[defaultLocale] = entry.title;

        }

        if (entry.providerName && !providerNameByLocale[defaultLocale]) {

            providerNameByLocale[defaultLocale] = entry.providerName;

        }

        if (entry.location && !locationByLocale[defaultLocale]) {

            locationByLocale[defaultLocale] = entry.location;

        }

        if (Array.isArray(entry.highlights) && entry.highlights.length > 0 && !highlightsByLocale[defaultLocale]) {

            highlightsByLocale[defaultLocale] = entry.highlights.slice();

        }



        return {

            id: entry.id || '',

            sortOrder: entry.sortOrder || 0,

            startDate: entry.startDate || '',

            endDate: entry.endDate || '',

            isCurrent: Boolean(entry.isCurrent),

            titleByLocale: titleByLocale,

            providerNameByLocale: providerNameByLocale,

            locationByLocale: locationByLocale,

            providerWebsiteUrl: entry.providerWebsiteUrl || '',

            proofPdfPath: entry.proofPdfPath || '',

            proofUrl: entry.proofUrl || '',

            highlightsByLocale: highlightsByLocale,

            isPrimary: entry.isPrimary !== false,

            pendingProofFile: null,

            removeProofPdf: false

        };

    }



    /**

     * @brief Resolve Bootstrap modal instance for an element.

     *

     * @param {HTMLElement|null} element Modal root element.

     * @return {import('bootstrap').Modal|null}

     * @date 2026-06-11

     * @author Stephane H.

     */

    function getModal(element) {

        if (!element || typeof bootstrap === 'undefined') {

            return null;

        }



        return bootstrap.Modal.getOrCreateInstance(element);

    }



    const entryModal = getModal(entryModalEl);



    /**

     * @brief Generate a RFC-4122-like UUID v4 string.

     *

     * @return {string}

     * @date 2026-06-11

     * @author Stephane H.

     */

    function generateUuid() {

        if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {

            return crypto.randomUUID();

        }



        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (char) {

            const rand = Math.floor(Math.random() * 16);

            const value = char === 'x' ? rand : (rand & 0x3) | 0x8;



            return value.toString(16);

        });

    }



    /**

     * @brief Escape HTML for safe list rendering.

     *

     * @param {string} value Raw text.

     * @return {string}

     * @date 2026-06-11

     * @author Stephane H.

     */

    function escapeHtml(value) {

        return String(value)

            .replace(/&/g, '&amp;')

            .replace(/</g, '&lt;')

            .replace(/>/g, '&gt;')

            .replace(/"/g, '&quot;');

    }



    /**

     * @brief Build period label for list meta line.

     *

     * @param {object} entry Certification entry.

     * @return {string}

     * @date 2026-06-11

     * @author Stephane H.

     */

    function buildPeriodLabel(entry) {

        const startValue = String(entry.startDate || '').trim();

        if (startValue === '') {

            return '';

        }



        if (entry.isCurrent) {

            return startValue + ' – ' + (i18n.periodPresent || '');

        }



        const endValue = String(entry.endDate || '').trim();

        if (endValue !== '') {

            return startValue + ' – ' + endValue;

        }



        return startValue;

    }



    /**

     * @brief Resolve localized map value with fallback chain.

     *

     * @param {Record<string, string>} map Localized values.

     * @return {string}

     * @date 2026-06-11

     * @author Stephane H.

     */

    function resolveLocalizedValue(map) {

        const values = map || {};

        if (String(values[defaultLocale] || '').trim() !== '') {

            return String(values[defaultLocale]).trim();

        }



        for (let i = 0; i < activeLocales.length; i += 1) {

            const locale = activeLocales[i];

            if (String(values[locale] || '').trim() !== '') {

                return String(values[locale]).trim();

            }

        }



        return '';

    }



    /**

     * @brief Resolve display title for a certification entry.

     *

     * @param {object} entry Certification entry.

     * @param {number} index Zero-based index.

     * @return {string}

     * @date 2026-06-11

     * @author Stephane H.

     */

    function resolveEntryTitle(entry, index) {

        const title = resolveLocalizedValue(entry.titleByLocale);

        if (title !== '') {

            return title;

        }



        return (i18n.entryFallback || 'Certification __INDEX__')

            .replace('__INDEX__', String(index + 1))

            .replace(/%index%/g, String(index + 1));

    }



    /**

     * @brief Re-render certification list from in-memory state.

     *

     * @return {void}

     * @date 2026-06-11

     * @author Stephane H.

     */

    function renderList() {

        if (!listEl) {

            return;

        }



        listEl.innerHTML = '';

        entries.forEach(function (entry, index) {

            const metaParts = [];

            const provider = resolveLocalizedValue(entry.providerNameByLocale);

            if (provider !== '') {

                metaParts.push(provider);

            }

            const period = buildPeriodLabel(entry);

            if (period !== '') {

                metaParts.push(period);

            }



            const item = document.createElement('li');

            item.className = 'list-group-item cv-certification-admin-list__item';

            item.setAttribute('data-cv-certification-entry', '');

            item.setAttribute('data-entry-index', String(index));

            item.innerHTML = ''

                + '<div class="cv-certification-admin-list__row">'

                + '<span class="cv-certification-admin-list__title">' + escapeHtml(resolveEntryTitle(entry, index)) + '</span>'

                + (metaParts.length > 0

                    ? '<span class="cv-certification-admin-list__meta text-muted small">' + escapeHtml(metaParts.join(' · ')) + '</span>'

                    : '')

                + '<span class="cv-certification-admin-list__connector" aria-hidden="true"></span>'

                + '<div class="btn-group btn-group-sm cv-certification-admin-list__actions">'

                + '<button type="button" class="btn btn-outline-secondary" data-cv-certification-move="up" aria-label="' + escapeHtml(i18n.moveUpAria || '') + '">&uarr;</button>'

                + '<button type="button" class="btn btn-outline-secondary" data-cv-certification-move="down" aria-label="' + escapeHtml(i18n.moveDownAria || '') + '">&darr;</button>'

                + '<button type="button" class="btn btn-outline-primary" data-cv-certification-edit>' + escapeHtml(i18n.entryTitleEdit || '') + '</button>'

                + '<button type="button" class="btn btn-outline-danger" data-cv-certification-remove>' + escapeHtml(i18n.removeEntry || '') + '</button>'

                + '</div>'

                + '</div>';

            listEl.appendChild(item);

        });



        if (listEmptyEl) {

            listEmptyEl.classList.toggle('d-none', entries.length > 0);

        }

    }



    /**

     * @brief Sync hidden POST fields for certification_entries[index].

     *

     * @return {void}

     * @date 2026-06-11

     * @author Stephane H.

     */

    function syncHiddenFields() {

        if (!storageEl) {

            return;

        }



        storageEl.innerHTML = '';

        entries.forEach(function (entry, index) {

            const prefix = 'certification_entries[' + index + ']';

            const fields = [

                { name: prefix + '[id]', value: entry.id || '' },

                { name: prefix + '[sortOrder]', value: String(index) },

                { name: prefix + '[startDate]', value: entry.startDate || '' },

                { name: prefix + '[endDate]', value: entry.endDate || '' },

                { name: prefix + '[isCurrent]', value: entry.isCurrent ? '1' : '0' },

                { name: prefix + '[providerWebsiteUrl]', value: entry.providerWebsiteUrl || '' },

                { name: prefix + '[proofPdfPath]', value: entry.proofPdfPath || '' },

                { name: prefix + '[proofUrl]', value: entry.proofUrl || '' },

                { name: prefix + '[isPrimary]', value: entry.isPrimary ? '1' : '0' }

            ];



            activeLocales.forEach(function (locale) {

                fields.push({

                    name: prefix + '[titleByLocale][' + locale + ']',

                    value: (entry.titleByLocale || {})[locale] || ''

                });

                fields.push({

                    name: prefix + '[providerNameByLocale][' + locale + ']',

                    value: (entry.providerNameByLocale || {})[locale] || ''

                });

                fields.push({

                    name: prefix + '[locationByLocale][' + locale + ']',

                    value: (entry.locationByLocale || {})[locale] || ''

                });

                const highlights = ((entry.highlightsByLocale || {})[locale] || []);

                highlights.forEach(function (highlight) {

                    fields.push({

                        name: prefix + '[highlightsByLocale][' + locale + '][]',

                        value: highlight || ''

                    });

                });

            });



            if (entry.removeProofPdf) {

                fields.push({ name: 'certification_remove_proof_pdf[' + (entry.id || '') + ']', value: '1' });

            }



            fields.forEach(function (field) {

                const input = document.createElement('input');

                input.type = 'hidden';

                input.name = field.name;

                input.value = field.value;

                storageEl.appendChild(input);

            });

        });

    }



    /**

     * @brief Clear highlight rows for one locale in the modal.

     *

     * @param {string} locale Locale code.

     * @return {void}

     * @date 2026-06-11

     * @author Stephane H.

     */

    function clearHighlightsForLocale(locale) {

        const list = root.querySelector('[data-cv-certification-entry-highlights-locale="' + locale + '"]');

        if (list) {

            list.innerHTML = '';

        }

    }



    /**

     * @brief Clear all highlight rows in the certification modal.

     *

     * @return {void}

     * @date 2026-06-11

     * @author Stephane H.

     */

    function clearAllHighlights() {

        activeLocales.forEach(clearHighlightsForLocale);

    }



    /**

     * @brief Append one highlight row to the certification modal for a locale.

     *

     * @param {string} locale Locale code.

     * @param {string} value Initial highlight text.

     * @return {void}

     * @date 2026-06-11

     * @author Stephane H.

     */

    function addHighlightRow(locale, value) {

        const highlightsList = root.querySelector('[data-cv-certification-entry-highlights-locale="' + locale + '"]');

        if (!highlightsList) {

            return;

        }



        const row = document.createElement('div');

        row.className = 'input-group';

        row.setAttribute('data-cv-certification-entry-highlight-row', '');

        row.setAttribute('data-cv-certification-entry-highlight-locale', locale);

        row.innerHTML = ''

            + '<input type="text" class="form-control" maxlength="500" data-cv-certification-entry-highlight-input data-cv-certification-entry-highlight-locale="' + locale + '" value="' + escapeHtml(value || '') + '">'

            + '<button type="button" class="btn btn-outline-danger" data-cv-certification-entry-remove-highlight>'

            + escapeHtml(i18n.removeHighlight || '')

            + '</button>';

        highlightsList.appendChild(row);

    }



    /**

     * @brief Toggle proof PDF controls in the modal.

     *

     * @param {string} proofPdfPath Existing proof path.

     * @return {void}

     * @date 2026-06-11

     * @author Stephane H.

     */

    function syncProofControls(proofPdfPath) {

        const hasProof = String(proofPdfPath || '').trim() !== '';



        if (proofCurrentWrap instanceof HTMLElement) {

            proofCurrentWrap.classList.toggle('d-none', !hasProof);

        }

        if (proofCurrentLink instanceof HTMLAnchorElement && hasProof) {

            proofCurrentLink.href = '/' + String(proofPdfPath).replace(/^\//, '');

        }

        if (removeProofWrap instanceof HTMLElement) {

            removeProofWrap.classList.toggle('d-none', !hasProof);

        }

        if (removeProofInput instanceof HTMLInputElement) {

            removeProofInput.checked = false;

        }

        if (proofFileInput instanceof HTMLInputElement) {

            proofFileInput.value = '';

        }

    }



    /**

     * @brief Sync end date disabled state when current checkbox changes.

     *

     * @return {void}

     * @date 2026-06-11

     * @author Stephane H.

     */

    function syncEndDateState() {

        if (!(isCurrentInput instanceof HTMLInputElement) || !(endDateInput instanceof HTMLInputElement)) {

            return;

        }



        endDateInput.disabled = isCurrentInput.checked;

        if (isCurrentInput.checked) {

            endDateInput.value = '';

        }

    }



    /**

     * @brief Clear modal form fields.

     *

     * @return {void}

     * @date 2026-06-11

     * @author Stephane H.

     */

    function resetEntryForm() {

        if (startDateInput instanceof HTMLInputElement) {

            startDateInput.value = '';

        }

        if (endDateInput instanceof HTMLInputElement) {

            endDateInput.value = '';

            endDateInput.disabled = false;

        }

        if (isCurrentInput instanceof HTMLInputElement) {

            isCurrentInput.checked = false;

        }

        if (websiteInput instanceof HTMLInputElement) {

            websiteInput.value = '';

        }

        if (isPrimaryInput instanceof HTMLInputElement) {

            isPrimaryInput.checked = true;

        }

        if (proofUrlInput instanceof HTMLInputElement) {

            proofUrlInput.value = '';

        }



        activeLocales.forEach(function (locale) {

            const titleInput = root.querySelector('[data-cv-certification-entry-title-locale="' + locale + '"]');

            const providerInput = root.querySelector('[data-cv-certification-entry-provider-locale="' + locale + '"]');

            const locationInput = root.querySelector('[data-cv-certification-entry-location-locale="' + locale + '"]');

            if (titleInput instanceof HTMLInputElement) {

                titleInput.value = '';

            }

            if (providerInput instanceof HTMLInputElement) {

                providerInput.value = '';

            }

            if (locationInput instanceof HTMLInputElement) {

                locationInput.value = '';

            }

            clearHighlightsForLocale(locale);

        });



        syncProofControls('');

    }



    /**

     * @brief Open certification entry modal for add or edit.

     *

     * @param {number|null} index Entry index to edit, or null for add.

     * @return {void}

     * @date 2026-06-11

     * @author Stephane H.

     */

    function openEntryModal(index) {

        editingIndex = index;



        const titleEl = document.getElementById('cvCertificationEntryModalLabel');

        if (titleEl) {

            titleEl.textContent = index === null

                ? (i18n.entryTitleAdd || '')

                : (i18n.entryTitleEdit || '');

        }



        resetEntryForm();

        if (index !== null) {

            const entry = entries[index];

            if (entry) {

                if (startDateInput instanceof HTMLInputElement) {

                    startDateInput.value = entry.startDate || '';

                }

                if (endDateInput instanceof HTMLInputElement) {

                    endDateInput.value = entry.endDate || '';

                }

                if (isCurrentInput instanceof HTMLInputElement) {

                    isCurrentInput.checked = Boolean(entry.isCurrent);

                }

                if (websiteInput instanceof HTMLInputElement) {

                    websiteInput.value = entry.providerWebsiteUrl || '';

                }

                if (isPrimaryInput instanceof HTMLInputElement) {

                    isPrimaryInput.checked = entry.isPrimary !== false;

                }

                if (proofUrlInput instanceof HTMLInputElement) {

                    proofUrlInput.value = entry.proofUrl || '';

                }



                activeLocales.forEach(function (locale) {

                    const titleInput = root.querySelector('[data-cv-certification-entry-title-locale="' + locale + '"]');

                    const providerInput = root.querySelector('[data-cv-certification-entry-provider-locale="' + locale + '"]');

                    const locationInput = root.querySelector('[data-cv-certification-entry-location-locale="' + locale + '"]');

                    if (titleInput instanceof HTMLInputElement) {

                        titleInput.value = (entry.titleByLocale || {})[locale] || '';

                    }

                    if (providerInput instanceof HTMLInputElement) {

                        providerInput.value = (entry.providerNameByLocale || {})[locale] || '';

                    }

                    if (locationInput instanceof HTMLInputElement) {

                        locationInput.value = (entry.locationByLocale || {})[locale] || '';

                    }

                    ((entry.highlightsByLocale || {})[locale] || []).forEach(function (highlight) {

                        addHighlightRow(locale, highlight);

                    });

                });



                syncProofControls(entry.proofPdfPath || '');

            }

        }



        syncEndDateState();

        entryModal?.show();

    }



    /**

     * @brief Read highlight values from modal rows for one locale.

     *

     * @param {string} locale Locale code.

     * @return {string[]}

     * @date 2026-06-11

     * @author Stephane H.

     */

    function readHighlightsFromModal(locale) {

        const values = [];

        root.querySelectorAll('[data-cv-certification-entry-highlight-input][data-cv-certification-entry-highlight-locale="' + locale + '"]').forEach(function (input) {

            if (input instanceof HTMLInputElement) {

                const value = input.value.trim();

                if (value !== '') {

                    values.push(value);

                }

            }

        });



        return values;

    }



    /**

     * @brief Persist modal form into entries array.

     *

     * @return {void}

     * @date 2026-06-11

     * @author Stephane H.

     */

    function saveEntryFromModal() {

        const startDate = startDateInput instanceof HTMLInputElement ? startDateInput.value.trim() : '';

        const titleByLocale = {};

        const providerNameByLocale = {};

        const locationByLocale = {};

        const highlightsByLocale = {};



        activeLocales.forEach(function (locale) {

            const titleInput = root.querySelector('[data-cv-certification-entry-title-locale="' + locale + '"]');

            const providerInput = root.querySelector('[data-cv-certification-entry-provider-locale="' + locale + '"]');

            const locationInput = root.querySelector('[data-cv-certification-entry-location-locale="' + locale + '"]');

            titleByLocale[locale] = titleInput instanceof HTMLInputElement ? titleInput.value.trim() : '';

            providerNameByLocale[locale] = providerInput instanceof HTMLInputElement ? providerInput.value.trim() : '';

            locationByLocale[locale] = locationInput instanceof HTMLInputElement ? locationInput.value.trim() : '';

            const highlights = readHighlightsFromModal(locale);

            if (highlights.length > 0) {

                highlightsByLocale[locale] = highlights;

            }

        });



        if (startDate === '') {

            window.alert(i18n.startDateRequired || i18n.flashError || '');

            return;

        }

        if ((titleByLocale[defaultLocale] || '') === '') {

            window.alert(i18n.titleRequired || i18n.flashError || '');

            return;

        }

        if ((providerNameByLocale[defaultLocale] || '') === '') {

            window.alert(i18n.providerRequired || i18n.flashError || '');

            return;

        }



        const previous = editingIndex !== null ? entries[editingIndex] : null;

        const row = {

            id: previous ? previous.id : generateUuid(),

            sortOrder: editingIndex !== null ? editingIndex : entries.length,

            startDate: startDate,

            endDate: endDateInput instanceof HTMLInputElement ? endDateInput.value.trim() : '',

            isCurrent: isCurrentInput instanceof HTMLInputElement ? isCurrentInput.checked : false,

            titleByLocale: titleByLocale,

            providerNameByLocale: providerNameByLocale,

            locationByLocale: locationByLocale,

            providerWebsiteUrl: websiteInput instanceof HTMLInputElement ? websiteInput.value.trim() : '',

            proofPdfPath: previous ? (previous.proofPdfPath || '') : '',

            proofUrl: proofUrlInput instanceof HTMLInputElement ? proofUrlInput.value.trim() : '',

            highlightsByLocale: highlightsByLocale,

            isPrimary: isPrimaryInput instanceof HTMLInputElement ? isPrimaryInput.checked : true,

            pendingProofFile: proofFileInput instanceof HTMLInputElement && proofFileInput.files && proofFileInput.files[0]

                ? proofFileInput.files[0]

                : null,

            removeProofPdf: removeProofInput instanceof HTMLInputElement ? removeProofInput.checked : false

        };



        if (editingIndex !== null) {

            entries[editingIndex] = row;

        } else {

            entries.push(row);

        }



        renderList();

        syncHiddenFields();

        entryModal?.hide();

        editingIndex = null;

    }



    /**

     * @brief Sync certification tone adjustment label from range input (-100..100).

     *

     * @param {HTMLInputElement} range Range control.

     * @return {void}

     * @date 2026-06-11

     * @author Stephane H.

     */

    function syncCertificationToneLabel(range) {

        const valueEl = root.querySelector('[data-cv-certification-tone-value]');

        if (!valueEl) {

            return;

        }



        const parsed = parseInt(String(range.value), 10);

        const percent = Number.isNaN(parsed) ? 0 : Math.max(-100, Math.min(100, parsed));

        if (percent === 0) {

            const neutralLabel = valueEl.getAttribute('data-cv-certification-tone-neutral-label') || '';

            valueEl.textContent = neutralLabel;



            return;

        }



        valueEl.textContent = (percent > 0 ? '+' : '') + percent + '%';

    }



    /**

     * @brief Build multipart FormData and submit certification form via fetch.

     *

     * @return {Promise<void>}

     * @date 2026-06-11

     * @author Stephane H.

     */

    async function submitMainForm() {

        if (!(mainForm instanceof HTMLFormElement)) {

            return;

        }



        syncHiddenFields();

        const formData = new FormData();

        mainForm.querySelectorAll('input[name], select[name], textarea[name]').forEach(function (field) {

            if (!(field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement)) {

                return;

            }



            if (field instanceof HTMLInputElement && field.type === 'file') {

                return;

            }



            if (field.closest('[data-cv-certification-entries-storage]')) {

                return;

            }



            formData.append(field.name, field.value);

        });



        entries.forEach(function (entry, index) {

            const prefix = 'certification_entries[' + index + ']';

            formData.set(prefix + '[id]', entry.id || '');

            formData.set(prefix + '[sortOrder]', String(index));

            formData.set(prefix + '[startDate]', entry.startDate || '');

            formData.set(prefix + '[endDate]', entry.endDate || '');

            formData.set(prefix + '[isCurrent]', entry.isCurrent ? '1' : '0');

            formData.set(prefix + '[providerWebsiteUrl]', entry.providerWebsiteUrl || '');

            formData.set(prefix + '[proofPdfPath]', entry.proofPdfPath || '');

            formData.set(prefix + '[proofUrl]', entry.proofUrl || '');

            formData.set(prefix + '[isPrimary]', entry.isPrimary ? '1' : '0');

            activeLocales.forEach(function (locale) {

                formData.set(prefix + '[titleByLocale][' + locale + ']', (entry.titleByLocale || {})[locale] || '');

                formData.set(prefix + '[providerNameByLocale][' + locale + ']', (entry.providerNameByLocale || {})[locale] || '');

                formData.set(prefix + '[locationByLocale][' + locale + ']', (entry.locationByLocale || {})[locale] || '');

                ((entry.highlightsByLocale || {})[locale] || []).forEach(function (highlight) {

                    formData.append(prefix + '[highlightsByLocale][' + locale + '][]', highlight || '');

                });

            });

            if (entry.removeProofPdf) {

                formData.set('certification_remove_proof_pdf[' + (entry.id || '') + ']', '1');

            }

            if (entry.pendingProofFile instanceof File) {

                formData.set('certification_proof_pdf[' + (entry.id || '') + ']', entry.pendingProofFile);

            }

        });



        const submitBtn = mainForm.querySelector('[type="submit"]');

        if (submitBtn instanceof HTMLButtonElement) {

            submitBtn.disabled = true;

        }



        try {

            const response = await fetch(mainForm.action, {

                method: 'POST',

                body: formData,

                credentials: 'same-origin',

                redirect: 'follow'

            });

            window.location.href = response.url;

        } catch (error) {

            if (submitBtn instanceof HTMLButtonElement) {

                submitBtn.disabled = false;

            }

            window.alert(i18n.flashError || '');

        }

    }



    root.querySelectorAll('[data-cv-certification-tone-range]').forEach(function (range) {

        if (!(range instanceof HTMLInputElement)) {

            return;

        }



        syncCertificationToneLabel(range);

        range.addEventListener('input', function () {

            syncCertificationToneLabel(range);

        });

    });



    isCurrentInput?.addEventListener('change', syncEndDateState);



    entryFormEl?.addEventListener('click', function (event) {

        const target = event.target;

        if (!(target instanceof HTMLElement)) {

            return;

        }



        const addBtn = target.closest('[data-cv-certification-entry-add-highlight]');

        if (addBtn instanceof HTMLElement) {

            const locale = addBtn.getAttribute('data-cv-certification-entry-add-highlight-locale') || '';

            if (locale !== '') {

                addHighlightRow(locale, '');

            }

        }



        const row = target.closest('[data-cv-certification-entry-highlight-row]');

        if (row instanceof HTMLElement && target.closest('[data-cv-certification-entry-remove-highlight]')) {

            row.remove();

        }

    });



    root.querySelector('[data-cv-certification-add]')?.addEventListener('click', function () {

        openEntryModal(null);

    });



    entrySaveBtn?.addEventListener('click', saveEntryFromModal);



    root.addEventListener('click', function (event) {

        const target = event.target;

        if (!(target instanceof HTMLElement)) {

            return;

        }



        const item = target.closest('[data-cv-certification-entry]');

        if (!(item instanceof HTMLElement)) {

            return;

        }



        const index = parseInt(item.getAttribute('data-entry-index') || '-1', 10);

        if (Number.isNaN(index) || index < 0) {

            return;

        }



        if (target.closest('[data-cv-certification-remove]')) {

            if (!window.confirm(i18n.confirmDelete || '')) {

                return;

            }

            entries.splice(index, 1);

            renderList();

            syncHiddenFields();



            return;

        }



        if (target.closest('[data-cv-certification-edit]')) {

            openEntryModal(index);



            return;

        }



        const move = target.closest('[data-cv-certification-move]')?.getAttribute('data-cv-certification-move');

        if (move === 'up' && index > 0) {

            const tmp = entries[index - 1];

            entries[index - 1] = entries[index];

            entries[index] = tmp;

            renderList();

            syncHiddenFields();

        } else if (move === 'down' && index < entries.length - 1) {

            const tmp = entries[index + 1];

            entries[index + 1] = entries[index];

            entries[index] = tmp;

            renderList();

            syncHiddenFields();

        }

    });



    mainForm?.addEventListener('submit', function (event) {

        event.preventDefault();

        submitMainForm();

    });



    renderList();

    syncHiddenFields();

})();


