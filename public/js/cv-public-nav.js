/**
 * Closes the mobile CV top collapse menu when a section link is activated.
 */
(function () {
    'use strict';

    var COLLAPSE_ID = 'cvPublicNavCollapse';

    /**
     * @brief Return the mobile navigation collapse element when present.
     *
     * @return {HTMLElement|null}
     * @date 2026-05-23
     * @author Stephane H.
     */
    function getCollapseElement() {
        var el = document.getElementById(COLLAPSE_ID);

        return el instanceof HTMLElement ? el : null;
    }

    /**
     * @brief Hide the mobile collapse menu if Bootstrap is available.
     *
     * @return {void}
     * @date 2026-05-23
     * @author Stephane H.
     */
    function hideCollapseMenu() {
        var el = getCollapseElement();
        if (!el || typeof bootstrap === 'undefined' || !bootstrap.Collapse) {
            return;
        }

        var instance = bootstrap.Collapse.getInstance(el);
        if (!instance) {
            instance = bootstrap.Collapse.getOrCreateInstance(el, { toggle: false });
        }

        instance.hide();
    }

    document.addEventListener('click', function (event) {
        var target = event.target;
        if (!(target instanceof Element)) {
            return;
        }

        var trigger = target.closest('[data-cv-public-nav-link]');
        if (!trigger) {
            return;
        }

        hideCollapseMenu();
    });
})();
