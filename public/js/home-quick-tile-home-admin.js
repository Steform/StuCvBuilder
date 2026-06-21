/**
 * Home landing quick tile create/edit/delete modals (ROLE_TUILE).
 */
(function () {
    'use strict';

    const modalEl = document.querySelector('[data-home-quick-tile-modal]');
    if (!modalEl) {
        return;
    }

    const form = modalEl.querySelector('[data-home-quick-tile-form]');
    const actionInput = modalEl.querySelector('[data-home-quick-tile-action-input]');
    const tileIdInput = modalEl.querySelector('[data-home-quick-tile-id-input]');
    const enabledInput = modalEl.querySelector('[data-home-quick-tile-enabled-input]');
    const linkInput = modalEl.querySelector('[data-home-quick-tile-link-input]');
    const newTabInput = modalEl.querySelector('[data-home-quick-tile-new-tab-input]');
    const iconInput = modalEl.querySelector('[data-home-quick-tile-icon-input]');
    const titleEl = modalEl.querySelector('[data-home-quick-tile-modal-title]');
    const submitBtn = modalEl.querySelector('[data-home-quick-tile-submit]');
    const labelInputs = modalEl.querySelectorAll('[data-home-quick-tile-label-input]');
    const previewWrap = modalEl.querySelector('[data-home-quick-tile-icon-preview-wrap]');
    const previewImg = modalEl.querySelector('[data-home-quick-tile-icon-preview]');

    const deleteModalEl = document.querySelector('[data-home-quick-tile-delete-modal]');
    const deleteTileIdInput = deleteModalEl
        ? deleteModalEl.querySelector('[data-home-quick-tile-delete-id-input]')
        : null;
    const deleteLabelEl = deleteModalEl
        ? deleteModalEl.querySelector('[data-home-quick-tile-delete-label]')
        : null;

    const i18n = {
        titleCreate: modalEl.dataset.titleCreate || '',
        titleEdit: modalEl.dataset.titleEdit || '',
        submitCreate: modalEl.dataset.submitCreate || '',
        submitEdit: modalEl.dataset.submitEdit || '',
    };

    /**
     * @param {HTMLInputElement|null} input
     * @param {boolean} required
     */
    function setFileRequired(input, required) {
        if (!input) {
            return;
        }
        if (required) {
            input.setAttribute('required', 'required');
        } else {
            input.removeAttribute('required');
        }
    }

    /**
     * @param {boolean} isEdit
     */
    function resetCreateForm(isEdit) {
        if (form) {
            form.reset();
        }
        if (actionInput) {
            actionInput.value = isEdit ? 'update' : 'create';
        }
        if (tileIdInput) {
            tileIdInput.value = '';
        }
        if (enabledInput) {
            enabledInput.value = '1';
        }
        setFileRequired(iconInput, !isEdit);
        if (previewWrap) {
            previewWrap.classList.add('d-none');
        }
        if (previewImg) {
            previewImg.removeAttribute('src');
        }
        labelInputs.forEach((input) => {
            input.value = '';
        });
        if (titleEl) {
            titleEl.textContent = isEdit ? i18n.titleEdit : i18n.titleCreate;
        }
        if (submitBtn) {
            submitBtn.textContent = isEdit ? i18n.submitEdit : i18n.submitCreate;
        }
    }

    /**
     * @param {HTMLElement} trigger
     */
    function populateEditForm(trigger) {
        resetCreateForm(true);
        if (tileIdInput) {
            tileIdInput.value = trigger.dataset.tileId || '';
        }
        if (linkInput) {
            linkInput.value = trigger.dataset.tileLink || '';
        }
        if (newTabInput) {
            newTabInput.checked = trigger.dataset.tileOpenNewTab === '1';
        }
        if (enabledInput) {
            enabledInput.value = trigger.dataset.tileEnabled === '1' ? '1' : '0';
        }
        setFileRequired(iconInput, false);

        let labels = {};
        try {
            labels = JSON.parse(trigger.dataset.tileLabels || '{}');
        } catch (error) {
            labels = {};
        }
        labelInputs.forEach((input) => {
            const locale = input.dataset.locale || '';
            input.value = labels[locale] || labels[locale.toLowerCase()] || '';
        });

        const iconSrc = trigger.dataset.tileIconSrc || '';
        if (previewWrap && previewImg && iconSrc !== '') {
            previewImg.src = iconSrc;
            previewWrap.classList.remove('d-none');
        }
    }

    document.querySelectorAll('[data-home-quick-tile-edit]').forEach((button) => {
        button.addEventListener('click', () => {
            populateEditForm(button);
        });
    });

    const addButton = document.querySelector('.home-quick-tile-add__button');
    if (addButton) {
        addButton.addEventListener('click', () => {
            resetCreateForm(false);
        });
    }

    modalEl.addEventListener('hidden.bs.modal', () => {
        resetCreateForm(false);
    });

    if (form) {
        form.addEventListener('submit', (event) => {
            const hasLabel = Array.from(labelInputs).some((input) => input.value.trim() !== '');
            if (!hasLabel) {
                event.preventDefault();
                const firstInput = labelInputs[0];
                if (firstInput) {
                    firstInput.focus();
                }
            }
        });
    }

    document.querySelectorAll('[data-home-quick-tile-delete]').forEach((button) => {
        button.addEventListener('click', () => {
            if (deleteTileIdInput) {
                deleteTileIdInput.value = button.dataset.tileId || '';
            }
            if (deleteLabelEl) {
                deleteLabelEl.textContent = button.dataset.tileLabel || '—';
            }
        });
    });
})();
