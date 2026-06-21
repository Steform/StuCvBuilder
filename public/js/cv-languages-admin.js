(function () {
    'use strict';

    const root = document.querySelector('[data-cv-languages-admin]');
    if (!root) {
        return;
    }

    const activeLocales = JSON.parse(root.getAttribute('data-active-locales') || '[]');
    const defaultLocale = root.getAttribute('data-default-locale') || 'fr';
    const i18n = JSON.parse(root.getAttribute('data-i18n') || '{}');
    const mainForm = root.querySelector('.cv-languages-customization__form');
    const storageEl = root.querySelector('[data-cv-languages-entries-storage]');
    const listEl = root.querySelector('[data-cv-languages-list]');
    const listEmptyEl = root.querySelector('[data-cv-languages-list-empty]');
    const entryModalEl = document.getElementById('cvLanguagesEntryModal');
    const entryFormEl = root.querySelector('[data-cv-languages-entry-form]');
    const levelSelect = root.querySelector('[data-cv-languages-entry-level]');
    const notesInput = root.querySelector('[data-cv-languages-entry-notes]');
    const entrySaveBtn = root.querySelector('[data-cv-languages-entry-save]');

    /** @type {Array<{id: string, labelByLocale: Record<string, string>, levelCode: string, notes: string, sortOrder: number}>} */
    let entries = [];
    /** @type {number|null} */
    let editingIndex = null;

    try {
        const dataEl = document.getElementById('cv-languages-entries-data');
        entries = JSON.parse(dataEl?.textContent || '[]');
        if (!Array.isArray(entries)) {
            entries = [];
        }
        entries = entries.map(normalizeLoadedEntry);
    } catch (error) {
        entries = [];
    }

    /**
     * @brief Normalize a language entry loaded from server JSON.
     *
     * @param {object} entry Raw entry object.
     * @return {object} Normalized entry.
     * @date 2026-06-10
     * @author Stephane H.
     */
    function normalizeLoadedEntry(entry) {
        return {
            id: entry.id || '',
            labelByLocale: entry.labelByLocale || {},
            levelCode: entry.levelCode || 'b1',
            notes: entry.notes || '',
            sortOrder: entry.sortOrder || 0
        };
    }

    /**
     * @brief Resolve Bootstrap modal instance for an element.
     *
     * @param {HTMLElement|null} element Modal root element.
     * @return {import('bootstrap').Modal|null}
     * @date 2026-06-10
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
     * @date 2026-06-10
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
     * @brief Resolve level label from the level select options.
     *
     * @param {string} levelCode CEFR level code.
     * @return {string}
     * @date 2026-06-10
     * @author Stephane H.
     */
    function resolveLevelLabel(levelCode) {
        if (!(levelSelect instanceof HTMLSelectElement)) {
            return levelCode;
        }

        for (const option of levelSelect.options) {
            if (option.value === levelCode) {
                return option.textContent || levelCode;
            }
        }

        return levelCode;
    }

    /**
     * @brief Resolve display label for a language entry.
     *
     * @param {object} entry Language entry.
     * @param {number} index Zero-based index.
     * @return {string}
     * @date 2026-06-10
     * @author Stephane H.
     */
    function resolveEntryLabel(entry, index) {
        const labels = entry.labelByLocale || {};
        const label = String(labels[defaultLocale] || '').trim();
        if (label !== '') {
            return label;
        }

        for (const locale of activeLocales) {
            const fallback = String(labels[locale] || '').trim();
            if (fallback !== '') {
                return fallback;
            }
        }

        return (i18n.entryFallback || 'Language __INDEX__').replace('__INDEX__', String(index + 1));
    }

    /**
     * @brief Re-render the visible language list from in-memory state.
     *
     * @return {void}
     * @date 2026-06-10
     * @author Stephane H.
     */
    function renderList() {
        if (!listEl) {
            return;
        }

        listEl.innerHTML = '';
        entries.forEach(function (entry, index) {
            const levelLabel = resolveLevelLabel(entry.levelCode || '');
            const item = document.createElement('li');
            item.className = 'list-group-item cv-languages-admin-list__item';
            item.setAttribute('data-cv-languages-entry', '');
            item.setAttribute('data-entry-index', String(index));
            item.innerHTML = ''
                + '<div class="cv-languages-admin-list__row">'
                + '<span class="cv-languages-admin-list__label">' + escapeHtml(resolveEntryLabel(entry, index)) + '</span>'
                + '<span class="badge text-bg-secondary cv-languages-admin-list__level">' + escapeHtml(levelLabel) + '</span>'
                + '<span class="cv-languages-admin-list__connector" aria-hidden="true"></span>'
                + '<div class="btn-group btn-group-sm cv-languages-admin-list__actions">'
                + '<button type="button" class="btn btn-outline-secondary" data-cv-languages-move="up" aria-label="' + escapeAttr(i18n.moveUpAria || '') + '">&uarr;</button>'
                + '<button type="button" class="btn btn-outline-secondary" data-cv-languages-move="down" aria-label="' + escapeAttr(i18n.moveDownAria || '') + '">&darr;</button>'
                + '<button type="button" class="btn btn-outline-primary" data-cv-languages-edit>' + escapeHtml(i18n.entryTitleEdit || '') + '</button>'
                + '<button type="button" class="btn btn-outline-danger" data-cv-languages-remove>' + escapeHtml(i18n.removeEntry || '') + '</button>'
                + '</div>'
                + '</div>';
            listEl.appendChild(item);
        });

        if (listEmptyEl) {
            listEmptyEl.classList.toggle('d-none', entries.length > 0);
        }
    }

    /**
     * @brief Escape HTML for safe list rendering.
     *
     * @param {string} value Raw text.
     * @return {string}
     * @date 2026-06-10
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
     * @brief Escape attribute values for safe list rendering.
     *
     * @param {string} value Raw text.
     * @return {string}
     * @date 2026-06-10
     * @author Stephane H.
     */
    function escapeAttr(value) {
        return escapeHtml(value);
    }

    /**
     * @brief Sync hidden POST fields for language_entries[].
     *
     * @return {void}
     * @date 2026-06-10
     * @author Stephane H.
     */
    function syncHiddenFields() {
        if (!storageEl) {
            return;
        }

        storageEl.innerHTML = '';
        entries.forEach(function (entry, index) {
            const prefix = 'language_entries[' + index + ']';
            const fields = [
                { name: prefix + '[id]', value: entry.id || '' },
                { name: prefix + '[sortOrder]', value: String(index) },
                { name: prefix + '[levelCode]', value: entry.levelCode || 'b1' },
                { name: prefix + '[notes]', value: entry.notes || '' }
            ];
            activeLocales.forEach(function (locale) {
                fields.push({
                    name: prefix + '[labelByLocale][' + locale + ']',
                    value: (entry.labelByLocale || {})[locale] || ''
                });
            });
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
     * @brief Clear modal form fields.
     *
     * @return {void}
     * @date 2026-06-10
     * @author Stephane H.
     */
    function resetEntryForm() {
        root.querySelectorAll('[data-cv-languages-entry-label]').forEach(function (input) {
            if (input instanceof HTMLInputElement) {
                input.value = '';
            }
        });
        if (levelSelect instanceof HTMLSelectElement) {
            levelSelect.value = 'b1';
        }
        if (notesInput instanceof HTMLInputElement) {
            notesInput.value = '';
        }
    }

    /**
     * @brief Open the language entry modal for add or edit.
     *
     * @param {number|null} index Entry index to edit, or null for add.
     * @return {void}
     * @date 2026-06-10
     * @author Stephane H.
     */
    function openEntryModal(index) {
        editingIndex = index;
        const titleEl = document.getElementById('cvLanguagesEntryModalLabel');
        if (titleEl) {
            titleEl.textContent = index === null
                ? (i18n.entryTitleAdd || '')
                : (i18n.entryTitleEdit || '');
        }

        resetEntryForm();
        if (index !== null && entries[index]) {
            const entry = entries[index];
            activeLocales.forEach(function (locale) {
                const input = root.querySelector('[data-cv-languages-entry-label-locale="' + locale + '"]');
                if (input instanceof HTMLInputElement) {
                    input.value = (entry.labelByLocale || {})[locale] || '';
                }
            });
            if (levelSelect instanceof HTMLSelectElement) {
                levelSelect.value = entry.levelCode || 'b1';
            }
            if (notesInput instanceof HTMLInputElement) {
                notesInput.value = entry.notes || '';
            }
        }

        entryModal?.show();
    }

    /**
     * @brief Persist modal form into entries array.
     *
     * @return {void}
     * @date 2026-06-10
     * @author Stephane H.
     */
    function saveEntryFromModal() {
        const labelByLocale = {};
        activeLocales.forEach(function (locale) {
            const input = root.querySelector('[data-cv-languages-entry-label-locale="' + locale + '"]');
            labelByLocale[locale] = input instanceof HTMLInputElement ? input.value.trim() : '';
        });

        if ((labelByLocale[defaultLocale] || '') === '') {
            window.alert(i18n.labelRequired || i18n.flashError || '');
            return;
        }

        const previous = editingIndex !== null ? entries[editingIndex] : null;
        const row = {
            id: previous ? previous.id : generateUuid(),
            labelByLocale: labelByLocale,
            levelCode: levelSelect instanceof HTMLSelectElement ? levelSelect.value : 'b1',
            notes: notesInput instanceof HTMLInputElement ? notesInput.value.trim() : '',
            sortOrder: editingIndex !== null ? editingIndex : entries.length
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

    root.querySelectorAll('[data-cv-languages-tone-range]').forEach(function (range) {
        range.addEventListener('input', function () {
            const valueEl = root.querySelector('[data-cv-languages-tone-value]');
            if (!(valueEl instanceof HTMLElement)) {
                return;
            }

            const neutralLabel = valueEl.getAttribute('data-cv-languages-tone-neutral-label') || '0%';
            const numeric = Number(range.value);
            if (numeric === 0) {
                valueEl.textContent = neutralLabel;

                return;
            }

            valueEl.textContent = (numeric > 0 ? '+' : '') + numeric + '%';
        });
    });

    root.querySelector('[data-cv-languages-add]')?.addEventListener('click', function () {
        openEntryModal(null);
    });

    entrySaveBtn?.addEventListener('click', saveEntryFromModal);

    root.addEventListener('click', function (event) {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
            return;
        }

        const item = target.closest('[data-cv-languages-entry]');
        if (!(item instanceof HTMLElement)) {
            return;
        }

        const index = parseInt(item.getAttribute('data-entry-index') || '-1', 10);
        if (Number.isNaN(index) || index < 0) {
            return;
        }

        if (target.closest('[data-cv-languages-remove]')) {
            if (!window.confirm(i18n.confirmDelete || '')) {
                return;
            }
            entries.splice(index, 1);
            renderList();
            syncHiddenFields();

            return;
        }

        if (target.closest('[data-cv-languages-edit]')) {
            openEntryModal(index);

            return;
        }

        const move = target.closest('[data-cv-languages-move]')?.getAttribute('data-cv-languages-move');
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

    mainForm?.addEventListener('submit', function () {
        syncHiddenFields();
    });

    renderList();
    syncHiddenFields();
})();
