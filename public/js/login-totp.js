/**
 * Login TOTP step: resend code by email with cooldown timer.
 */
(function () {
    'use strict';

    var root = document.getElementById('login-totp-resend-root');
    if (!root) {
        return;
    }

    var resendBtn = document.getElementById('login-totp-resend-btn');
    var alertEl = document.getElementById('login-totp-alert');
    var resendUrl = root.getAttribute('data-endpoint-resend') || '';
    var csrfToken = root.getAttribute('data-csrf-token') || '';
    var retryAfterSeconds = parseInt(root.getAttribute('data-retry-after-seconds') || '0', 10);
    var rateLimited = root.getAttribute('data-rate-limited') === '1';
    var msgSent = root.getAttribute('data-msg-sent') || '';
    var msgCooldown = root.getAttribute('data-msg-cooldown') || '';
    var msgRateLimited = root.getAttribute('data-msg-rate-limited') || '';
    var msgFallback = root.getAttribute('data-msg-fallback') || '';
    var labelResend = root.getAttribute('data-label-resend') || '';
    var labelResendWait = root.getAttribute('data-label-resend-wait') || '';
    var countdownTimer = null;

    /**
     * @param {string} text Message for the user.
     * @param {string} kind One of: info, success, danger.
     * @return {void}
     */
    function showAlert(text, kind) {
        if (!alertEl) {
            return;
        }
        alertEl.className = 'alert';
        if (kind === 'success') {
            alertEl.classList.add('alert-success');
        } else if (kind === 'danger') {
            alertEl.classList.add('alert-danger');
        } else {
            alertEl.classList.add('alert-info');
        }
        alertEl.textContent = text;
        alertEl.classList.remove('d-none');
    }

    /**
     * @param {number} seconds Remaining cooldown seconds.
     * @return {void}
     */
    function updateResendButtonLabel(seconds) {
        if (!resendBtn) {
            return;
        }
        if (seconds > 0) {
            resendBtn.textContent = labelResendWait.replace('%seconds%', String(seconds));
            resendBtn.setAttribute('disabled', 'disabled');
            return;
        }
        resendBtn.textContent = labelResend;
        if (!rateLimited) {
            resendBtn.removeAttribute('disabled');
        }
    }

    /**
     * @param {number} seconds Cooldown duration in seconds.
     * @return {void}
     */
    function startCooldown(seconds) {
        if (countdownTimer !== null) {
            clearInterval(countdownTimer);
            countdownTimer = null;
        }
        retryAfterSeconds = Math.max(0, seconds);
        updateResendButtonLabel(retryAfterSeconds);
        if (retryAfterSeconds <= 0) {
            return;
        }
        countdownTimer = setInterval(function () {
            retryAfterSeconds -= 1;
            if (retryAfterSeconds <= 0) {
                retryAfterSeconds = 0;
                updateResendButtonLabel(0);
                if (countdownTimer !== null) {
                    clearInterval(countdownTimer);
                    countdownTimer = null;
                }
                return;
            }
            updateResendButtonLabel(retryAfterSeconds);
        }, 1000);
    }

    /**
     * @param {Response} res Fetch response.
     * @return {Promise<Record<string, unknown>>}
     */
    function parseJson(res) {
        return res.json().catch(function () {
            return {};
        });
    }

    if (rateLimited && resendBtn) {
        resendBtn.setAttribute('disabled', 'disabled');
        showAlert(msgRateLimited, 'danger');
    } else if (retryAfterSeconds > 0) {
        startCooldown(retryAfterSeconds);
    }

    if (resendBtn && resendUrl !== '') {
        resendBtn.addEventListener('click', function () {
            if (rateLimited || retryAfterSeconds > 0) {
                return;
            }
            resendBtn.setAttribute('disabled', 'disabled');
            var body = new URLSearchParams();
            body.set('_csrf_token', csrfToken);
            fetch(resendUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'Accept': 'application/json'
                },
                body: body.toString()
            })
                .then(function (res) {
                    return parseJson(res).then(function (j) {
                        return { res: res, j: j };
                    });
                })
                .then(function (pack) {
                    var payload = pack.j || {};
                    var messageKey = payload.message ? String(payload.message) : '';
                    var nextRetry = parseInt(String(payload.retryAfterSeconds || '0'), 10);
                    if (pack.res.status === 200 && payload.status === 'ok') {
                        showAlert(msgSent, 'success');
                        startCooldown(nextRetry);
                        return;
                    }
                    if (messageKey === 'auth.totp.challenge.cooldown') {
                        showAlert(msgCooldown, 'danger');
                        startCooldown(nextRetry > 0 ? nextRetry : retryAfterSeconds);
                        return;
                    }
                    if (messageKey === 'auth.totp.challenge.rate_limited') {
                        rateLimited = true;
                        showAlert(msgRateLimited, 'danger');
                        updateResendButtonLabel(0);
                        return;
                    }
                    showAlert(msgFallback, 'danger');
                    updateResendButtonLabel(0);
                })
                .catch(function () {
                    showAlert(msgFallback, 'danger');
                    updateResendButtonLabel(0);
                });
        });
    }
}());
