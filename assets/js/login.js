/**
 * EODB-TaskFlow - Login page behaviour
 * Show/hide password, Caps Lock warning, empty-field check, and
 * double-submit prevention. The server validates everything again.
 */
(function () {
    'use strict';

    var form = document.getElementById('loginForm');
    var login = document.getElementById('login');
    var password = document.getElementById('password');
    var toggle = document.getElementById('togglePassword');
    var capsWarning = document.getElementById('capsWarning');
    var loginError = document.getElementById('loginError');
    var passwordError = document.getElementById('passwordError');
    var button = document.getElementById('loginButton');

    if (!form) {
        return;
    }

    // Show / hide password
    toggle.addEventListener('click', function () {
        var show = password.type === 'password';
        password.type = show ? 'text' : 'password';
        toggle.setAttribute('aria-pressed', show ? 'true' : 'false');
        toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        toggle.innerHTML = show
            ? '<i class="fa-solid fa-eye-slash"></i>'
            : '<i class="fa-solid fa-eye"></i>';
        password.focus();
    });

    // Caps Lock warning
    function checkCaps(event) {
        if (typeof event.getModifierState === 'function') {
            capsWarning.hidden = !event.getModifierState('CapsLock');
        }
    }
    password.addEventListener('keyup', checkCaps);
    password.addEventListener('keydown', checkCaps);
    password.addEventListener('blur', function () {
        capsWarning.hidden = true;
    });

    // Client-side empty-field check
    form.addEventListener('submit', function (event) {
        var loginEmpty = login.value.trim() === '';
        var passwordEmpty = password.value === '';

        loginError.hidden = !loginEmpty;
        passwordError.hidden = !passwordEmpty;
        login.classList.toggle('is-invalid', loginEmpty);
        password.classList.toggle('is-invalid', passwordEmpty);

        if (loginEmpty || passwordEmpty) {
            event.preventDefault();
            (loginEmpty ? login : password).focus();
            return;
        }

        // Prevent double submission
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span> Signing in...';
    });
})();
