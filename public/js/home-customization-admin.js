/**
 * @brief Toggle custom quick-tile CSS field visibility on home customization admin form.
 */
(function () {
    'use strict';

    function syncQuickTileCustomCssVisibility() {
        var wrapper = document.getElementById('quick-tile-custom-css-wrapper');
        if (!wrapper) {
            return;
        }

        var selected = document.querySelector('input.js-quick-tile-style:checked');
        var isCustom = selected && selected.value === 'custom';
        wrapper.classList.toggle('d-none', !isCustom);
    }

    document.addEventListener('DOMContentLoaded', function () {
        var radios = document.querySelectorAll('input.js-quick-tile-style');
        radios.forEach(function (radio) {
            radio.addEventListener('change', syncQuickTileCustomCssVisibility);
        });
        syncQuickTileCustomCssVisibility();
    });
})();
