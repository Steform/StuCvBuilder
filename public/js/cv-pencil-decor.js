/**
 * Public CV pencil decoration — anchor fixed position below the About section.
 */
(function () {
    'use strict';

    var GAP_PX = 12;

    /**
     * @brief Position the pencil decoration below the About section.
     *
     * @param {void} No input parameter.
     * @return {void}
     * @date 2026-06-08
     * @author Stephane H.
     */
    function positionCvPencilDecor() {
        var aboutSection = document.getElementById('about');
        var pencilDecor = document.querySelector('[data-cv-pencil-decor]');
        if (!aboutSection || !pencilDecor) {
            return;
        }

        var top = aboutSection.offsetTop + aboutSection.offsetHeight + GAP_PX;
        pencilDecor.style.setProperty('--cv-pencil-top', top + 'px');
    }

    /**
     * @brief Bind resize and About image load listeners.
     *
     * @param {void} No input parameter.
     * @return {void}
     * @date 2026-06-08
     * @author Stephane H.
     */
    function initCvPencilDecor() {
        var pencilDecor = document.querySelector('[data-cv-pencil-decor]');
        if (!pencilDecor) {
            return;
        }

        positionCvPencilDecor();
        window.addEventListener('resize', positionCvPencilDecor);

        var aboutSection = document.getElementById('about');
        if (!aboutSection) {
            return;
        }

        var aboutImages = aboutSection.querySelectorAll('img');
        aboutImages.forEach(function (image) {
            if (image.complete) {
                return;
            }

            image.addEventListener('load', positionCvPencilDecor, { once: true });
            image.addEventListener('error', positionCvPencilDecor, { once: true });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCvPencilDecor);
    } else {
        initCvPencilDecor();
    }
})();
