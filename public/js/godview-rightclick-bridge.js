(function () {
    'use strict';

    /**
     * @param {HTMLElement} menu Dropdown menu element.
     * @return {void}
     */
    function clearInlineMenuStyles(menu) {
        menu.style.left = '';
        menu.style.top = '';
        menu.style.right = '';
        menu.style.bottom = '';
        menu.style.transform = '';
        menu.style.position = '';
        menu.style.inset = '';
        menu.style.margin = '';
    }

    /**
     * @param {HTMLElement} menu Dropdown menu to close.
     * @return {void}
     */
    function closeContextMenu(menu) {
        menu.classList.remove('files-row-context-menu', 'show');
        clearInlineMenuStyles(menu);
        var labelledBy = menu.getAttribute('aria-labelledby') || '';
        if (labelledBy !== '') {
            var linkedToggle = document.getElementById(labelledBy);
            if (linkedToggle) {
                linkedToggle.setAttribute('aria-expanded', 'false');
            }
        }
    }

    /**
     * @param {HTMLElement} toggle Row action toggle element.
     * @param {number} clickX Cursor X coordinate.
     * @param {number} clickY Cursor Y coordinate.
     * @return {boolean}
     */
    function openContextMenuAt(toggle, clickX, clickY) {
        var labelledBy = toggle.getAttribute('id') || '';
        if (labelledBy === '') {
            return false;
        }
        var menu = document.querySelector('[aria-labelledby="' + labelledBy + '"]');
        if (!menu) {
            return false;
        }
        var openedMenus = document.querySelectorAll('.dropdown-menu.files-row-context-menu.show');
        openedMenus.forEach(function (opened) {
            closeContextMenu(opened);
        });
        clearInlineMenuStyles(menu);
        menu.classList.add('files-row-context-menu', 'show');
        menu.style.left = clickX + 'px';
        menu.style.top = clickY + 'px';
        toggle.setAttribute('aria-expanded', 'true');
        return true;
    }

    document.addEventListener('contextmenu', function (event) {
        var row = event.target && event.target.closest
            ? event.target.closest('tr[data-files-owner-group-row="1"]')
            : null;
        if (!row) {
            return;
        }
        var toggle = row.querySelector(
            '[id^="files-actions-"],[id^="files-folder-actions-"],[id^="files-shared-folder-actions-"]'
        );
        if (!toggle) {
            return;
        }
        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();

        if (openContextMenuAt(toggle, event.clientX, event.clientY)) {
            return;
        }

        toggle.click();
    }, true);
})();
