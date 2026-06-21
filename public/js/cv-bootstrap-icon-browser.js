(function (global) {
    'use strict';

    const ICON_CLASS_PATTERN = /^bi-[a-z0-9-]+$/;
    const manifestCache = {};

    /**
     * @param {string} raw
     * @param {string} defaultIcon
     * @returns {string}
     */
    function normalizeBootstrapIconClass(raw, defaultIcon) {
        const fallback = defaultIcon || 'bi-circle';
        let value = String(raw || '').trim();
        if (value === '') {
            return fallback;
        }

        if (!value.startsWith('bi-')) {
            value = 'bi-' + value.replace(/^bi-?/, '');
        }

        return value;
    }

    /**
     * @param {string} manifestUrl
     * @returns {Promise<string[]>}
     */
    async function loadBootstrapIconClasses(manifestUrl) {
        if (manifestCache[manifestUrl]) {
            return manifestCache[manifestUrl];
        }

        const response = await fetch(manifestUrl, { credentials: 'omit' });
        if (!response.ok) {
            throw new Error('manifest');
        }

        const manifest = await response.json();
        const classes = Object.keys(manifest).map(function (name) {
            return 'bi-' + name;
        }).sort();
        manifestCache[manifestUrl] = classes;

        return classes;
    }

    /**
     * @param {object} config
     * @returns {object}
     */
    function createBootstrapIconBrowser(config) {
        const modalEl = config.modalEl;
        const manifestUrl = config.manifestUrl || '';
        const radioName = config.radioName || 'iconBrowserPick';
        const idPrefix = config.idPrefix || 'cvBootstrapIconBrowser';
        const i18n = config.i18n || {};
        const onError = typeof config.onError === 'function' ? config.onError : function () {};
        const getTargetInput = typeof config.getTargetInput === 'function' ? config.getTargetInput : function () { return null; };
        const getPreviewEl = typeof config.getPreviewEl === 'function' ? config.getPreviewEl : function () { return null; };
        const defaultIcon = config.defaultIcon || 'bi-circle';
        const previewClassName = config.previewClassName || 'cv-bootstrap-icon-field__glyph';

        const grid = modalEl?.querySelector('[data-cv-bootstrap-icon-browser-grid]');
        const searchInput = modalEl?.querySelector('[data-cv-bootstrap-icon-browser-search]');
        const countEl = modalEl?.querySelector('[data-cv-bootstrap-icon-browser-count]');
        const emptyEl = modalEl?.querySelector('[data-cv-bootstrap-icon-browser-empty]');
        const confirmBtn = modalEl?.querySelector('[data-cv-bootstrap-icon-browser-confirm]');

        let iconClasses = null;
        let gridBuilt = false;
        const modal = modalEl && typeof bootstrap !== 'undefined'
            ? bootstrap.Modal.getOrCreateInstance(modalEl)
            : null;

        /**
         * @param {string} icon
         * @param {string} inputId
         * @param {boolean} checked
         * @returns {string}
         */
        function buildItemHtml(icon, inputId, checked) {
            const checkedAttr = checked ? ' checked' : '';

            return ''
                + '<div class="cv-bootstrap-icon-browser__item" data-cv-bootstrap-icon-browser-item data-icon="' + icon + '">'
                + '<label class="cv-bootstrap-icon-browser__card" for="' + inputId + '">'
                + '<i class="bi ' + icon + ' cv-bootstrap-icon-browser__glyph" aria-hidden="true"></i>'
                + '<input class="form-check-input cv-bootstrap-icon-browser__radio" type="radio" name="' + radioName + '" id="' + inputId + '" value="' + icon + '"' + checkedAttr + '>'
                + '<span class="cv-bootstrap-icon-browser__name">' + icon + '</span>'
                + '</label>'
                + '</div>';
        }

        /**
         * @param {string} selectedIcon
         */
        function renderGrid(selectedIcon) {
            if (!grid || !iconClasses) {
                return;
            }

            const normalized = normalizeBootstrapIconClass(selectedIcon, defaultIcon);
            if (!gridBuilt) {
                grid.innerHTML = iconClasses.map(function (icon, index) {
                    return buildItemHtml(icon, idPrefix + '-' + index, icon === normalized);
                }).join('');
                gridBuilt = true;
            } else {
                grid.querySelectorAll('input[name="' + radioName + '"]').forEach(function (radio) {
                    radio.checked = radio.value === normalized;
                });
            }

            filterGrid('');
        }

        /**
         * @param {string} query
         */
        function filterGrid(query) {
            if (!grid) {
                return;
            }

            const needle = String(query || '').trim().toLowerCase();
            let visibleCount = 0;
            grid.querySelectorAll('[data-cv-bootstrap-icon-browser-item]').forEach(function (item) {
                const icon = item.getAttribute('data-icon') || '';
                const visible = needle === '' || icon.toLowerCase().includes(needle);
                item.classList.toggle('d-none', !visible);
                if (visible) {
                    visibleCount += 1;
                }
            });

            if (emptyEl) {
                emptyEl.classList.toggle('d-none', visibleCount > 0);
            }

            if (countEl) {
                const template = i18n.bootstrapIconBrowserCount || '%count%';
                countEl.textContent = template.replace('%count%', String(visibleCount));
            }
        }

        function syncPreview() {
            const input = getTargetInput();
            const preview = getPreviewEl();
            if (!(input instanceof HTMLInputElement) || !(preview instanceof HTMLElement)) {
                return;
            }

            const iconClass = normalizeBootstrapIconClass(input.value, defaultIcon);
            preview.className = 'bi ' + iconClass + ' ' + previewClassName;
        }

        function applySelection() {
            const selected = modalEl?.querySelector('input[name="' + radioName + '"]:checked');
            const input = getTargetInput();
            if (!(selected instanceof HTMLInputElement) || !(input instanceof HTMLInputElement)) {
                return;
            }

            input.value = selected.value;
            syncPreview();
            modal?.hide();
        }

        async function open() {
            try {
                iconClasses = await loadBootstrapIconClasses(manifestUrl);
            } catch (error) {
                onError(i18n.flashError || '');
                return;
            }

            if (!iconClasses || iconClasses.length === 0) {
                onError(i18n.flashError || '');
                return;
            }

            const input = getTargetInput();
            const current = normalizeBootstrapIconClass(
                input instanceof HTMLInputElement ? input.value : '',
                defaultIcon
            );
            if (searchInput instanceof HTMLInputElement) {
                searchInput.value = '';
            }

            renderGrid(current);
            modal?.show();
        }

        searchInput?.addEventListener('input', function () {
            if (searchInput instanceof HTMLInputElement) {
                filterGrid(searchInput.value);
            }
        });
        confirmBtn?.addEventListener('click', applySelection);
        grid?.addEventListener('dblclick', function (event) {
            const item = event.target instanceof HTMLElement
                ? event.target.closest('[data-cv-bootstrap-icon-browser-item]')
                : null;
            if (!item) {
                return;
            }

            const radio = item.querySelector('input[name="' + radioName + '"]');
            if (radio instanceof HTMLInputElement) {
                radio.checked = true;
                applySelection();
            }
        });

        return {
            open: open,
            syncPreview: syncPreview,
            normalize: function (raw) {
                return normalizeBootstrapIconClass(raw, defaultIcon);
            },
            isValid: function (raw) {
                return ICON_CLASS_PATTERN.test(normalizeBootstrapIconClass(raw, defaultIcon));
            }
        };
    }

    global.CvBootstrapIconBrowser = {
        ICON_CLASS_PATTERN: ICON_CLASS_PATTERN,
        normalizeBootstrapIconClass: normalizeBootstrapIconClass,
        createBootstrapIconBrowser: createBootstrapIconBrowser
    };
})(window);
