(function () {
    'use strict';

    const root = document.querySelector('[data-cv-references-admin]');
    if (!root) {
        return;
    }

    const entryTemplate = document.getElementById('cv-references-entry-template');

    function generateUuid() {
        return typeof crypto !== 'undefined' && crypto.randomUUID ? crypto.randomUUID() : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            const r = Math.floor(Math.random() * 16);
            return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
        });
    }

    function reindexLocaleAccordion(accordion) {
        accordion.querySelectorAll('[data-cv-references-entry]').forEach(function (entryEl, index) {
            const sortInput = entryEl.querySelector('[data-cv-references-sort-order]');
            if (sortInput instanceof HTMLInputElement) {
                sortInput.value = String(index);
            }
        });
    }

    root.querySelectorAll('[data-cv-references-add]').forEach(function (button) {
        button.addEventListener('click', function () {
            const locale = button.getAttribute('data-locale') || '';
            const accordion = root.querySelector('[data-cv-references-entries][data-locale="' + locale + '"]');
            if (!accordion || !(entryTemplate instanceof HTMLTemplateElement)) {
                return;
            }
            const index = accordion.querySelectorAll('[data-cv-references-entry]').length;
            const wrapper = document.createElement('div');
            wrapper.innerHTML = entryTemplate.innerHTML.replace(/__LOCALE__/g, locale).replace(/__INDEX__/g, String(index)).replace(/__UUID__/g, generateUuid()).trim();
            const entryEl = wrapper.firstElementChild;
            if (entryEl instanceof HTMLElement) {
                accordion.appendChild(entryEl);
                reindexLocaleAccordion(accordion);
            }
        });
    });

    root.addEventListener('click', function (event) {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
            return;
        }
        const entryEl = target.closest('[data-cv-references-entry]');
        if (!(entryEl instanceof HTMLElement)) {
            return;
        }
        const accordion = entryEl.closest('[data-cv-references-entries]');
        if (target.matches('[data-cv-references-remove]')) {
            entryEl.remove();
            if (accordion instanceof HTMLElement) {
                reindexLocaleAccordion(accordion);
            }
        }
    });

    root.querySelectorAll('[data-bs-target^="#cv-references-locale-"]').forEach(function (tabButton) {
        tabButton.addEventListener('shown.bs.tab', function (event) {
            const target = event.target;
            if (!(target instanceof HTMLElement)) {
                return;
            }
            const paneId = target.getAttribute('data-bs-target')?.replace(/^#/, '') || '';
            const locale = paneId.replace('cv-references-locale-', '');
            root.querySelectorAll('[data-cv-references-preview-locale]').forEach(function (pane) {
                pane.classList.toggle('d-none', (pane.getAttribute('data-cv-references-preview-locale') || '') !== locale);
            });
        });
    });
})();
