/**
 * Employment countries admin: populate edit country modal.
 */
(function () {
    const modalEl = document.querySelector('[data-employment-country-edit-modal]');
    if (!modalEl) {
        return;
    }

    const form = modalEl.querySelector('[data-employment-country-edit-form]');
    const labelInput = modalEl.querySelector('[data-employment-country-edit-label-input]');
    const localeInput = modalEl.querySelector('[data-employment-country-edit-locale-input]');
    const codeDisplay = modalEl.querySelector('[data-employment-country-edit-code-display]');

    /**
     * @param {HTMLElement} trigger
     */
    function populateFromTrigger(trigger) {
        if (!form || !labelInput) {
            return;
        }

        const editUrl = trigger.dataset.editUrl || '';
        if (editUrl === '') {
            return;
        }

        form.action = editUrl;
        labelInput.value = trigger.dataset.countryLabel || '';
        if (localeInput) {
            localeInput.value = trigger.dataset.countryLocale || '';
        }
        if (codeDisplay) {
            codeDisplay.textContent = trigger.dataset.countryCode || '';
        }
    }

    document.querySelectorAll('[data-employment-country-edit]').forEach((button) => {
        button.addEventListener('click', () => {
            populateFromTrigger(button);
        });
    });
})();
