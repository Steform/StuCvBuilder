/**
 * Site configuration mail template editors (CKEditor sync and accordion lazy init).
 */
(function () {
  'use strict';

  /**
   * @returns {HTMLElement|null}
   */
  function findActiveMailLocalePane() {
    const openTypePane = document.querySelector('#siteMailTemplateTypeTabContent .tab-pane.active');
    if (!(openTypePane instanceof HTMLElement)) {
      return null;
    }

    const activeLocalePane = openTypePane.querySelector('.tab-pane.active');
    if (activeLocalePane instanceof HTMLElement) {
      return activeLocalePane;
    }

    return openTypePane.querySelector('.tab-pane');
  }

  /**
   * @param {HTMLElement} pane Locale tab pane.
   * @returns {void}
   */
  function initMailEditorsInPane(pane) {
    if (!window.CvCkeditorBridge || typeof window.CvCkeditorBridge.initTextarea !== 'function') {
      return;
    }

    pane.querySelectorAll('textarea.ckeditor-cv-rich[data-editor-scope="mail"]').forEach(function (node) {
      if (node instanceof HTMLTextAreaElement) {
        window.CvCkeditorBridge.initTextarea(node);
      }
    });
  }

  /**
   * @returns {void}
   */
  function initActiveMailEditors() {
    const pane = findActiveMailLocalePane();
    if (pane instanceof HTMLElement) {
      initMailEditorsInPane(pane);
    }
  }

  /**
   * @returns {void}
   */
  function bindMailTemplateTypeTabs() {
    const typeTabs = document.getElementById('siteMailTemplateTypeTabs');
    if (!(typeTabs instanceof HTMLElement)) {
      return;
    }

    typeTabs.addEventListener('shown.bs.tab', function () {
      initActiveMailEditors();
    });
  }

  /**
   * @returns {void}
   */
  function bindMailTemplateLocaleTabs() {
    const panel = document.getElementById('siteMailTemplatesPanel');
    if (!(panel instanceof HTMLElement)) {
      return;
    }

    panel.addEventListener('shown.bs.tab', function (event) {
      const trigger = event.target;
      if (!(trigger instanceof HTMLElement)) {
        return;
      }

      const targetSel = trigger.getAttribute('data-bs-target');
      if (!targetSel || targetSel === '') {
        return;
      }

      const pane = document.querySelector(targetSel);
      if (pane instanceof HTMLElement) {
        initMailEditorsInPane(pane);
      }
    });
  }

  /**
   * @returns {void}
   */
  function bindMailTemplatesAccordionShown() {
    const collapse = document.getElementById('collapseSiteConfigMailTemplates');
    if (!(collapse instanceof HTMLElement)) {
      return;
    }

    collapse.addEventListener('shown.bs.collapse', function () {
      initActiveMailEditors();
    });
  }

  /**
   * @returns {void}
   */
  function bindSiteConfigurationFormSubmit() {
    const form = document.getElementById('site-configuration-form');
    if (!(form instanceof HTMLFormElement)) {
      return;
    }

    if (form.dataset.siteMailEditorsSubmitBound === '1') {
      return;
    }
    form.dataset.siteMailEditorsSubmitBound = '1';

    form.addEventListener('submit', function () {
      if (window.CvCkeditorBridge && typeof window.CvCkeditorBridge.syncAllInRoot === 'function') {
        window.CvCkeditorBridge.syncAllInRoot(form);
      }
    });
  }

  /**
   * @param {string} template
   * @param {Record<string, string>} replacements
   * @returns {string}
   */
  function applyTemplatePlaceholders(template, replacements) {
    return Object.keys(replacements).reduce(function (result, key) {
      return result.split(key).join(replacements[key]);
    }, template);
  }

  /**
   * @param {HTMLElement} panel
   * @param {string} label
   * @param {string} value
   * @returns {HTMLElement}
   */
  function buildPreviewMetaRow(panel, label, value) {
    const dt = document.createElement('dt');
    dt.className = 'col-sm-3 col-md-2 text-muted';
    dt.textContent = label;

    const dd = document.createElement('dd');
    dd.className = 'col-sm-9 col-md-10 mb-2';
    dd.textContent = value;

    const wrapper = document.createElement('div');
    wrapper.className = 'col-12';
    wrapper.appendChild(dt);
    wrapper.appendChild(dd);

    return wrapper;
  }

  /**
   * @param {HTMLElement} panel
   * @param {Record<string, string>} payload
   * @returns {void}
   */
  function renderPreviewMeta(panel, payload) {
    const meta = document.getElementById('siteMailTemplatePreviewMeta');
    if (!(meta instanceof HTMLElement)) {
      return;
    }

    meta.replaceChildren();

    const fromValue = payload.fromName
      ? payload.fromName + ' <' + payload.fromEmail + '>'
      : payload.fromEmail;

    meta.appendChild(buildPreviewMetaRow(panel, panel.dataset.previewMetaFrom || 'From', fromValue));
    if (payload.toEmail) {
      meta.appendChild(buildPreviewMetaRow(panel, panel.dataset.previewMetaTo || 'To', payload.toEmail));
    }
    meta.appendChild(buildPreviewMetaRow(panel, panel.dataset.previewMetaSubject || 'Subject', payload.subject));
  }

  /**
   * @param {string} html
   * @returns {void}
   */
  function setPreviewFrameHtml(html) {
    const frame = document.getElementById('siteMailTemplatePreviewFrame');
    if (!(frame instanceof HTMLIFrameElement)) {
      return;
    }

    frame.srcdoc = html;
  }

  /**
   * @param {HTMLElement} panel
   * @param {string} typeLabel
   * @param {string} locale
   * @returns {void}
   */
  function openPreviewModal(panel, typeLabel, locale) {
    const modalEl = document.getElementById('siteMailTemplatePreviewModal');
    if (!(modalEl instanceof HTMLElement)) {
      return;
    }

    const titleEl = document.getElementById('siteMailTemplatePreviewModalLabel');
    if (titleEl instanceof HTMLElement) {
      const titleTemplate = panel.dataset.previewModalTitle || '%type% (%locale%)';
      titleEl.textContent = applyTemplatePlaceholders(titleTemplate, {
        '%type%': typeLabel,
        '%locale%': locale.toUpperCase(),
      });
    }

    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
      bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
  }

  /**
   * @param {HTMLButtonElement} button
   * @returns {Promise<void>}
   */
  async function requestMailTemplatePreview(button) {
    const panel = document.getElementById('siteMailTemplatesPanel');
    const form = document.getElementById('site-configuration-form');
    if (!(panel instanceof HTMLElement) || !(form instanceof HTMLFormElement)) {
      return;
    }

    const previewUrl = panel.dataset.previewUrl;
    if (!previewUrl) {
      return;
    }

    const mailType = button.dataset.mailType || '';
    const mailLocale = button.dataset.mailLocale || '';
    const typeLabel = button.dataset.mailTypeLabel || mailType;
    if (mailType === '' || mailLocale === '') {
      return;
    }

    if (window.CvCkeditorBridge && typeof window.CvCkeditorBridge.syncAllInRoot === 'function') {
      window.CvCkeditorBridge.syncAllInRoot(form);
    }

    const loadingText = panel.dataset.previewLoading || 'Loading…';
    const errorText = panel.dataset.previewError || 'Preview failed.';
    const originalLabel = button.textContent;

    button.disabled = true;
    button.textContent = loadingText;
    openPreviewModal(panel, typeLabel, mailLocale);
    setPreviewFrameHtml('<p class="p-3 text-muted">' + loadingText + '</p>');

    const formData = new FormData(form);
    formData.set('mail_template_preview_type', mailType);
    formData.set('mail_template_preview_locale', mailLocale);

    try {
      const response = await fetch(previewUrl, {
        method: 'POST',
        body: formData,
        headers: {
          Accept: 'application/json',
        },
        credentials: 'same-origin',
      });

      const payload = await response.json();
      if (!response.ok) {
        throw new Error(typeof payload.error === 'string' ? payload.error : errorText);
      }

      renderPreviewMeta(panel, payload);
      setPreviewFrameHtml(typeof payload.html === 'string' ? payload.html : '');
    } catch (error) {
      const message = error instanceof Error ? error.message : errorText;
      setPreviewFrameHtml('<p class="p-3 text-danger">' + message + '</p>');
    } finally {
      button.disabled = false;
      button.textContent = originalLabel;
    }
  }

  /**
   * @returns {void}
   */
  function bindMailTemplatePreviewButtons() {
    const panel = document.getElementById('siteMailTemplatesPanel');
    if (!(panel instanceof HTMLElement)) {
      return;
    }

    if (panel.dataset.previewBound === '1') {
      return;
    }
    panel.dataset.previewBound = '1';

    panel.addEventListener('click', function (event) {
      const target = event.target;
      if (!(target instanceof HTMLElement)) {
        return;
      }

      const button = target.closest('.site-mail-template-preview-btn');
      if (!(button instanceof HTMLButtonElement)) {
        return;
      }

      event.preventDefault();
      requestMailTemplatePreview(button);
    });
  }

  /**
   * @returns {void}
   */
  function initSiteConfigurationMailEditors() {
    bindMailTemplateTypeTabs();
    bindMailTemplateLocaleTabs();
    bindMailTemplatesAccordionShown();
    bindSiteConfigurationFormSubmit();
    bindMailTemplatePreviewButtons();
    initActiveMailEditors();
  }

  document.addEventListener('DOMContentLoaded', initSiteConfigurationMailEditors);
  document.addEventListener('turbo:load', initSiteConfigurationMailEditors);
})();
