(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var refreshButtons = document.querySelectorAll('[data-cv-captcha-refresh]');
        var captchaImg = document.getElementById('captcha-img');
        if (!captchaImg || refreshButtons.length === 0) {
            return;
        }

        var captchaUrl = captchaImg.getAttribute('src') || '';

        refreshButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                captchaImg.src = captchaUrl.split('?')[0] + '?' + String(Date.now());
            });
        });
    });
})();
