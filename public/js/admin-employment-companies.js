/**
 * Employment companies admin: modals, country inline create, edit populate.
 */
(function () {
    const countryAddModalEl = document.querySelector('[data-employment-country-add-modal]');
    const countryAddForm = countryAddModalEl
        ? countryAddModalEl.querySelector('[data-employment-country-add-form]')
        : null;
    const countryAddErrorEl = countryAddModalEl
        ? countryAddModalEl.querySelector('[data-employment-country-add-error]')
        : null;
    const countryCreateUrl = countryAddModalEl
        ? countryAddModalEl.dataset.countryCreateUrl || ''
        : '';
    const countryRequestFailedMessage = countryAddModalEl
        ? countryAddModalEl.dataset.countryRequestFailed || 'Error'
        : 'Error';

    /**
     * @returns {HTMLSelectElement[]}
     */
    function getCountrySelects() {
        return Array.from(document.querySelectorAll('[data-employment-country-select]'));
    }

    /**
     * @param {string} code
     * @param {string} label
     * @param {string|null} selectedCode
     */
    function appendCountryOption(code, label, selectedCode) {
        getCountrySelects().forEach((select) => {
            const existing = Array.from(select.options).find((option) => option.value === code);
            if (existing) {
                existing.textContent = label;
            } else {
                const option = document.createElement('option');
                option.value = code;
                option.textContent = label;
                select.appendChild(option);
            }
            if (selectedCode === code) {
                select.value = code;
            }
        });
    }

    /**
     * @param {string} message
     */
    function showCountryAddError(message) {
        if (!countryAddErrorEl) {
            return;
        }
        countryAddErrorEl.textContent = message;
        countryAddErrorEl.classList.remove('d-none');
    }

    function clearCountryAddError() {
        if (!countryAddErrorEl) {
            return;
        }
        countryAddErrorEl.textContent = '';
        countryAddErrorEl.classList.add('d-none');
    }

    if (countryAddForm && countryCreateUrl !== '') {
        countryAddForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            clearCountryAddError();

            const formData = new FormData(countryAddForm);
            try {
                const response = await fetch(countryCreateUrl, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });

                const payload = await response.json();
                if (!response.ok) {
                    showCountryAddError(payload.error || countryRequestFailedMessage);
                    return;
                }

                appendCountryOption(payload.code, payload.label, payload.code);
                countryAddForm.reset();

                const modalInstance = window.bootstrap?.Modal?.getInstance(countryAddModalEl);
                if (modalInstance) {
                    modalInstance.hide();
                }

                if (window.location.pathname.includes('/admin/employment/countries')) {
                    window.location.reload();
                }
            } catch {
                showCountryAddError(countryRequestFailedMessage);
            }
        });

        countryAddModalEl.addEventListener('hidden.bs.modal', () => {
            clearCountryAddError();
            countryAddForm.reset();
        });
    }

    document.querySelectorAll('[data-employment-country-add-trigger]').forEach((button) => {
        button.addEventListener('click', () => {
            const parentModal = button.closest('.modal.show');
            if (parentModal && countryAddModalEl) {
                countryAddModalEl.style.zIndex = '1065';
            }
        });
    });

    if (countryAddModalEl) {
        countryAddModalEl.addEventListener('hidden.bs.modal', () => {
            countryAddModalEl.style.zIndex = '';
        });
    }

    const editModalEl = document.querySelector('[data-employment-company-edit-modal]');
    if (editModalEl) {
        const form = editModalEl.querySelector('[data-employment-company-edit-form]');
        const nameInput = editModalEl.querySelector('[data-employment-company-name-input]');
        const countryInput = editModalEl.querySelector('[data-employment-company-country-input]');
        const recruiterNameInput = editModalEl.querySelector('[data-employment-company-recruiter-name-input]');
        const addressLine1Input = editModalEl.querySelector('[data-employment-company-address-line1-input]');
        const addressLine2Input = editModalEl.querySelector('[data-employment-company-address-line2-input]');
        const addressPostalCodeInput = editModalEl.querySelector('[data-employment-company-address-postal-code-input]');
        const addressCityInput = editModalEl.querySelector('[data-employment-company-address-city-input]');
        const phoneInput = editModalEl.querySelector('[data-employment-company-phone-input]');
        const emailInput = editModalEl.querySelector('[data-employment-company-email-input]');
        const cvDocumentInput = editModalEl.querySelector('[data-employment-company-cv-document-input]');
        const lmDocumentInput = editModalEl.querySelector('[data-employment-company-lm-document-input]');
        const codeDisplay = editModalEl.querySelector('[data-employment-company-code-display]');
        const cvCustomizationLink = editModalEl.querySelector('[data-employment-company-cv-customization-link]');
        const recruiterUrlWrap = editModalEl.querySelector('[data-employment-company-recruiter-url-wrap]');
        const recruiterUrlLink = editModalEl.querySelector('[data-employment-company-recruiter-url-link]');
        const archiveForm = editModalEl.querySelector('[data-employment-company-archive-form]');
        const unarchiveForm = editModalEl.querySelector('[data-employment-company-unarchive-form]');

        /**
         * @param {HTMLElement} trigger
         */
        function populateFromTrigger(trigger) {
            if (!form || !nameInput || !countryInput) {
                return;
            }

            const companyId = trigger.dataset.companyId || '';
            const editUrl = trigger.dataset.editUrl || '';
            const archiveUrl = trigger.dataset.archiveUrl || '';
            const unarchiveUrl = trigger.dataset.unarchiveUrl || '';
            const isArchived = trigger.dataset.companyArchived === '1';
            if (companyId === '' || editUrl === '') {
                return;
            }

            form.action = editUrl;
            if (archiveForm) {
                archiveForm.action = archiveUrl;
                archiveForm.classList.toggle('d-none', isArchived || archiveUrl === '');
            }
            if (unarchiveForm) {
                unarchiveForm.action = unarchiveUrl;
                unarchiveForm.classList.toggle('d-none', !isArchived || unarchiveUrl === '');
            }
            nameInput.value = trigger.dataset.companyName || '';
            countryInput.value = trigger.dataset.companyCountry || '';
            if (recruiterNameInput) {
                recruiterNameInput.value = trigger.dataset.companyRecruiterName || '';
            }
            if (addressLine1Input) {
                addressLine1Input.value = trigger.dataset.companyAddressLine1 || '';
            }
            if (addressLine2Input) {
                addressLine2Input.value = trigger.dataset.companyAddressLine2 || '';
            }
            if (addressPostalCodeInput) {
                addressPostalCodeInput.value = trigger.dataset.companyAddressPostalCode || '';
            }
            if (addressCityInput) {
                addressCityInput.value = trigger.dataset.companyAddressCity || '';
            }
            if (phoneInput) {
                phoneInput.value = trigger.dataset.companyPhone || '';
            }
            if (emailInput) {
                emailInput.value = trigger.dataset.companyEmail || '';
            }
            if (cvDocumentInput) {
                cvDocumentInput.value = trigger.dataset.companyCvDocumentVariantId || '';
            }
            if (lmDocumentInput) {
                lmDocumentInput.value = trigger.dataset.companyLmDocumentVariantId || '';
            }
            if (codeDisplay) {
                codeDisplay.textContent = trigger.dataset.companyCode || '';
            }
            if (cvCustomizationLink) {
                const customizationUrl = trigger.dataset.cvCustomizationUrl || '';
                if (customizationUrl !== '') {
                    cvCustomizationLink.href = customizationUrl;
                    cvCustomizationLink.classList.remove('disabled');
                } else {
                    cvCustomizationLink.href = '#';
                    cvCustomizationLink.classList.add('disabled');
                }
            }
            if (recruiterUrlWrap && recruiterUrlLink) {
                const recruiterUrl = trigger.dataset.recruiterUrl || '';
                if (recruiterUrl !== '') {
                    recruiterUrlLink.href = recruiterUrl;
                    recruiterUrlLink.textContent = recruiterUrl;
                    recruiterUrlWrap.classList.remove('d-none');
                } else {
                    recruiterUrlLink.href = '#';
                    recruiterUrlLink.textContent = '';
                    recruiterUrlWrap.classList.add('d-none');
                }
            }
        }

        document.querySelectorAll('[data-employment-company-edit]').forEach((button) => {
            button.addEventListener('click', () => {
                populateFromTrigger(button);
            });
        });
    }

    const createModalEl = document.querySelector('[data-employment-company-create-modal]');
    if (createModalEl) {
        const createForm = createModalEl.querySelector('[data-employment-company-create-form]');
        createModalEl.addEventListener('hidden.bs.modal', () => {
            if (createForm) {
                createForm.reset();
            }
        });
    }

    /**
     * Row-action dropdowns inside `.table-responsive` use fixed Popper strategy so the menu
     * escapes scroll clipping and flips above the toggle when there is not enough space below.
     *
     * @return {void}
     * @creator Stephane H.
     * @date 2025-06-21
     */
    function initRowActionDropdowns() {
        if (typeof window.bootstrap === 'undefined' || !window.bootstrap.Dropdown) {
            return;
        }

        document.querySelectorAll('.employment-companies-row-actions [data-bs-toggle="dropdown"]').forEach((toggle) => {
            window.bootstrap.Dropdown.getOrCreateInstance(toggle, {
                popperConfig(defaultBsPopperConfig) {
                    const modifiers = (defaultBsPopperConfig?.modifiers ?? []).map((modifier) => {
                        if (modifier.name === 'preventOverflow' || modifier.name === 'flip') {
                            return {
                                ...modifier,
                                options: {
                                    ...modifier.options,
                                    boundary: 'viewport',
                                    altAxis: modifier.name === 'preventOverflow',
                                },
                            };
                        }

                        return modifier;
                    });

                    return {
                        ...defaultBsPopperConfig,
                        strategy: 'fixed',
                        modifiers,
                    };
                },
            });
        });
    }

    initRowActionDropdowns();
})();
