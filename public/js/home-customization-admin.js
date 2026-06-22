/**
 * Home customization admin helpers (quick-tile CSS toggle and intro CKEditor lazy init).
 */
(function () {
    'use strict';

    var INTRO_EDITOR_SELECTOR = 'textarea.ckeditor-cv-rich[data-editor-scope="home"]';

    /**
     * @returns {void}
     */
    function syncQuickTileCustomCssVisibility() {
        var wrapper = document.getElementById('quick-tile-custom-css-wrapper');
        if (!wrapper) {
            return;
        }

        var selected = document.querySelector('input.js-quick-tile-style:checked');
        var isCustom = selected && selected.value === 'custom';
        wrapper.classList.toggle('d-none', !isCustom);
    }

    /**
     * @param {HTMLElement} pane Locale tab pane.
     * @returns {void}
     */
    function initIntroEditorsInPane(pane) {
        if (!window.CvCkeditorBridge || typeof window.CvCkeditorBridge.initTextarea !== 'function') {
            if (typeof window.ClassicEditor === 'undefined') {
                window.setTimeout(function () {
                    initIntroEditorsInPane(pane);
                }, 50);
            }

            return;
        }

        pane.querySelectorAll(INTRO_EDITOR_SELECTOR).forEach(function (node) {
            if (node instanceof HTMLTextAreaElement) {
                window.CvCkeditorBridge.initTextarea(node);
            }
        });
    }

    /**
     * @returns {HTMLElement|null}
     */
    function findActiveIntroLocalePane() {
        var openTextsPane = document.getElementById('collapseHomeAccordionTexts');
        if (!(openTextsPane instanceof HTMLElement) || !openTextsPane.classList.contains('show')) {
            return null;
        }

        var activePane = document.querySelector('#homeIntroLocaleTabContent .tab-pane.active');
        if (activePane instanceof HTMLElement) {
            return activePane;
        }

        return document.querySelector('#homeIntroLocaleTabContent .tab-pane');
    }

    /**
     * @returns {void}
     */
    function initActiveIntroEditors() {
        var pane = findActiveIntroLocalePane();
        if (pane instanceof HTMLElement) {
            initIntroEditorsInPane(pane);
        }
    }

    /**
     * @returns {void}
     */
    function bindIntroLocaleTabs() {
        var tabs = document.getElementById('homeIntroLocaleTabs');
        if (!(tabs instanceof HTMLElement)) {
            return;
        }

        if (tabs.dataset.homeIntroEditorsBound === '1') {
            return;
        }
        tabs.dataset.homeIntroEditorsBound = '1';

        tabs.addEventListener('shown.bs.tab', function (event) {
            var trigger = event.target;
            if (!(trigger instanceof HTMLElement)) {
                return;
            }

            var targetSel = trigger.getAttribute('data-bs-target');
            if (!targetSel) {
                return;
            }

            var pane = document.querySelector(targetSel);
            if (pane instanceof HTMLElement) {
                initIntroEditorsInPane(pane);
            }
        });
    }

    /**
     * @returns {void}
     */
    function bindIntroAccordionShown() {
        var collapse = document.getElementById('collapseHomeAccordionTexts');
        if (!(collapse instanceof HTMLElement)) {
            return;
        }

        if (collapse.dataset.homeIntroEditorsBound === '1') {
            return;
        }
        collapse.dataset.homeIntroEditorsBound = '1';

        collapse.addEventListener('shown.bs.collapse', function () {
            initActiveIntroEditors();
        });

        if (collapse.classList.contains('show')) {
            initActiveIntroEditors();
        }
    }

    /**
     * @returns {void}
     */
    function bindHomeCustomizationFormSubmit() {
        var form = document.querySelector('form.customization-home-form');
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        if (form.dataset.homeIntroEditorsSubmitBound === '1') {
            return;
        }
        form.dataset.homeIntroEditorsSubmitBound = '1';

        form.addEventListener('submit', function () {
            if (window.CvCkeditorBridge && typeof window.CvCkeditorBridge.syncAllInRoot === 'function') {
                window.CvCkeditorBridge.syncAllInRoot(form);
            }
        });
    }

    /**
     * @returns {void}
     */
    function initHomeCustomizationAdmin() {
        syncQuickTileCustomCssVisibility();
        bindIntroLocaleTabs();
        bindIntroAccordionShown();
        bindHomeCustomizationFormSubmit();
    }

    document.addEventListener('DOMContentLoaded', function () {
        var radios = document.querySelectorAll('input.js-quick-tile-style');
        radios.forEach(function (radio) {
            radio.addEventListener('change', syncQuickTileCustomCssVisibility);
        });
        initHomeCustomizationAdmin();
    });

    document.addEventListener('turbo:load', initHomeCustomizationAdmin);
})();
