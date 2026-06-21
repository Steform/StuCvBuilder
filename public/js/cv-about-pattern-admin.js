/**
 * About section pattern customization controls helpers.
 */
(function () {
    'use strict';

    /**
     * @brief Clamp percent to 0–100.
     *
     * @param {string|number} raw Raw value.
     * @return {number}
     * @date 2026-05-23
     * @author Stephane H.
     */
    function clampPercent(raw) {
        var n = parseInt(String(raw), 10);
        if (Number.isNaN(n)) {
            return 0;
        }

        return Math.max(0, Math.min(100, n));
    }

    /**
     * @brief Sync displayed percentage labels from range controls.
     *
     * @param {HTMLElement} root Admin customization root.
     * @return {void}
     * @date 2026-05-28
     * @author Stephane H.
     */
    function syncLabels(root) {
        root.querySelectorAll('[data-cv-about-pattern-mix]').forEach(function (input) {
            var toneRef = input.getAttribute('data-cv-about-pattern-mix');
            if (!toneRef) {
                return;
            }
            var percent = clampPercent(input.value);
            var valueEl = root.querySelector('[data-cv-about-pattern-mix-value="' + toneRef + '"]');
            if (valueEl) {
                valueEl.textContent = percent + '%';
            }
        });
        root.querySelectorAll('[data-cv-about-pattern-percent]').forEach(function (input) {
            var percentRef = input.getAttribute('data-cv-about-pattern-percent');
            if (!percentRef) {
                return;
            }
            var percent = clampPercent(input.value);
            var valueEl = root.querySelector('[data-cv-about-pattern-percent-value="' + percentRef + '"]');
            if (valueEl) {
                valueEl.textContent = percent + '%';
            }
        });
    }

    /**
     * @brief Clear pattern SVG upload when an existing template radio is selected.
     *
     * @param {HTMLElement} root Admin customization root.
     * @return {void}
     * @date 2026-05-27
     * @author Stephane H.
     */
    function bindPatternTemplateChoice(root) {
        var uploadInput = root.querySelector('[data-cv-about-pattern-template-upload]');
        if (!uploadInput) {
            return;
        }

        root.querySelectorAll('[data-cv-about-pattern-template-radio]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                if (radio.checked) {
                    uploadInput.value = '';
                }
            });
        });
    }

    /**
     * @brief Prefill SVG display name from selected upload filename.
     *
     * @param {HTMLElement} root Admin customization root.
     * @return {void}
     * @date 2026-05-28
     * @author Stephane H.
     */
    function bindUploadNamePrefill(root) {
        var uploadInput = root.querySelector('[data-cv-about-pattern-template-upload]');
        var nameInput = root.querySelector('#about-section-pattern-svg-name');
        if (!uploadInput || !nameInput) {
            return;
        }

        uploadInput.addEventListener('change', function () {
            var files = uploadInput.files;
            if (!files || files.length === 0) {
                return;
            }

            var filename = String(files[0].name || '').trim();
            if (filename === '') {
                return;
            }

            var dotIndex = filename.lastIndexOf('.');
            var baseName = dotIndex > 0 ? filename.slice(0, dotIndex) : filename;
            if (baseName.trim() === '') {
                return;
            }

            nameInput.value = baseName.trim();
        });
    }

    document.querySelectorAll('[data-cv-about-pattern-admin]').forEach(function (root) {
        syncLabels(root);
        bindPatternTemplateChoice(root);
        bindUploadNamePrefill(root);
        root.addEventListener('input', function () {
            syncLabels(root);
        });
        root.addEventListener('change', function () {
            syncLabels(root);
        });
    });
})();
