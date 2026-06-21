(function () {
    'use strict';

    const root = document.querySelector('[data-cv-skills-admin]');
    if (!root) {
        return;
    }

    const csrfToken = root.getAttribute('data-csrf-token') || '';
    const activeLocales = JSON.parse(root.getAttribute('data-active-locales') || '[]');
    const defaultLocale = root.getAttribute('data-default-locale') || 'fr';
    const routes = JSON.parse(root.getAttribute('data-routes') || '{}');
    const i18n = JSON.parse(root.getAttribute('data-i18n') || '{}');

    let catalog = { categories: [] };
    try {
        const catalogEl = document.getElementById('cv-skills-admin-catalog');
        catalog = JSON.parse(catalogEl?.textContent || '{"categories":[]}');
    } catch (error) {
        catalog = { categories: [] };
    }

    const categoryModalEl = document.getElementById('cvSkillsCategoryModal');
    const skillModalEl = document.getElementById('cvSkillsSkillModal');
    const categoryForm = document.getElementById('cvSkillsCategoryForm');
    const skillForm = document.getElementById('cvSkillsSkillForm');
    const alertBox = root.querySelector('[data-cv-skills-alert]');
    const treeMount = root.querySelector('[data-cv-skills-tree-mount]');
    let successTimeoutId = null;
    const categoryLevelSelect = categoryForm?.querySelector('[data-cv-skills-category-level]');
    const parentCategorySelect = categoryForm?.querySelector('[data-cv-skills-parent-category]');
    const parentSubcategorySelect = categoryForm?.querySelector('[data-cv-skills-parent-subcategory]');
    const placementHelp = skillForm?.querySelector('[data-cv-skills-placement-help]');
    const layoutDesktopInput = categoryForm?.querySelector('[data-cv-skills-layout-desktop]');
    const layoutTabletInput = categoryForm?.querySelector('[data-cv-skills-layout-tablet]');
    const layoutMobileInput = categoryForm?.querySelector('[data-cv-skills-layout-mobile]');
    const layoutDesktopHelp = categoryForm?.querySelector('[data-cv-skills-layout-desktop-help]');
    const layoutTabletHelp = categoryForm?.querySelector('[data-cv-skills-layout-tablet-help]');
    const layoutMobileHelp = categoryForm?.querySelector('[data-cv-skills-layout-mobile-help]');

    const GRID_ROOT_BUDGET = 12;

    const bootstrapIconsManifestUrl = root.getAttribute('data-bootstrap-icons-manifest-url') || '';
    const iconBrowserModalEl = document.getElementById('cvSkillsBootstrapIconBrowserModal');
    const bootstrapIconInput = skillForm?.querySelector('[data-cv-skills-bootstrap-icon-input]');
    const bootstrapIconPreview = skillForm?.querySelector('[data-cv-skills-bootstrap-icon-preview]');

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

    const categoryModal = getModal(categoryModalEl);
    const skillModal = getModal(skillModalEl);

    /**
     * @param {string} message
     */
    function showError(message) {
        if (successTimeoutId !== null) {
            clearTimeout(successTimeoutId);
            successTimeoutId = null;
        }

        if (!alertBox) {
            window.alert(message);
            return;
        }

        alertBox.textContent = message;
        alertBox.classList.remove('d-none', 'alert-success');
        alertBox.classList.add('alert-danger');
    }

    function hideError() {
        if (alertBox) {
            alertBox.classList.add('d-none');
            alertBox.textContent = '';
            alertBox.classList.remove('alert-success');
            alertBox.classList.add('alert-danger');
        }
    }

    /**
     * @param {string} message
     */
    function showSuccess(message) {
        if (!alertBox) {
            return;
        }

        if (successTimeoutId !== null) {
            clearTimeout(successTimeoutId);
        }

        alertBox.textContent = message;
        alertBox.classList.remove('d-none', 'alert-danger');
        alertBox.classList.add('alert-success');
        successTimeoutId = window.setTimeout(function () {
            hideError();
            successTimeoutId = null;
        }, 3000);
    }

    /**
     * @brief Apply catalog JSON and admin tree HTML from an API success payload.
     *
     * @param {{ catalog?: { categories?: unknown[] }, treeHtml?: string }} result
     */
    function applyCatalogUpdate(result) {
        if (result.catalog) {
            catalog = result.catalog;
            const catalogEl = document.getElementById('cv-skills-admin-catalog');
            if (catalogEl) {
                catalogEl.textContent = JSON.stringify(catalog);
            }
        }

        if (typeof result.treeHtml === 'string' && treeMount) {
            treeMount.innerHTML = result.treeHtml;
        }

        rebuildPlacementSelect();
        showSuccess(i18n.flashSaved || '');
    }

    const iconBrowser = globalThis.CvBootstrapIconBrowser?.createBootstrapIconBrowser({
        modalEl: iconBrowserModalEl,
        manifestUrl: bootstrapIconsManifestUrl,
        radioName: 'cvSkillsIconBrowserPick',
        idPrefix: 'cvSkillsBootstrapIconBrowser',
        defaultIcon: 'bi-circle',
        i18n: i18n,
        onError: function () {
            showError(i18n.flashError || '');
        },
        getTargetInput: function () {
            return bootstrapIconInput;
        },
        getPreviewEl: function () {
            return bootstrapIconPreview;
        }
    });

    /**
     * @param {string} raw
     * @returns {string}
     */
    function normalizeBootstrapIconClass(raw) {
        if (iconBrowser) {
            return iconBrowser.normalize(raw);
        }

        return globalThis.CvBootstrapIconBrowser?.normalizeBootstrapIconClass(raw, 'bi-circle') || 'bi-circle';
    }

    /**
     * @brief Sync the inline Bootstrap icon preview with the text input value.
     */
    function syncBootstrapIconPreview() {
        iconBrowser?.syncPreview();
    }

    bootstrapIconInput?.addEventListener('input', syncBootstrapIconPreview);
    skillForm?.querySelector('[data-cv-skills-bootstrap-icon-browse]')?.addEventListener('click', function () {
        iconBrowser?.open();
    });
    syncBootstrapIconPreview();

    /**
     * @param {object} node
     * @param {string} locale
     * @returns {string}
     */
    function resolveNodeLabel(node, locale) {
        if (!node || typeof node !== 'object') {
            return '';
        }

        if (node.labelMode === 'canonical') {
            return String(node.canonicalLabel || '');
        }

        const labels = node.labelsByLocale || {};
        const candidate = labels[locale] || labels[defaultLocale] || node.canonicalLabel || '';

        return String(candidate);
    }

    /**
     * @param {HTMLSelectElement|null} select
     * @param {Array<{value: string, label: string}>} options
     * @param {string} selectedValue
     */
    function fillSelect(select, options, selectedValue) {
        if (!select) {
            return;
        }

        select.innerHTML = '';
        options.forEach(function (optionData) {
            const option = document.createElement('option');
            option.value = optionData.value;
            option.textContent = optionData.label;
            if (optionData.value === selectedValue) {
                option.selected = true;
            }
            select.appendChild(option);
        });
    }

    /**
     * @param {string} categoryId
     * @returns {Array<{value: string, label: string}>}
     */
    function buildSubcategoryOptions(categoryId) {
        const category = (catalog.categories || []).find(function (row) {
            return row.id === categoryId;
        });
        if (!category) {
            return [];
        }

        return (category.subcategories || []).map(function (subcategory) {
            return {
                value: subcategory.id,
                label: resolveNodeLabel(subcategory, defaultLocale)
            };
        });
    }

    /**
     * @returns {void}
     */
    function populateCategoryParentSelects(categoryId, subcategoryId) {
        const categoryOptions = (catalog.categories || []).map(function (category) {
            return {
                value: category.id,
                label: resolveNodeLabel(category, defaultLocale)
            };
        });

        fillSelect(parentCategorySelect, categoryOptions, categoryId);
        fillSelect(parentSubcategorySelect, buildSubcategoryOptions(categoryId), subcategoryId);
    }

    /**
     * @param {object|null|undefined} node
     * @param {string} breakpoint
     * @returns {number}
     */
    function readLayoutSpan(node, breakpoint) {
        const layout = node?.layout || {};
        const block = layout[breakpoint] || (breakpoint === 'tablet' ? layout.mobile : {}) || {};
        const span = parseInt(String(block.span || GRID_ROOT_BUDGET), 10);

        return Math.max(1, Math.min(GRID_ROOT_BUDGET, Number.isFinite(span) ? span : GRID_ROOT_BUDGET));
    }

    /**
     * @param {number} level
     * @param {string} categoryId
     * @param {string} subcategoryId
     * @param {string} breakpoint
     * @returns {number}
     */
    function resolveLayoutBudget(level, categoryId, subcategoryId, breakpoint) {
        if (level === 1) {
            return GRID_ROOT_BUDGET;
        }

        const category = (catalog.categories || []).find(function (entry) {
            return entry.id === categoryId;
        });
        if (!category) {
            return GRID_ROOT_BUDGET;
        }

        if (level === 2) {
            return readLayoutSpan(category, breakpoint);
        }

        const subcategory = (category.subcategories || []).find(function (entry) {
            return entry.id === subcategoryId;
        });
        if (!subcategory) {
            return readLayoutSpan(category, breakpoint);
        }

        return readLayoutSpan(subcategory, breakpoint);
    }

    /**
     * @param {number} value
     * @param {number} max
     * @returns {number}
     */
    function clampLayoutSpan(value, max) {
        const parsed = parseInt(String(value), 10);
        const safeMax = Math.max(1, Math.min(GRID_ROOT_BUDGET, max));
        if (!Number.isFinite(parsed)) {
            return safeMax;
        }

        return Math.max(1, Math.min(safeMax, parsed));
    }

    /**
     * @brief Sync layout span min/max and help text from parent budgets.
     */
    function syncLayoutSpanLimits() {
        if (!categoryForm || !categoryLevelSelect) {
            return;
        }

        const level = parseInt(categoryLevelSelect.value || '1', 10);
        const categoryId = level === 1 ? '' : (parentCategorySelect?.value || categoryForm.querySelector('[data-cv-skills-field="categoryId"]')?.value || '');
        const subcategoryId = level === 3
            ? (parentSubcategorySelect?.value || categoryForm.querySelector('[data-cv-skills-field="subcategoryId"]')?.value || '')
            : '';
        const maxDesktop = resolveLayoutBudget(level, categoryId, subcategoryId, 'desktop');
        const maxTablet = resolveLayoutBudget(level, categoryId, subcategoryId, 'tablet');
        const maxMobile = resolveLayoutBudget(level, categoryId, subcategoryId, 'mobile');

        if (layoutDesktopInput) {
            layoutDesktopInput.min = '1';
            layoutDesktopInput.max = String(maxDesktop);
            layoutDesktopInput.value = String(clampLayoutSpan(layoutDesktopInput.value, maxDesktop));
        }
        if (layoutTabletInput) {
            layoutTabletInput.min = '1';
            layoutTabletInput.max = String(maxTablet);
            layoutTabletInput.value = String(clampLayoutSpan(layoutTabletInput.value, maxTablet));
        }
        if (layoutMobileInput) {
            layoutMobileInput.min = '1';
            layoutMobileInput.max = String(maxMobile);
            layoutMobileInput.value = String(clampLayoutSpan(layoutMobileInput.value, maxMobile));
        }
        if (layoutDesktopHelp && i18n.layoutDesktopHelp) {
            layoutDesktopHelp.textContent = i18n.layoutDesktopHelp.replace('%max%', String(maxDesktop));
        }
        if (layoutTabletHelp && i18n.layoutTabletHelp) {
            layoutTabletHelp.textContent = i18n.layoutTabletHelp.replace('%max%', String(maxTablet));
        }
        if (layoutMobileHelp && i18n.layoutMobileHelp) {
            layoutMobileHelp.textContent = i18n.layoutMobileHelp.replace('%max%', String(maxMobile));
        }
    }

    /**
     * @param {boolean} isEdit
     */
    function syncCategoryLevelUi(isEdit) {
        if (!categoryForm || !categoryLevelSelect) {
            return;
        }

        const level = parseInt(categoryLevelSelect.value || '1', 10);
        const levelWrap = categoryForm.querySelector('[data-cv-skills-category-level-wrap]');
        const parentCategoryWrap = categoryForm.querySelector('[data-cv-skills-parent-category-wrap]');
        const parentSubcategoryWrap = categoryForm.querySelector('[data-cv-skills-parent-subcategory-wrap]');

        if (levelWrap) {
            levelWrap.classList.toggle('d-none', isEdit);
        }

        categoryLevelSelect.disabled = isEdit;
        if (parentCategorySelect) {
            parentCategorySelect.disabled = isEdit;
        }
        if (parentSubcategorySelect) {
            parentSubcategorySelect.disabled = isEdit;
        }

        if (parentCategoryWrap) {
            parentCategoryWrap.classList.toggle('d-none', level === 1);
        }
        if (parentSubcategoryWrap) {
            parentSubcategoryWrap.classList.toggle('d-none', level !== 3);
        }

        if (level >= 2) {
            const selectedCategory = parentCategorySelect?.value || categoryForm.querySelector('[data-cv-skills-field="categoryId"]')?.value || '';
            populateCategoryParentSelects(selectedCategory, parentSubcategorySelect?.value || '');
        }

        syncLayoutSpanLimits();
    }

    /**
     * @returns {void}
     */
    function syncCategoryHiddenFieldsFromParents() {
        if (!categoryForm || !categoryLevelSelect) {
            return;
        }

        const level = parseInt(categoryLevelSelect.value || '1', 10);
        const categoryIdField = categoryForm.querySelector('[data-cv-skills-field="categoryId"]');
        const subcategoryIdField = categoryForm.querySelector('[data-cv-skills-field="subcategoryId"]');

        if (level === 1) {
            if (categoryIdField) {
                categoryIdField.value = '';
            }
            if (subcategoryIdField) {
                subcategoryIdField.value = '';
            }

            return;
        }

        if (categoryIdField) {
            categoryIdField.value = parentCategorySelect?.value || '';
        }

        if (subcategoryIdField) {
            subcategoryIdField.value = level === 3 ? parentSubcategorySelect?.value || '' : '';
        }
    }

    /**
     * @returns {boolean}
     */
    function rebuildPlacementSelect() {
        const select = document.getElementById('cvSkillsSkillPlacement');
        if (!select) {
            return false;
        }

        select.innerHTML = '';
        let optionCount = 0;

        (catalog.categories || []).forEach(function (category) {
            const base = resolveNodeLabel(category, defaultLocale);
            const directOption = document.createElement('option');
            directOption.value = [category.id, '', ''].join('|');
            directOption.textContent = base + (i18n.placementDirectSuffix || '');
            select.appendChild(directOption);
            optionCount += 1;

            (category.subcategories || []).forEach(function (subcategory) {
                const subLabel = resolveNodeLabel(subcategory, defaultLocale);
                const groupList = subcategory.groups || [];
                if (groupList.length > 0) {
                    groupList.forEach(function (group) {
                        const option = document.createElement('option');
                        option.value = [category.id, subcategory.id, group.id].join('|');
                        option.textContent = base + ' › ' + subLabel + ' › ' + resolveNodeLabel(group, defaultLocale);
                        select.appendChild(option);
                        optionCount += 1;
                    });
                } else {
                    const option = document.createElement('option');
                    option.value = [category.id, subcategory.id, ''].join('|');
                    option.textContent = base + ' › ' + subLabel;
                    select.appendChild(option);
                    optionCount += 1;
                }
            });
        });

        const hasPlacement = optionCount > 0;
        if (placementHelp) {
            placementHelp.classList.toggle('d-none', hasPlacement);
        }
        select.disabled = !hasPlacement;

        return hasPlacement;
    }

    /**
     * @param {HTMLElement} container
     * @param {string} labelMode
     */
    function syncLabelModeFields(container, labelMode) {
        const canonicalWrap = container.querySelector('[data-cv-skills-canonical-wrap]');
        const localizedWrap = container.querySelector('[data-cv-skills-localized-wrap]');
        const isCanonical = labelMode === 'canonical';
        if (canonicalWrap) {
            canonicalWrap.classList.toggle('d-none', !isCanonical);
        }
        if (localizedWrap) {
            localizedWrap.classList.toggle('d-none', isCanonical);
        }
    }

    /**
     * @param {HTMLFormElement} form
     * @returns {string}
     */
    function readLabelModeFromForm(form) {
        const checked = form.querySelector('input[name="labelMode"]:checked');
        return checked instanceof HTMLInputElement ? checked.value : 'localized';
    }

    if (categoryForm) {
        categoryForm.querySelectorAll('input[name="labelMode"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                syncLabelModeFields(categoryForm, readLabelModeFromForm(categoryForm));
            });
        });
        syncLabelModeFields(categoryForm, readLabelModeFromForm(categoryForm));
    }

    if (skillForm) {
        skillForm.querySelectorAll('input[name="labelMode"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                syncLabelModeFields(skillForm, readLabelModeFromForm(skillForm));
            });
        });
        syncLabelModeFields(skillForm, readLabelModeFromForm(skillForm));
    }

    categoryLevelSelect?.addEventListener('change', function () {
        syncCategoryLevelUi(false);
        syncCategoryHiddenFieldsFromParents();
    });

    parentCategorySelect?.addEventListener('change', function () {
        fillSelect(parentSubcategorySelect, buildSubcategoryOptions(parentCategorySelect.value), '');
        syncCategoryHiddenFieldsFromParents();
        syncLayoutSpanLimits();
    });

    parentSubcategorySelect?.addEventListener('change', function () {
        syncCategoryHiddenFieldsFromParents();
        syncLayoutSpanLimits();
    });

    skillForm?.querySelectorAll('input[name="iconType"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            const iconType = skillForm.querySelector('input[name="iconType"]:checked')?.value || 'bootstrap';
            const bootstrapWrap = skillForm.querySelector('[data-cv-skills-bootstrap-icon-wrap]');
            const uploadWrap = skillForm.querySelector('[data-cv-skills-upload-icon-wrap]');
            if (bootstrapWrap) {
                bootstrapWrap.classList.toggle('d-none', iconType !== 'bootstrap');
            }
            if (uploadWrap) {
                uploadWrap.classList.toggle('d-none', iconType !== 'image');
            }
        });
    });

    /**
     * @param {number} level
     * @param {string} categoryId
     * @param {string} subcategoryId
     * @param {string} nodeId
     * @returns {object|null}
     */
    function findCategoryNode(level, categoryId, subcategoryId, nodeId) {
        const category = (catalog.categories || []).find(function (row) {
            return row.id === categoryId;
        });
        if (!category) {
            return null;
        }

        if (level === 1) {
            return category.id === nodeId ? category : null;
        }

        const subcategory = (category.subcategories || []).find(function (row) {
            return row.id === subcategoryId || row.id === nodeId;
        });
        if (!subcategory) {
            return null;
        }

        if (level === 2) {
            return subcategory.id === nodeId ? subcategory : null;
        }

        return (subcategory.groups || []).find(function (row) {
            return row.id === nodeId;
        }) || null;
    }

    /**
     * @param {string} skillId
     * @returns {object|null}
     */
    function findSkillNode(skillId) {
        for (const category of catalog.categories || []) {
            for (const item of category.items || []) {
                if (item.id === skillId) {
                    return { item: item, categoryId: category.id, subcategoryId: '', groupId: '' };
                }
            }

            for (const subcategory of category.subcategories || []) {
                for (const item of subcategory.items || []) {
                    if (item.id === skillId) {
                        return { item: item, categoryId: category.id, subcategoryId: subcategory.id, groupId: '' };
                    }
                }
                for (const group of subcategory.groups || []) {
                    for (const item of group.items || []) {
                        if (item.id === skillId) {
                            return {
                                item: item,
                                categoryId: category.id,
                                subcategoryId: subcategory.id,
                                groupId: group.id
                            };
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param {number} level
     * @param {string} categoryId
     * @param {string} subcategoryId
     * @param {object|null} node
     */
    function fillCategoryForm(level, categoryId, subcategoryId, node) {
        if (!categoryForm || !categoryLevelSelect) {
            return;
        }

        const isEdit = node !== null;
        categoryForm.querySelector('input[name="id"]').value = node ? node.id : '';
        categoryLevelSelect.value = String(level);

        const parentCategoryId = level === 1 ? '' : categoryId;
        const parentSubcategoryId = level === 3 ? subcategoryId : '';

        populateCategoryParentSelects(parentCategoryId, parentSubcategoryId);

        const categoryIdField = categoryForm.querySelector('[data-cv-skills-field="categoryId"]');
        const subcategoryIdField = categoryForm.querySelector('[data-cv-skills-field="subcategoryId"]');
        if (categoryIdField) {
            categoryIdField.value = parentCategoryId;
        }
        if (subcategoryIdField) {
            subcategoryIdField.value = parentSubcategoryId;
        }

        const labelMode = node?.labelMode || 'localized';
        categoryForm.querySelectorAll('input[name="labelMode"]').forEach(function (radio) {
            radio.checked = radio.value === labelMode;
        });
        categoryForm.querySelector('input[name="canonicalLabel"]').value = node?.canonicalLabel || '';
        activeLocales.forEach(function (locale) {
            const input = categoryForm.querySelector('[data-cv-skills-locale-input="' + locale + '"]');
            if (input) {
                input.value = (node?.labelsByLocale || {})[locale] || '';
            }
        });
        const visible = categoryForm.querySelector('input[name="visibleOnPrimary"]');
        if (visible) {
            visible.checked = node ? node.visibleOnPrimary !== false : true;
        }

        syncLabelModeFields(categoryForm, labelMode);
        syncCategoryLevelUi(isEdit);
        syncCategoryHiddenFieldsFromParents();
        const resolvedLevel = parseInt(categoryLevelSelect.value || '1', 10);
        const resolvedCategoryId = resolvedLevel === 1 ? '' : parentCategoryId;
        const resolvedSubcategoryId = resolvedLevel === 3 ? parentSubcategoryId : '';
        const maxDesktop = resolveLayoutBudget(resolvedLevel, resolvedCategoryId, resolvedSubcategoryId, 'desktop');
        const maxTablet = resolveLayoutBudget(resolvedLevel, resolvedCategoryId, resolvedSubcategoryId, 'tablet');
        const maxMobile = resolveLayoutBudget(resolvedLevel, resolvedCategoryId, resolvedSubcategoryId, 'mobile');
        if (layoutDesktopInput) {
            layoutDesktopInput.value = String(node ? readLayoutSpan(node, 'desktop') : maxDesktop);
        }
        if (layoutTabletInput) {
            layoutTabletInput.value = String(node ? readLayoutSpan(node, 'tablet') : maxTablet);
        }
        if (layoutMobileInput) {
            layoutMobileInput.value = String(node ? readLayoutSpan(node, 'mobile') : maxMobile);
        }
        syncLayoutSpanLimits();
    }

    /**
     * @param {object|null} ctx
     */
    function fillSkillForm(ctx) {
        if (!skillForm) {
            return;
        }

        const hasPlacement = rebuildPlacementSelect();
        const node = ctx?.item || null;
        skillForm.querySelector('input[name="id"]').value = node ? node.id : '';
        skillForm.querySelector('input[name="iconPath"]').value = node?.iconPath || '';

        const preferredPlacement = ctx
            ? [ctx.categoryId || '', ctx.subcategoryId || '', ctx.groupId || ''].join('|')
            : '';
        const placementSelect = skillForm.querySelector('#cvSkillsSkillPlacement');
        if (placementSelect) {
            if (preferredPlacement && placementSelect.querySelector('option[value="' + preferredPlacement + '"]')) {
                placementSelect.value = preferredPlacement;
            } else if (placementSelect.options.length > 0) {
                placementSelect.selectedIndex = 0;
            } else {
                placementSelect.value = '';
            }
        }

        const labelMode = node?.labelMode || 'localized';
        skillForm.querySelectorAll('input[name="labelMode"]').forEach(function (radio) {
            radio.checked = radio.value === labelMode;
        });
        skillForm.querySelector('input[name="canonicalLabel"]').value = node?.canonicalLabel || '';
        activeLocales.forEach(function (locale) {
            const input = skillForm.querySelector('[data-cv-skills-locale-input="' + locale + '"]');
            if (input) {
                input.value = (node?.labelsByLocale || {})[locale] || '';
            }
        });
        const iconType = node?.iconType || 'bootstrap';
        skillForm.querySelectorAll('input[name="iconType"]').forEach(function (radio) {
            radio.checked = radio.value === iconType;
        });
        if (bootstrapIconInput) {
            bootstrapIconInput.value = normalizeBootstrapIconClass(node?.icon || 'bi-circle');
        }
        syncBootstrapIconPreview();
        const visible = skillForm.querySelector('input[name="visibleOnPrimary"]');
        if (visible) {
            visible.checked = node ? node.visibleOnPrimary !== false : true;
        }
        syncLabelModeFields(skillForm, labelMode);
        skillForm.querySelector('input[name="iconType"]:checked')?.dispatchEvent(new Event('change'));

        const preview = skillForm.querySelector('[data-cv-skills-icon-preview]');
        if (preview) {
            preview.innerHTML = '';
            if (node?.iconType === 'image' && node.iconPath) {
                preview.hidden = false;
                const img = document.createElement('img');
                img.src = '/' + node.iconPath.replace(/^\//, '');
                img.alt = '';
                img.width = 32;
                img.height = 32;
                preview.appendChild(img);
            } else {
                preview.hidden = true;
            }
        }

        if (!hasPlacement && !node) {
            showError(i18n.placementEmpty || i18n.flashError || '');
        }
    }

    /**
     * @param {string} url
     * @param {FormData} body
     * @returns {Promise<object>}
     */
    async function postForm(url, body) {
        body.append('_csrf_token', csrfToken);
        const response = await fetch(url, {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        let payload = {};
        try {
            payload = await response.json();
        } catch (error) {
            throw new Error(i18n.flashError || String(response.status) || 'error');
        }

        if (!response.ok || payload.status !== 'ok') {
            const key = payload.messageKey || '';
            const messageByKey = {
                'dashboard.customization_cv.skills.flash_invalid': i18n.flashError,
                'dashboard.customization_cv.skills.flash_delete_blocked': i18n.flashDeleteBlocked,
                'dashboard.customization_cv.skills.flash_invalid_icon': i18n.flashInvalidIcon,
                'dashboard.customization_cv.skills.flash_invalid_bootstrap_icon': i18n.flashInvalidBootstrapIcon,
                'dashboard.customization_cv.flash.invalid_csrf': i18n.flashInvalidCsrf,
                'dashboard.customization_cv.skills.flash_move_blocked': i18n.flashMoveBlocked
            };
            throw new Error(messageByKey[key] || i18n.flashError || key);
        }

        return payload;
    }

    categoryForm?.addEventListener('submit', async function (event) {
        event.preventDefault();
        hideError();
        syncCategoryHiddenFieldsFromParents();

        const level = parseInt(categoryLevelSelect?.value || '1', 10);
        if (level >= 2 && !(parentCategorySelect?.value || '')) {
            showError(i18n.flashError || '');
            return;
        }
        if (level === 3 && !(parentSubcategorySelect?.value || '')) {
            showError(i18n.flashError || '');
            return;
        }

        const formData = new FormData(categoryForm);
        formData.set('level', String(level));
        formData.set('categoryId', categoryForm.querySelector('[data-cv-skills-field="categoryId"]')?.value || '');
        formData.set('subcategoryId', categoryForm.querySelector('[data-cv-skills-field="subcategoryId"]')?.value || '');
        formData.set('visibleOnPrimary', categoryForm.querySelector('input[name="visibleOnPrimary"]')?.checked ? '1' : '0');

        try {
            const result = await postForm(routes.categorySave, formData);
            categoryModal?.hide();
            applyCatalogUpdate(result);
        } catch (error) {
            showError(error instanceof Error ? error.message : String(error));
        }
    });

    skillForm?.addEventListener('submit', async function (event) {
        event.preventDefault();
        hideError();

        const placementRaw = skillForm.querySelector('#cvSkillsSkillPlacement')?.value || '';
        const placement = placementRaw.split('|');
        if (!placement[0]) {
            showError(i18n.placementRequired || i18n.placementEmpty || i18n.flashError || '');
            return;
        }

        const iconType = skillForm.querySelector('input[name="iconType"]:checked')?.value || 'bootstrap';
        if (iconType === 'bootstrap') {
            const iconValue = normalizeBootstrapIconClass(bootstrapIconInput?.value || '');
            if (!iconBrowser?.isValid(iconValue)) {
                showError(i18n.flashInvalidBootstrapIcon || i18n.flashError || '');
                return;
            }

            if (bootstrapIconInput) {
                bootstrapIconInput.value = iconValue;
            }
        }

        const formData = new FormData(skillForm);
        formData.set('categoryId', placement[0]);
        formData.set('subcategoryId', placement[1] || '');
        formData.set('groupId', placement[2] || '');
        formData.delete('placement');
        formData.set('visibleOnPrimary', skillForm.querySelector('input[name="visibleOnPrimary"]')?.checked ? '1' : '0');

        try {
            const result = await postForm(routes.skillSave, formData);
            skillModal?.hide();
            applyCatalogUpdate(result);
        } catch (error) {
            showError(error instanceof Error ? error.message : String(error));
        }
    });

    root.addEventListener('click', async function (event) {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
            return;
        }

        const button = target.closest('[data-cv-skills-action]');
        if (!(button instanceof HTMLElement)) {
            return;
        }

        const action = button.getAttribute('data-cv-skills-action') || '';
        hideError();

        if (action === 'add-category') {
            document.getElementById('cvSkillsCategoryModalLabel').textContent = i18n.categoryTitleAdd || '';
            fillCategoryForm(1, '', '', null);
            categoryModal?.show();
            return;
        }

        if (action === 'add-subcategory') {
            document.getElementById('cvSkillsCategoryModalLabel').textContent = i18n.categoryTitleAdd || '';
            fillCategoryForm(2, button.getAttribute('data-category-id') || '', '', null);
            categoryModal?.show();
            return;
        }

        if (action === 'add-group') {
            document.getElementById('cvSkillsCategoryModalLabel').textContent = i18n.categoryTitleAdd || '';
            fillCategoryForm(
                3,
                button.getAttribute('data-category-id') || '',
                button.getAttribute('data-subcategory-id') || '',
                null
            );
            categoryModal?.show();
            return;
        }

        if (action === 'edit-category') {
            const level = parseInt(button.getAttribute('data-level') || '1', 10);
            const nodeId = button.getAttribute('data-id') || '';
            const categoryId = button.getAttribute('data-category-id') || nodeId;
            const subcategoryId = button.getAttribute('data-subcategory-id') || '';
            const node = findCategoryNode(level, categoryId, subcategoryId, nodeId);
            document.getElementById('cvSkillsCategoryModalLabel').textContent = i18n.categoryTitleEdit || '';
            fillCategoryForm(level, categoryId, subcategoryId, node);
            categoryModal?.show();
            return;
        }

        if (action === 'move-category') {
            if (button.hasAttribute('disabled')) {
                showError(i18n.flashMoveBlocked || i18n.flashError || '');
                return;
            }

            const formData = new FormData();
            formData.append('level', button.getAttribute('data-level') || '');
            formData.append('id', button.getAttribute('data-id') || '');
            formData.append('categoryId', button.getAttribute('data-category-id') || '');
            formData.append('subcategoryId', button.getAttribute('data-subcategory-id') || '');
            formData.append('direction', button.getAttribute('data-direction') || '');

            try {
                const result = await postForm(routes.categoryMove, formData);
                applyCatalogUpdate(result);
            } catch (error) {
                showError(error instanceof Error ? error.message : String(error));
            }
            return;
        }

        if (action === 'delete-category') {
            if (button.hasAttribute('disabled')) {
                showError(i18n.flashDeleteBlocked || '');
                return;
            }
            if (!window.confirm(i18n.confirmDelete || '')) {
                return;
            }
            const formData = new FormData();
            formData.append('level', button.getAttribute('data-level') || '');
            formData.append('id', button.getAttribute('data-id') || '');
            formData.append('categoryId', button.getAttribute('data-category-id') || '');
            formData.append('subcategoryId', button.getAttribute('data-subcategory-id') || '');
            try {
                const result = await postForm(routes.categoryDelete, formData);
                applyCatalogUpdate(result);
            } catch (error) {
                showError(error instanceof Error ? error.message : String(error));
            }
            return;
        }

        if (action === 'add-skill') {
            document.getElementById('cvSkillsSkillModalLabel').textContent = i18n.skillTitleAdd || '';
            fillSkillForm({
                categoryId: button.getAttribute('data-category-id') || '',
                subcategoryId: button.getAttribute('data-subcategory-id') || '',
                groupId: button.getAttribute('data-group-id') || '',
                item: null
            });
            rebuildPlacementSelect();
            skillModal?.show();
            return;
        }

        if (action === 'edit-skill') {
            const skillId = button.getAttribute('data-id') || '';
            const ctx = findSkillNode(skillId);
            document.getElementById('cvSkillsSkillModalLabel').textContent = i18n.skillTitleEdit || '';
            fillSkillForm(ctx);
            skillModal?.show();
            return;
        }

        if (action === 'delete-skill') {
            if (!window.confirm(i18n.confirmDelete || '')) {
                return;
            }
            const formData = new FormData();
            formData.append('id', button.getAttribute('data-id') || '');
            try {
                const result = await postForm(routes.skillDelete, formData);
                applyCatalogUpdate(result);
            } catch (error) {
                showError(error instanceof Error ? error.message : String(error));
            }
        }
    });

    rebuildPlacementSelect();
})();
