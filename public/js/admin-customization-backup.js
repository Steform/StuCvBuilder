(function () {
    'use strict';

    /**
     * @brief Copy shared reauth fields into one target form before submit.
     * @param {string} formId Target form element id.
     * @param {string} passwordFieldId Hidden password input id inside the target form.
     * @param {string} totpFieldId Hidden TOTP input id inside the target form.
     * @return {void}
     * @date 2026-06-21
     * @author Stephane H.
     */
    function bindReauthSync(formId, passwordFieldId, totpFieldId) {
        var form = document.getElementById(formId);
        var passwordSource = document.getElementById('reauth_password');
        var totpSource = document.getElementById('reauth_totp');
        var passwordTarget = document.getElementById(passwordFieldId);
        var totpTarget = document.getElementById(totpFieldId);

        if (!form || !passwordSource || !totpSource || !passwordTarget || !totpTarget) {
            return;
        }

        form.addEventListener('submit', function () {
            passwordTarget.value = passwordSource.value;
            totpTarget.value = totpSource.value;
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        bindReauthSync('restore-form', 'restore-form-reauth-password', 'restore-form-reauth-totp');
        bindReauthSync('reset-form', 'reset-form-reauth-password', 'reset-form-reauth-totp');
    });
})();
