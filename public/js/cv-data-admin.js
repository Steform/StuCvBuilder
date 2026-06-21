/**
 * CV data admin — pencil decoration slider value labels.
 */
(function () {
    'use strict';

    /**
     * @brief Update displayed percent next to a pencil tone slider.
     *
     * @param {HTMLInputElement} rangeInput Range input element.
     * @return {void}
     * @date 2026-06-08
     * @author Stephane H.
     */
    function syncPencilToneLabel(rangeInput) {
        var toneKey = rangeInput.getAttribute('data-cv-pencil-tone-range');
        if (!toneKey) {
            return;
        }

        var valueNode = document.querySelector('[data-cv-pencil-tone-value="' + toneKey + '"]');
        if (!valueNode) {
            return;
        }

        valueNode.textContent = rangeInput.value + '%';
    }

    /**
     * @brief Wire pencil tone sliders inside the cv_data customization form.
     *
     * @param {void} No input parameter.
     * @return {void}
     * @date 2026-06-08
     * @author Stephane H.
     */
    function initCvDataAdmin() {
        var rangeInputs = document.querySelectorAll('[data-cv-pencil-tone-range]');
        rangeInputs.forEach(function (rangeInput) {
            syncPencilToneLabel(rangeInput);
            rangeInput.addEventListener('input', function () {
                syncPencilToneLabel(rangeInput);
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCvDataAdmin);
    } else {
        initCvDataAdmin();
    }
})();
