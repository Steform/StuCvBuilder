(function () {
    'use strict';

    const root = document.querySelector('[data-cv-web-profiles-admin]');
    if (!root) {
        return;
    }

    const entriesAccordion = root.querySelector('[data-cv-web-profiles-entries]');
    const entryTemplate = document.getElementById('cv-web-profiles-entry-template');

    function generateUuid() {
        return typeof crypto !== 'undefined' && crypto.randomUUID ? crypto.randomUUID() : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            const r = Math.floor(Math.random() * 16);
            return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
        });
    }

    function reindexEntries() {
        if (!entriesAccordion) {
            return;
        }
        entriesAccordion.querySelectorAll('[data-cv-web-profiles-entry]').forEach(function (entryEl, index) {
            const sortInput = entryEl.querySelector('[data-cv-web-profiles-sort-order]');
            if (sortInput instanceof HTMLInputElement) {
                sortInput.value = String(index);
            }
        });
    }

    root.querySelector('[data-cv-web-profiles-add]')?.addEventListener('click', function () {
        if (!entriesAccordion || !(entryTemplate instanceof HTMLTemplateElement)) {
            return;
        }
        const index = entriesAccordion.querySelectorAll('[data-cv-web-profiles-entry]').length;
        const wrapper = document.createElement('div');
        wrapper.innerHTML = entryTemplate.innerHTML.replace(/__INDEX__/g, String(index)).replace(/__UUID__/g, generateUuid()).trim();
        const entryEl = wrapper.firstElementChild;
        if (entryEl instanceof HTMLElement) {
            entriesAccordion.appendChild(entryEl);
            reindexEntries();
        }
    });

    entriesAccordion?.addEventListener('click', function (event) {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
            return;
        }
        const entryEl = target.closest('[data-cv-web-profiles-entry]');
        if (!(entryEl instanceof HTMLElement)) {
            return;
        }
        if (target.matches('[data-cv-web-profiles-remove]')) {
            entryEl.remove();
            reindexEntries();
            return;
        }
        const move = target.closest('[data-cv-web-profiles-move]')?.getAttribute('data-cv-web-profiles-move');
        if (move === 'up' || move === 'down') {
            const sibling = move === 'up' ? entryEl.previousElementSibling : entryEl.nextElementSibling;
            if (sibling instanceof HTMLElement) {
                if (move === 'up') {
                    entriesAccordion.insertBefore(entryEl, sibling);
                } else {
                    entriesAccordion.insertBefore(sibling, entryEl);
                }
                reindexEntries();
            }
        }
    });
})();
