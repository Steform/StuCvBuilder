/**
 * Public CV education timeline — lightweight disclosure helpers.
 */
(function () {
    'use strict';

    /**
     * @brief Prevent institution links inside education disclosure summaries from toggling the details element.
     *
     * @param {Event} event Click event from a summary link.
     * @return {void}
     * @date 2026-06-09
     * @author Stephane H.
     */
    function preventSummaryLinkToggle(event) {
        event.stopPropagation();
    }

    /**
     * @brief Bind click handlers on links nested in education disclosure summaries.
     *
     * @param {Document|ParentNode} root DOM root to scan for disclosure summaries.
     * @return {void}
     * @date 2026-06-09
     * @author Stephane H.
     */
    function initCvEducationDisclosure(root) {
        var scope = root || document;
        var links = scope.querySelectorAll('.cv-education__disclosure-summary a[href]');

        links.forEach(function (link) {
            if (link.getAttribute('data-cv-education-disclosure-bound') === '1') {
                return;
            }

            link.setAttribute('data-cv-education-disclosure-bound', '1');
            link.addEventListener('click', preventSummaryLinkToggle);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initCvEducationDisclosure(document);
    });

    window.initCvEducationDisclosure = initCvEducationDisclosure;
}());
