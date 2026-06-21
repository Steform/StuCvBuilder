(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var modalEl = document.getElementById('cvContactModal');
        var captchaImg = document.getElementById('cv-contact-captcha-img');
        if (!modalEl || !captchaImg) {
            return;
        }

        var captchaBaseUrl = captchaImg.getAttribute('src') || '';
        var refreshButtons = modalEl.querySelectorAll('[data-cv-captcha-refresh]');

        function refreshCaptchaImage() {
            if (captchaBaseUrl === '') {
                return;
            }
            captchaImg.src = captchaBaseUrl.split('?')[0] + '?' + String(Date.now());
        }

        refreshButtons.forEach(function (button) {
            button.addEventListener('click', refreshCaptchaImage);
        });

        modalEl.addEventListener('show.bs.modal', refreshCaptchaImage);
    });
})();
