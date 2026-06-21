/**
 * Centralized CKEditor 5 (Classic build) bootstrap for dashboard rich-text fields.
 * Targets: textarea.ckeditor-cv-rich[data-editor-scope="cv"]
 */
(function () {
  'use strict';

  /** @type {WeakMap<HTMLTextAreaElement, object>} */
  const editorByTextarea = new WeakMap();

  /**
   * @param {string} html
   * @param {string} tagName
   * @returns {number}
   */
  function countTags(html, tagName) {
    const parser = new DOMParser();
    const doc = parser.parseFromString(html || '', 'text/html');

    return doc.querySelectorAll(tagName).length;
  }

  /**
   * @param {HTMLTextAreaElement} textarea
   * @returns {HTMLElement}
   */
  function ensureWarningElement(textarea) {
    const existing = textarea.parentElement?.querySelector('.ckeditor-cv-h1-guard-notice');
    if (existing instanceof HTMLElement) {
      return existing;
    }

    const node = document.createElement('div');
    node.className = 'ckeditor-cv-h1-guard-notice text-danger small mt-1 d-none';
    node.textContent = textarea.dataset.h1Warning || '';
    textarea.insertAdjacentElement('afterend', node);

    return node;
  }

  /**
   * @param {HTMLTextAreaElement} textarea
   * @returns {void}
   */
  function hideWarning(textarea) {
    const notice = textarea.parentElement?.querySelector('.ckeditor-cv-h1-guard-notice');
    if (notice instanceof HTMLElement) {
      notice.classList.add('d-none');
    }
  }

  /**
   * @param {HTMLTextAreaElement} textarea
   * @returns {void}
   */
  function showWarning(textarea) {
    const notice = ensureWarningElement(textarea);
    notice.classList.remove('d-none');
  }

  /**
   * @returns {string} Primary UI language code for CKEditor (fr, de, lt, no, or en).
   */
  function resolveCkeditorUiLanguage() {
    const raw = document.documentElement.getAttribute('lang') || 'en';
    const primary = raw.replace('_', '-').split('-')[0].toLowerCase();
    if (primary === 'fr' || primary === 'de' || primary === 'lt' || primary === 'no') {
      return primary;
    }

    return 'en';
  }

  /**
   * @returns {Record<string, unknown>}
   */
  function buildConfig() {
    /** @type {Record<string, unknown>} */
    const cfg = {
      toolbar: [
        'heading',
        '|',
        'bold',
        'italic',
        'link',
        '|',
        'fontColor',
        'fontBackgroundColor',
        '|',
        'bulletedList',
        'numberedList',
        '|',
        'undo',
        'redo',
      ],
      heading: {
        options: [
          { model: 'paragraph', view: 'p', title: 'Paragraph', class: 'ck-heading_paragraph' },
          { model: 'heading1', view: 'h1', title: 'H1', class: 'ck-heading_heading1' },
          { model: 'heading2', view: 'h2', title: 'H2', class: 'ck-heading_heading2' },
          { model: 'heading3', view: 'h3', title: 'H3', class: 'ck-heading_heading3' },
          { model: 'heading4', view: 'h4', title: 'H4', class: 'ck-heading_heading4' },
          { model: 'heading5', view: 'h5', title: 'H5', class: 'ck-heading_heading5' },
          { model: 'heading6', view: 'h6', title: 'H6', class: 'ck-heading_heading6' },
        ],
      },
    };
    const lang = resolveCkeditorUiLanguage();
    if (lang !== 'en') {
      cfg.language = lang;
    }

    return cfg;
  }

  /**
   * @param {HTMLTextAreaElement} textarea
   * @returns {Record<string, unknown>|null}
   */
  function readCvPlaceholderUiSpec(textarea) {
    let el = textarea.parentElement;
    for (let i = 0; i < 24 && el; i += 1, el = el.parentElement) {
      const raw = el.getAttribute?.('data-cv-placeholder-ui');
      if (typeof raw === 'string' && raw.trim() !== '') {
        try {
          const parsed = JSON.parse(raw);
          return parsed && typeof parsed === 'object' ? /** @type {Record<string, unknown>} */ (parsed) : null;
        } catch {
          return null;
        }
      }
    }

    return null;
  }

  /**
   * @param {*} editor CKEditor Classic instance.
   * @param {HTMLTextAreaElement} textarea
   * @param {Record<string, unknown>} uiSpec Server-provided labels and token list (no user-facing literals in this file).
   * @returns {void}
   */
  function mountCvPlaceholderInsertUi(editor, textarea, uiSpec) {
    if (textarea.getAttribute('data-editor-scope') !== 'cv') {
      return;
    }
    const tokensRaw = uiSpec.tokens;
    if (!Array.isArray(tokensRaw) || tokensRaw.length === 0) {
      return;
    }
    const root = editor.ui?.view?.element;
    if (!(root instanceof HTMLElement)) {
      return;
    }
    if (root.querySelector('.cv-ckeditor-placeholder-toolbar')) {
      return;
    }

    const wrap = document.createElement('div');
    wrap.className = 'cv-ckeditor-placeholder-toolbar d-flex flex-wrap align-items-center gap-2 mt-2';

    const dd = document.createElement('div');
    dd.className = 'dropdown';

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn-outline-secondary btn-sm dropdown-toggle';
    btn.textContent = String(uiSpec.pickerLabel ?? '');
    btn.setAttribute('data-bs-toggle', 'dropdown');
    btn.setAttribute('aria-expanded', 'false');
    const menuAria = uiSpec.menuAria;
    if (typeof menuAria === 'string' && menuAria !== '') {
      btn.setAttribute('aria-label', menuAria);
    }

    const menu = document.createElement('ul');
    menu.className = 'dropdown-menu';

    tokensRaw.forEach((entry) => {
      if (!entry || typeof entry !== 'object') {
        return;
      }
      const token = /** @type {{ insert?: string, label?: string }} */ (entry);
      const insert = typeof token.insert === 'string' ? token.insert : '';
      if (insert === '') {
        return;
      }
      const li = document.createElement('li');
      const a = document.createElement('a');
      a.href = '#';
      a.className = 'dropdown-item';
      a.textContent = typeof token.label === 'string' && token.label !== '' ? token.label : insert;
      a.addEventListener('click', (ev) => {
        ev.preventDefault();
        editor.model.change((writer) => {
          const pos = editor.model.document.selection.getFirstPosition();
          writer.insertText(insert, pos);
        });
        if (typeof window.bootstrap !== 'undefined' && window.bootstrap.Dropdown) {
          const inst = window.bootstrap.Dropdown.getOrCreateInstance(btn);
          inst.hide();
        }
      });
      li.appendChild(a);
      menu.appendChild(li);
    });

    if (menu.childElementCount === 0) {
      return;
    }

    dd.appendChild(btn);
    dd.appendChild(menu);
    wrap.appendChild(dd);
    root.appendChild(wrap);
  }

  /**
   * @param {*} editor CKEditor Classic instance.
   * @param {HTMLTextAreaElement} textarea Bound textarea.
   * @returns {void}
   */
  function bindSingleH1Guard(editor, textarea) {
    editor.model.document.on('change:data', () => {
      const html = editor.getData();
      if (countTags(html, 'h1') > 1) {
        showWarning(textarea);
      } else {
        hideWarning(textarea);
      }
    });
  }

  /**
   * @param {HTMLTextAreaElement} textarea
   * @returns {void}
   */
  function initCkeditorForTextarea(textarea) {
    if (!(textarea instanceof HTMLTextAreaElement)) {
      return;
    }
    if (textarea.dataset.ckeditorReady === '1' || textarea.dataset.ckeditorPending === '1') {
      return;
    }
    const editorScope = textarea.getAttribute('data-editor-scope');
    if (editorScope !== 'cv' && editorScope !== 'mail') {
      return;
    }
    if (typeof window.ClassicEditor === 'undefined') {
      return;
    }

    textarea.dataset.ckeditorPending = '1';

    window.ClassicEditor.create(textarea, buildConfig())
      .then((editor) => {
        delete textarea.dataset.ckeditorPending;
        textarea.dataset.ckeditorReady = '1';
        editorByTextarea.set(textarea, editor);
        bindSingleH1Guard(editor, textarea);
        const uiSpec = readCvPlaceholderUiSpec(textarea);
        if (uiSpec) {
          mountCvPlaceholderInsertUi(editor, textarea, uiSpec);
        }
      })
      .catch(function () {
        delete textarea.dataset.ckeditorPending;
      });
  }

  /**
   * @param {HTMLTextAreaElement} textarea
   * @returns {object|null}
   */
  function resolveEditorForTextarea(textarea) {
    const stored = editorByTextarea.get(textarea);
    if (stored && typeof stored.getData === 'function') {
      return stored;
    }

    return null;
  }

  /**
   * @param {HTMLTextAreaElement} textarea
   * @returns {void}
   */
  function syncTextareaFromEditor(textarea) {
    const editor = resolveEditorForTextarea(textarea);
    if (editor && typeof editor.updateSourceElement === 'function') {
      editor.updateSourceElement();
    }
  }

  /**
   * @param {HTMLElement|null|undefined} root
   * @returns {void}
   */
  function syncAllEditorsInRoot(root) {
    const scope = root instanceof HTMLElement ? root : document;
    scope.querySelectorAll('textarea.ckeditor-cv-rich[data-editor-scope="cv"], textarea.ckeditor-cv-rich[data-editor-scope="mail"]').forEach(function (node) {
      if (node instanceof HTMLTextAreaElement) {
        syncTextareaFromEditor(node);
      }
    });
  }

  /**
   * @param {HTMLTextAreaElement} textarea
   * @returns {string}
   */
  function readTextareaHtml(textarea) {
    const editor = resolveEditorForTextarea(textarea);
    if (editor && typeof editor.getData === 'function') {
      return editor.getData();
    }

    return textarea.value;
  }

  /**
   * @param {HTMLTextAreaElement} textarea
   * @param {string} html
   * @returns {void}
   */
  function writeTextareaHtml(textarea, html) {
    const editor = resolveEditorForTextarea(textarea);
    if (editor && typeof editor.setData === 'function') {
      editor.setData(html || '');
      return;
    }

    textarea.value = html || '';
  }

  /**
   * @param {HTMLTextAreaElement} textarea
   * @returns {void}
   */
  function destroyCkeditorForTextarea(textarea) {
    syncTextareaFromEditor(textarea);
    const editor = resolveEditorForTextarea(textarea);
    if (editor && typeof editor.destroy === 'function') {
      editor.destroy().catch(function () {});
    }

    editorByTextarea.delete(textarea);
    delete textarea.dataset.ckeditorReady;
    delete textarea.dataset.ckeditorPending;
  }

  /**
   * @param {HTMLElement|null|undefined} root
   * @returns {void}
   */
  function destroyAllEditorsInRoot(root) {
    if (!(root instanceof HTMLElement)) {
      return;
    }

    root.querySelectorAll('textarea.ckeditor-cv-rich[data-editor-scope="cv"], textarea.ckeditor-cv-rich[data-editor-scope="mail"]').forEach(function (node) {
      if (node instanceof HTMLTextAreaElement && node.dataset.ckeditorReady === '1') {
        destroyCkeditorForTextarea(node);
      }
    });
  }

  /**
   * @param {string} tabContentId DOM id of the locale tab content container.
   * @returns {HTMLTextAreaElement|null}
   */
  function findActiveLocaleTabTextarea(tabContentId) {
    const ta = document.querySelector(
      '#' + tabContentId + ' .tab-pane.active textarea.ckeditor-cv-rich[data-editor-scope="cv"]'
    );

    return ta instanceof HTMLTextAreaElement ? ta : null;
  }

  /**
   * @returns {HTMLTextAreaElement|null}
   */
  function findActiveAboutPresentationTextarea() {
    return findActiveLocaleTabTextarea('cvAboutPresentationLocaleTabContent');
  }

  /**
   * @returns {HTMLTextAreaElement|null}
   */
  function findActiveCvDataTaglineTextarea() {
    return findActiveLocaleTabTextarea('cvDataLocaleTabContent');
  }

  /**
   * @returns {void}
   */
  function initActiveAboutPresentationTextarea() {
    const ta = findActiveAboutPresentationTextarea();
    if (ta) {
      initCkeditorForTextarea(ta);
    }
  }

  /**
   * @returns {void}
   */
  function initActiveCvDataTaglineTextarea() {
    const ta = findActiveCvDataTaglineTextarea();
    if (ta) {
      initCkeditorForTextarea(ta);
    }
  }

  /**
   * @param {string} tabsId Locale tab list element id.
   * @param {string} tabContentId Locale tab panes container id.
   * @param {string} boundDatasetKey Dataset flag key to avoid duplicate listeners.
   * @returns {boolean} True when the tab list exists.
   */
  function bindLocaleTabEditors(tabsId, tabContentId, boundDatasetKey) {
    const localeTabs = document.getElementById(tabsId);
    if (!(localeTabs instanceof HTMLElement)) {
      return false;
    }

    if (localeTabs.dataset[boundDatasetKey] !== '1') {
      localeTabs.dataset[boundDatasetKey] = '1';
      localeTabs.addEventListener('shown.bs.tab', function (event) {
        const trigger = event.target;
        if (!(trigger instanceof HTMLElement)) {
          return;
        }
        const targetSel = trigger.getAttribute('data-bs-target');
        if (!targetSel || targetSel === '') {
          return;
        }
        const pane = document.querySelector(targetSel);
        if (!(pane instanceof HTMLElement)) {
          return;
        }
        const ta = pane.querySelector('textarea.ckeditor-cv-rich[data-editor-scope="cv"]');
        if (ta instanceof HTMLTextAreaElement) {
          initCkeditorForTextarea(ta);
        }
      });
    }

    const activeTa = findActiveLocaleTabTextarea(tabContentId);
    if (activeTa) {
      initCkeditorForTextarea(activeTa);
    }

    return true;
  }

  /**
   * @returns {void}
   */
  function bindAboutPresentationAccordionShown() {
    const collapse = document.getElementById('collapseAboutAccordionPresentation');
    if (!(collapse instanceof HTMLElement)) {
      return;
    }
    if (collapse.dataset.cvPresentationAccordionBound === '1') {
      return;
    }
    collapse.dataset.cvPresentationAccordionBound = '1';
    collapse.addEventListener('shown.bs.collapse', function () {
      initActiveAboutPresentationTextarea();
    });
  }

  /**
   * @returns {void}
   */
  function initAboutPresentationLocaleEditors() {
    bindAboutPresentationAccordionShown();
    bindLocaleTabEditors(
      'cvAboutPresentationLocaleTabs',
      'cvAboutPresentationLocaleTabContent',
      'cvPresentationLocaleTabsBound'
    );
  }

  /**
   * @returns {void}
   */
  function initCvDataLocaleEditors() {
    bindLocaleTabEditors('cvDataLocaleTabs', 'cvDataLocaleTabContent', 'cvDataLocaleTabsBound');
  }

  /**
   * @returns {void}
   */
  function bindCvCustomizationMainTabs() {
    const mainTabs = document.getElementById('cvCustomizationTabs');
    if (!(mainTabs instanceof HTMLElement)) {
      return;
    }
    if (mainTabs.dataset.cvCustomizationTabsBound === '1') {
      return;
    }
    mainTabs.dataset.cvCustomizationTabsBound = '1';
    mainTabs.addEventListener('shown.bs.tab', function (event) {
      const trigger = event.target;
      if (!(trigger instanceof HTMLElement)) {
        return;
      }
      const targetSel = trigger.getAttribute('data-bs-target');
      if (targetSel === '#cv-custom-pane-cv-data') {
        initActiveCvDataTaglineTextarea();
      } else if (targetSel === '#cv-custom-pane-about') {
        initActiveAboutPresentationTextarea();
      } else if (targetSel === '#cv-custom-pane-experience') {
        initActiveExperienceEntryTextarea();
      }
    });
  }

  /**
   * @returns {void}
   */
  function findActiveExperienceEntryLocalePane() {
    const openCollapse = document.querySelector(
      '#cvExperienceEntriesAccordion .accordion-collapse.show'
    );
    if (openCollapse instanceof HTMLElement) {
      const activePane = openCollapse.querySelector('[data-cv-experience-entry-locale-pane].active');
      if (activePane instanceof HTMLElement) {
        return activePane;
      }

      const firstPane = openCollapse.querySelector('[data-cv-experience-entry-locale-pane]');
      if (firstPane instanceof HTMLElement) {
        return firstPane;
      }
    }

    return document.querySelector('[data-cv-experience-entry-locale-pane].active');
  }

  /**
   * @returns {void}
   */
  function initActiveExperienceEntryTextarea() {
    const activePane = findActiveExperienceEntryLocalePane();
    if (activePane instanceof HTMLElement) {
      initExperienceDetailEditorsInPane(activePane);
    }
  }

  /**
   * @returns {HTMLTextAreaElement|null}
   */
  function findActiveExperienceModalTextarea() {
    const pane = document.querySelector(
      '#cvExperienceModalLocaleTabContent .tab-pane.active textarea[data-cv-experience-modal-detail-html]'
    );

    return pane instanceof HTMLTextAreaElement ? pane : null;
  }

  /**
   * @returns {void}
   */
  function initActiveExperienceModalTextarea() {
    const ta = findActiveExperienceModalTextarea();
    if (ta) {
      initCkeditorForTextarea(ta);
    }
  }

  /**
   * @param {HTMLElement} pane Locale tab pane containing experience entries.
   * @returns {void}
   */
  function initExperienceDetailEditorsInPane(pane) {
    pane.querySelectorAll('textarea[data-cv-experience-detail-html]').forEach(function (node) {
      if (node instanceof HTMLTextAreaElement) {
        initCkeditorForTextarea(node);
      }
    });
  }

  /**
   * @returns {void}
   */
  function bindExperienceEntryLocaleEditors() {
    if (document.body.dataset.cvExperienceEntryLocaleEditorsBound === '1') {
      initActiveExperienceEntryTextarea();

      return;
    }

    document.body.dataset.cvExperienceEntryLocaleEditorsBound = '1';
    document.addEventListener('shown.bs.tab', function (event) {
      const trigger = event.target;
      if (!(trigger instanceof HTMLElement)) {
        return;
      }

      if (!trigger.hasAttribute('data-cv-experience-entry-locale-tab')) {
        return;
      }

      const targetSel = trigger.getAttribute('data-bs-target');
      if (!targetSel || targetSel === '') {
        return;
      }

      const pane = document.querySelector(targetSel);
      if (pane instanceof HTMLElement) {
        initExperienceDetailEditorsInPane(pane);
      }
    });
    document.addEventListener('hidden.bs.tab', function (event) {
      const trigger = event.target;
      if (!(trigger instanceof HTMLElement)) {
        return;
      }

      if (!trigger.hasAttribute('data-cv-experience-entry-locale-tab')) {
        return;
      }

      const targetSel = trigger.getAttribute('data-bs-target');
      if (!targetSel || targetSel === '') {
        return;
      }

      const pane = document.querySelector(targetSel);
      if (pane instanceof HTMLElement) {
        destroyAllEditorsInRoot(pane);
      }
    });

    initActiveExperienceEntryTextarea();
  }

  /**
   * @returns {void}
   */
  function bindExperienceProfessionalEntriesAccordionShown() {
    const collapse = document.getElementById('collapseExperienceAccordionProfessionalEntries');
    if (!(collapse instanceof HTMLElement)) {
      return;
    }
    if (collapse.dataset.cvExperienceEntriesAccordionBound === '1') {
      return;
    }
    collapse.dataset.cvExperienceEntriesAccordionBound = '1';
    collapse.addEventListener('shown.bs.collapse', function () {
      initActiveExperienceEntryTextarea();
    });
  }

  /**
   * @returns {void}
   */
  function bindExperienceModalEditors() {
    const modal = document.getElementById('cvExperienceAddModal');
    if (!(modal instanceof HTMLElement)) {
      return;
    }

    bindLocaleTabEditors(
      'cvExperienceModalLocaleTabs',
      'cvExperienceModalLocaleTabContent',
      'cvExperienceModalLocaleTabsBound'
    );

    if (modal.dataset.cvExperienceModalEditorsBound === '1') {
      return;
    }
    modal.dataset.cvExperienceModalEditorsBound = '1';

    modal.addEventListener('shown.bs.modal', function () {
      initActiveExperienceModalTextarea();
    });
    modal.addEventListener('hidden.bs.modal', function () {
      destroyAllEditorsInRoot(modal);
    });
  }

  /**
   * @returns {void}
   */
  function bindExperienceFormSubmitSync() {
    document.querySelectorAll('.cv-experience-customization__form').forEach(function (form) {
      if (!(form instanceof HTMLFormElement)) {
        return;
      }
      if (form.dataset.cvExperienceCkeditorSubmitBound === '1') {
        return;
      }
      form.dataset.cvExperienceCkeditorSubmitBound = '1';
      form.addEventListener('submit', function () {
        syncAllEditorsInRoot(form);
      });
    });
  }

  /**
   * @returns {void}
   */
  function initExperienceEditors() {
    bindExperienceProfessionalEntriesAccordionShown();
    bindExperienceEntryLocaleEditors();
    bindExperienceModalEditors();
    bindExperienceFormSubmitSync();
  }

  window.CvCkeditorBridge = {
    initTextarea: initCkeditorForTextarea,
    syncTextarea: syncTextareaFromEditor,
    syncAllInRoot: syncAllEditorsInRoot,
    getHtml: readTextareaHtml,
    setHtml: writeTextareaHtml,
    destroyTextarea: destroyCkeditorForTextarea,
    destroyAllInRoot: destroyAllEditorsInRoot,
  };

  /**
   * @returns {void}
   */
  function initCkeditors() {
    bindCvCustomizationMainTabs();
    initAboutPresentationLocaleEditors();
    initCvDataLocaleEditors();
    initExperienceEditors();
  }

  document.addEventListener('DOMContentLoaded', initCkeditors);
  document.addEventListener('turbo:load', initCkeditors);
})();
