(function () {
    'use strict';

    /**
     * @brief Read the configured maximum tag rows for one project card list.
     *
     * @param {HTMLElement} tagsList Project tags list element.
     * @return {number} Maximum visible rows (defaults to 2).
     * @date 2026-06-11
     * @author Stephane H.
     */
    function readMaxTagLines(tagsList) {
        const raw = getComputedStyle(tagsList).getPropertyValue('--cv-projects-tags-max-lines').trim();
        const parsed = Number.parseInt(raw, 10);

        return Number.isFinite(parsed) && parsed > 0 ? parsed : 2;
    }

    /**
     * @brief Resolve the vertical gap between wrapped tag rows.
     *
     * @param {CSSStyleDeclaration} styles Computed styles for the tags list.
     * @return {number} Row gap in pixels.
     * @date 2026-06-11
     * @author Stephane H.
     */
    function readRowGap(styles) {
        const raw = styles.rowGap || styles.gap || '0';
        const parsed = Number.parseFloat(raw);

        return Number.isFinite(parsed) ? parsed : 0;
    }

    /**
     * @brief Hide trailing tags until the visible ones fit the card display area.
     *
     * @param {HTMLElement} tagsList Project tags list element.
     * @return {void}
     * @date 2026-06-11
     * @author Stephane H.
     */
    function clampProjectTags(tagsList) {
        const items = Array.from(tagsList.querySelectorAll(':scope > .cv-projects__tag-item'));
        if (items.length === 0) {
            return;
        }

        items.forEach(function (item) {
            item.hidden = false;
        });

        const firstItem = items[0];
        const lineHeight = firstItem.offsetHeight;
        if (lineHeight <= 0) {
            return;
        }

        const listStyles = getComputedStyle(tagsList);
        const maxLines = readMaxTagLines(tagsList);
        const rowGap = readRowGap(listStyles);
        const maxHeight = (lineHeight * maxLines) + (rowGap * Math.max(0, maxLines - 1));

        tagsList.style.maxHeight = maxHeight + 'px';

        for (let visibleCount = items.length; visibleCount >= 0; visibleCount -= 1) {
            items.forEach(function (item, index) {
                item.hidden = index >= visibleCount;
            });

            if (tagsList.scrollHeight <= maxHeight + 1) {
                break;
            }
        }
    }

    /**
     * @brief Clamp tags for every flagship project card on the page.
     *
     * @return {void}
     * @date 2026-06-11
     * @author Stephane H.
     */
    function clampAllProjectTags() {
        document.querySelectorAll('[data-cv-projects-tags]').forEach(function (tagsList) {
            if (tagsList instanceof HTMLElement) {
                clampProjectTags(tagsList);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', clampAllProjectTags);
    } else {
        clampAllProjectTags();
    }

    window.addEventListener('resize', clampAllProjectTags);

    if (typeof document.fonts !== 'undefined' && typeof document.fonts.ready?.then === 'function') {
        document.fonts.ready.then(clampAllProjectTags).catch(function () {
            return undefined;
        });
    }
})();
