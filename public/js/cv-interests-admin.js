(function () {
    'use strict';

    const root = document.querySelector('[data-cv-interests-admin]');
    if (!root) {
        return;
    }

    const activeLocales = JSON.parse(root.getAttribute('data-active-locales') || '[]');
    const defaultLocale = root.getAttribute('data-default-locale') || 'fr';
    const manifestUrl = root.getAttribute('data-bootstrap-icons-manifest-url') || '';
    const i18n = JSON.parse(root.getAttribute('data-i18n') || '{}');
    const mainForm = root.querySelector('.cv-interests-customization__form');
    const storageEl = root.querySelector('[data-cv-interests-entries-storage]');
    const listEl = root.querySelector('[data-cv-interests-list]');
    const listEmptyEl = root.querySelector('[data-cv-interests-list-empty]');
    const entryModalEl = document.getElementById('cvInterestsEntryModal');
    const entryFormEl = root.querySelector('[data-cv-interests-entry-form]');
    const entryIconInput = root.querySelector('[data-cv-interests-entry-icon-input]');
    const entryIconPreview = root.querySelector('[data-cv-interests-entry-icon-preview]');
    const entryIconFileInput = root.querySelector('[data-cv-interests-entry-icon-file]');
    const entryIconImagePreview = root.querySelector('[data-cv-interests-entry-icon-image-preview]');
    const bootstrapIconWrap = root.querySelector('[data-cv-interests-bootstrap-icon-wrap]');
    const uploadIconWrap = root.querySelector('[data-cv-interests-upload-icon-wrap]');
    const entrySaveBtn = root.querySelector('[data-cv-interests-entry-save]');
    const iconBrowserModalEl = document.getElementById('cvInterestsBootstrapIconBrowserModal');

    /** @type {string|null} */
    let modalObjectUrl = null;

    /** @type {Array<{id: string, iconType: string, icon: string, iconPath: string, labelByLocale: Record<string, string>, sortOrder: number, pendingIconFile: File|null}>} */
    let entries = [];
    /** @type {number|null} */
    let editingIndex = null;

    try {
        const dataEl = document.getElementById('cv-interests-entries-data');
        entries = JSON.parse(dataEl?.textContent || '[]');
        if (!Array.isArray(entries)) {
            entries = [];
        }
        entries = entries.map(normalizeLoadedEntry);
    } catch (error) {
        entries = [];
    }

    /**
     * @param {object} entry
     * @returns {object}
     */
    function normalizeLoadedEntry(entry) {
        const iconType = entry.iconType === 'image' || (entry.iconPath && String(entry.iconPath).trim() !== '')
            ? 'image'
            : 'bootstrap';

        return {
            id: entry.id || '',
            iconType: iconType,
            icon: iconType === 'bootstrap' ? (entry.icon || '') : '',
            iconPath: iconType === 'image' ? (entry.iconPath || '') : '',
            labelByLocale: entry.labelByLocale || {},
            sortOrder: entry.sortOrder || 0,
            pendingIconFile: null
        };
    }

    /**
     * @param {HTMLElement|null} element
     * @returns {import('bootstrap').Modal|null}
     */
    function getModal(element) {
        if (!element || typeof bootstrap === 'undefined') {
            return null;
        }

        return bootstrap.Modal.getOrCreateInstance(element);
    }

    const entryModal = getModal(entryModalEl);

    const iconBrowser = globalThis.CvBootstrapIconBrowser?.createBootstrapIconBrowser({
        modalEl: iconBrowserModalEl,
        manifestUrl: manifestUrl,
        radioName: 'cvInterestsIconBrowserPick',
        idPrefix: 'cvInterestsBootstrapIconBrowser',
        defaultIcon: 'bi-heart',
        i18n: i18n,
        onError: function () {
            window.alert(i18n.flashError || '');
        },
        getTargetInput: function () {
            return entryIconInput;
        },
        getPreviewEl: function () {
            return entryIconPreview;
        }
    });

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
     * @brief Revoke temporary object URL used for image preview.
     */
    function revokeModalObjectUrl() {
        if (modalObjectUrl) {
            URL.revokeObjectURL(modalObjectUrl);
            modalObjectUrl = null;
        }
    }

    /**
     * @brief Read selected icon type from modal radios.
     *
     * @return {string}
     */
    function getSelectedIconType() {
        const selected = entryFormEl?.querySelector('input[name="cvInterestsEntryIconType"]:checked');

        return selected instanceof HTMLInputElement ? selected.value : 'bootstrap';
    }

    /**
     * @brief Toggle bootstrap vs custom image fields in the entry modal.
     */
    function syncIconTypeFields() {
        const iconType = getSelectedIconType();
        bootstrapIconWrap?.classList.toggle('d-none', iconType !== 'bootstrap');
        uploadIconWrap?.classList.toggle('d-none', iconType !== 'image');
    }

    /**
     * @brief Render custom image preview inside the entry modal.
     *
     * @param {string} src
     */
    function renderModalImagePreview(src) {
        if (!(entryIconImagePreview instanceof HTMLElement)) {
            return;
        }

        entryIconImagePreview.innerHTML = '';
        if (!src) {
            entryIconImagePreview.hidden = true;

            return;
        }

        const img = document.createElement('img');
        img.src = src;
        img.alt = '';
        img.width = 32;
        img.height = 32;
        img.className = 'cv-interests-admin-list__icon-img';
        entryIconImagePreview.appendChild(img);
        entryIconImagePreview.hidden = false;
    }

    /**
     * @param {object} entry
     * @param {number} index
     * @returns {string}
     */
    function resolveEntryLabel(entry, index) {
        const labels = entry.labelByLocale || {};
        const label = String(labels[defaultLocale] || '').trim();
        if (label !== '') {
            return label;
        }

        return (i18n.entryFallback || 'Interest __INDEX__').replace('__INDEX__', String(index + 1));
    }

    /**
     * @param {object} entry
     * @returns {string}
     */
    function renderEntryIcon(entry) {
        if (entry.iconType === 'image') {
            const path = String(entry.iconPath || '').trim();
            if (path !== '') {
                return '<img src="/' + path.replace(/^\//, '') + '" alt="" width="18" height="18" class="cv-interests-admin-list__icon-img" loading="lazy" decoding="async">';
            }

            if (entry.pendingIconFile instanceof File) {
                return '<span class="cv-interests-admin-list__icon-pending text-muted small">…</span>';
            }
        }

        const iconClass = String(entry.icon || '').trim();
        if (iconClass === '') {
            return '<span class="cv-interests-admin-list__icon-placeholder text-muted">—</span>';
        }

        return '<i class="bi ' + iconClass + ' cv-interests-admin-list__icon" aria-hidden="true"></i>';
    }

    /**
     * @brief Re-render the visible entry list from in-memory state.
     */
    function renderList() {
        if (!listEl) {
            return;
        }

        listEl.innerHTML = '';
        entries.forEach(function (entry, index) {
            const item = document.createElement('li');
            item.className = 'list-group-item cv-interests-admin-list__item';
            item.setAttribute('data-cv-interests-entry', '');
            item.setAttribute('data-entry-index', String(index));
            item.innerHTML = ''
                + '<div class="cv-interests-admin-list__row">'
                + '<span class="cv-interests-admin-list__icon-wrap">' + renderEntryIcon(entry) + '</span>'
                + '<span class="cv-interests-admin-list__label">' + resolveEntryLabel(entry, index) + '</span>'
                + '<span class="cv-interests-admin-list__connector" aria-hidden="true"></span>'
                + '<div class="btn-group btn-group-sm cv-interests-admin-list__actions">'
                + '<button type="button" class="btn btn-outline-secondary" data-cv-interests-move="up" aria-label="' + (i18n.moveUpAria || '') + '">&uarr;</button>'
                + '<button type="button" class="btn btn-outline-secondary" data-cv-interests-move="down" aria-label="' + (i18n.moveDownAria || '') + '">&darr;</button>'
                + '<button type="button" class="btn btn-outline-primary" data-cv-interests-edit>' + (i18n.entryTitleEdit || '') + '</button>'
                + '<button type="button" class="btn btn-outline-danger" data-cv-interests-remove>' + (i18n.removeEntry || '') + '</button>'
                + '</div>'
                + '</div>';
            listEl.appendChild(item);
        });

        if (listEmptyEl) {
            listEmptyEl.classList.toggle('d-none', entries.length > 0);
        }
    }

    /**
     * @brief Sync hidden POST fields for interest_entries[].
     */
    function syncHiddenFields() {
        if (!storageEl) {
            return;
        }

        storageEl.innerHTML = '';
        entries.forEach(function (entry, index) {
            const prefix = 'interest_entries[' + index + ']';
            const fields = [
                { name: prefix + '[id]', value: entry.id || '' },
                { name: prefix + '[sortOrder]', value: String(index) },
                { name: prefix + '[iconType]', value: entry.iconType || 'bootstrap' },
                { name: prefix + '[icon]', value: entry.iconType === 'bootstrap' ? (entry.icon || '') : '' },
                { name: prefix + '[iconPath]', value: entry.iconType === 'image' ? (entry.iconPath || '') : '' }
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
     */
    function resetEntryForm() {
        revokeModalObjectUrl();
        if (entryIconInput instanceof HTMLInputElement) {
            entryIconInput.value = '';
        }
        if (entryIconFileInput instanceof HTMLInputElement) {
            entryIconFileInput.value = '';
        }
        root.querySelectorAll('[data-cv-interests-entry-label]').forEach(function (input) {
            if (input instanceof HTMLInputElement) {
                input.value = '';
            }
        });
        const bootstrapRadio = entryFormEl?.querySelector('#cvInterestsEntryIconBootstrap');
        if (bootstrapRadio instanceof HTMLInputElement) {
            bootstrapRadio.checked = true;
        }
        syncIconTypeFields();
        renderModalImagePreview('');
        iconBrowser?.syncPreview();
    }

    /**
     * @param {number|null} index
     */
    function openEntryModal(index) {
        editingIndex = index;
        const titleEl = document.getElementById('cvInterestsEntryModalLabel');
        if (titleEl) {
            titleEl.textContent = index === null
                ? (i18n.entryTitleAdd || '')
                : (i18n.entryTitleEdit || '');
        }

        resetEntryForm();
        if (index !== null && entries[index]) {
            const entry = entries[index];
            const iconType = entry.iconType === 'image' ? 'image' : 'bootstrap';
            entryFormEl?.querySelectorAll('input[name="cvInterestsEntryIconType"]').forEach(function (radio) {
                if (radio instanceof HTMLInputElement) {
                    radio.checked = radio.value === iconType;
                }
            });
            syncIconTypeFields();

            if (iconType === 'bootstrap' && entryIconInput instanceof HTMLInputElement) {
                entryIconInput.value = entry.icon || '';
            }

            if (iconType === 'image' && entry.iconPath) {
                renderModalImagePreview('/' + String(entry.iconPath).replace(/^\//, ''));
            }

            activeLocales.forEach(function (locale) {
                const input = root.querySelector('[data-cv-interests-entry-label-locale="' + locale + '"]');
                if (input instanceof HTMLInputElement) {
                    input.value = (entry.labelByLocale || {})[locale] || '';
                }
            });
        }

        iconBrowser?.syncPreview();
        entryModal?.show();
    }

    /**
     * @brief Persist modal form into entries array.
     */
    function saveEntryFromModal() {
        const labelByLocale = {};
        activeLocales.forEach(function (locale) {
            const input = root.querySelector('[data-cv-interests-entry-label-locale="' + locale + '"]');
            labelByLocale[locale] = input instanceof HTMLInputElement ? input.value.trim() : '';
        });

        if ((labelByLocale[defaultLocale] || '') === '') {
            window.alert(i18n.labelRequired || i18n.flashError || '');
            return;
        }

        const iconType = getSelectedIconType();
        let icon = '';
        let iconPath = '';
        let pendingIconFile = null;

        if (iconType === 'image') {
            if (entryIconFileInput instanceof HTMLInputElement && entryIconFileInput.files && entryIconFileInput.files[0]) {
                pendingIconFile = entryIconFileInput.files[0];
            }

            const existing = editingIndex !== null ? entries[editingIndex] : null;
            iconPath = existing && existing.iconType === 'image' ? (existing.iconPath || '') : '';

            if (!(pendingIconFile instanceof File) && iconPath === '') {
                window.alert(i18n.iconFileRequired || i18n.flashInvalidIcon || i18n.flashError || '');
                return;
            }
        } else {
            icon = entryIconInput instanceof HTMLInputElement ? entryIconInput.value.trim() : '';
            if (icon !== '') {
                if (!iconBrowser?.isValid(icon)) {
                    window.alert(i18n.flashInvalidBootstrapIcon || i18n.flashError || '');
                    return;
                }
                icon = iconBrowser.normalize(icon);
            }
        }

        const previous = editingIndex !== null ? entries[editingIndex] : null;
        const row = {
            id: previous ? previous.id : generateUuid(),
            iconType: iconType,
            icon: icon,
            iconPath: iconType === 'image' ? iconPath : '',
            labelByLocale: labelByLocale,
            sortOrder: editingIndex !== null ? editingIndex : entries.length,
            pendingIconFile: pendingIconFile
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
        revokeModalObjectUrl();
    }

    /**
     * @brief Build multipart FormData and submit interests form via fetch.
     *
     * @return {Promise<void>}
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

            if (field instanceof HTMLInputElement && (field.type === 'file' || field.closest('[data-cv-interests-entries-storage]'))) {
                return;
            }

            formData.append(field.name, field.value);
        });

        entries.forEach(function (entry, index) {
            const prefix = 'interest_entries[' + index + ']';
            formData.set(prefix + '[id]', entry.id || '');
            formData.set(prefix + '[sortOrder]', String(index));
            formData.set(prefix + '[iconType]', entry.iconType || 'bootstrap');
            formData.set(prefix + '[icon]', entry.iconType === 'bootstrap' ? (entry.icon || '') : '');
            formData.set(prefix + '[iconPath]', entry.iconType === 'image' ? (entry.iconPath || '') : '');
            activeLocales.forEach(function (locale) {
                formData.set(prefix + '[labelByLocale][' + locale + ']', (entry.labelByLocale || {})[locale] || '');
            });
            if (entry.pendingIconFile instanceof File) {
                formData.set(prefix + '[iconFile]', entry.pendingIconFile);
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

    entryFormEl?.querySelectorAll('input[name="cvInterestsEntryIconType"]').forEach(function (radio) {
        radio.addEventListener('change', syncIconTypeFields);
    });

    entryIconFileInput?.addEventListener('change', function () {
        revokeModalObjectUrl();
        if (!(entryIconFileInput instanceof HTMLInputElement) || !entryIconFileInput.files || !entryIconFileInput.files[0]) {
            renderModalImagePreview('');

            return;
        }

        modalObjectUrl = URL.createObjectURL(entryIconFileInput.files[0]);
        renderModalImagePreview(modalObjectUrl);
    });

    root.querySelector('[data-cv-interests-add]')?.addEventListener('click', function () {
        openEntryModal(null);
    });

    entrySaveBtn?.addEventListener('click', saveEntryFromModal);

    root.querySelector('[data-cv-interests-entry-icon-browse]')?.addEventListener('click', function () {
        iconBrowser?.open();
    });

    entryIconInput?.addEventListener('input', function () {
        iconBrowser?.syncPreview();
    });

    root.addEventListener('click', function (event) {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
            return;
        }

        const item = target.closest('[data-cv-interests-entry]');
        if (!(item instanceof HTMLElement)) {
            return;
        }

        const index = parseInt(item.getAttribute('data-entry-index') || '-1', 10);
        if (Number.isNaN(index) || index < 0) {
            return;
        }

        if (target.closest('[data-cv-interests-remove]')) {
            if (!window.confirm(i18n.confirmDelete || '')) {
                return;
            }
            entries.splice(index, 1);
            renderList();
            syncHiddenFields();
            return;
        }

        if (target.closest('[data-cv-interests-edit]')) {
            openEntryModal(index);
            return;
        }

        const move = target.closest('[data-cv-interests-move]')?.getAttribute('data-cv-interests-move');
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
