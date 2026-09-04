/* ============================================================
   PrimeCare — Auth pages (Register / Login)
   Client-side validation + submission to the PHP API.
   NOTE: This validation improves UX only. It is NOT a security
   boundary — api/auth/register.php re-validates everything
   server-side, because client-side checks can always be bypassed.
   ============================================================ */

document.addEventListener('DOMContentLoaded', function () {

    /* ---------- Password show/hide toggles ---------- */
    function wireToggle(toggleId, inputId) {
        const toggle = document.getElementById(toggleId);
        const input = document.getElementById(inputId);
        if (!toggle || !input) return;
        toggle.addEventListener('click', () => {
            input.type = input.type === 'password' ? 'text' : 'password';
        });
    }
    wireToggle('togglePassword', 'password');
    wireToggle('toggleConfirmPassword', 'confirmPassword');

    /* ---------- Password strength meter ---------- */
    const pwInput = document.getElementById('password');
    const pwStrength = document.getElementById('pwStrength');
    const pwStrengthLabel = document.getElementById('pwStrengthLabel');

    function scorePassword(pw) {
        let score = 0;
        if (pw.length >= 8) score++;
        if (/[A-Z]/.test(pw) && /[a-z]/.test(pw)) score++;
        if (/\d/.test(pw)) score++;
        if (/[^A-Za-z0-9]/.test(pw)) score++;
        return score;
    }

    if (pwInput && pwStrength) {
        pwInput.addEventListener('input', () => {
            const score = scorePassword(pwInput.value);
            pwStrength.className = 'pw-strength';
            if (pwInput.value.length === 0) {
                pwStrengthLabel.textContent = 'Use 8+ characters, with a number and a letter.';
            } else if (score <= 1) {
                pwStrength.classList.add('weak');
                pwStrengthLabel.textContent = 'Weak password.';
            } else if (score <= 3) {
                pwStrength.classList.add('medium');
                pwStrengthLabel.textContent = 'Good — could be stronger.';
            } else {
                pwStrength.classList.add('strong');
                pwStrengthLabel.textContent = 'Strong password.';
            }
        });
    }

    /* ---------- Shared validation helpers ---------- */
    function showError(fieldId, show) {
        const input = document.getElementById(fieldId);
        const err = document.getElementById('err-' + fieldId);
        if (!input) return;
        input.classList.toggle('invalid', show);
        input.classList.toggle('valid', !show && input.value.trim() !== '');
        if (err) err.classList.toggle('show', show);
    }

    function showBanner(el, type, message) {
        el.className = 'form-banner show ' + type;
        el.textContent = message;
    }

    function isValidEmail(value) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
    }

    function isValidPhone(value) {
        const digits = value.replace(/[\s\-()]/g, '');
        return /^\+?\d{7,15}$/.test(digits);
    }

    function isValidPassword(value) {
        return value.length >= 8 && /[A-Za-z]/.test(value) && /\d/.test(value);
    }

    function isValidDob(value) {
        if (!value) return false;
        const dob = new Date(value);
        const today = new Date();
        if (isNaN(dob.getTime())) return false;
        if (dob > today) return false;
        const age = (today - dob) / (1000 * 60 * 60 * 24 * 365.25);
        return age >= 0 && age <= 120;
    }

    /* ---------- Registration form ---------- */
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        const fullName = document.getElementById('fullName');
        const email = document.getElementById('email');
        const phone = document.getElementById('phone');
        const dob = document.getElementById('dob');
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirmPassword');
        const banner = document.getElementById('formBanner');
        const submitBtn = document.getElementById('registerBtn');

        function validateRegisterForm() {
            let valid = true;

            const nameOk = fullName.value.trim().split(/\s+/).filter(Boolean).length >= 2;
            showError('fullName', !nameOk);
            valid = valid && nameOk;

            const emailOk = isValidEmail(email.value.trim());
            showError('email', !emailOk);
            valid = valid && emailOk;

            const phoneOk = isValidPhone(phone.value.trim());
            showError('phone', !phoneOk);
            valid = valid && phoneOk;

            const dobOk = isValidDob(dob.value);
            showError('dob', !dobOk);
            valid = valid && dobOk;

            const passwordOk = isValidPassword(password.value);
            showError('password', !passwordOk);
            valid = valid && passwordOk;

            const confirmOk = confirmPassword.value === password.value && password.value !== '';
            showError('confirmPassword', !confirmOk);
            valid = valid && confirmOk;

            return valid;
        }

        registerForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            banner.classList.remove('show', 'error', 'success');

            if (!validateRegisterForm()) {
                showBanner(banner, 'error', 'Please fix the highlighted fields before continuing.');
                return;
            }

            submitBtn.classList.add('loading');
            submitBtn.textContent = 'Creating account...';

            try {
                const response = await fetch('api/auth/register.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        fullName: fullName.value.trim(),
                        email: email.value.trim(),
                        phone: phone.value.trim(),
                        dob: dob.value,
                        password: password.value
                    })
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    showBanner(banner, 'success', 'Account created successfully. Redirecting to login...');
                    registerForm.reset();
                    setTimeout(() => { window.location.href = 'login.html'; }, 1500);
                } else {
                    // Surface field-specific errors returned by the server, if any
                    if (data.errors) {
                        Object.keys(data.errors).forEach(field => {
                            const err = document.getElementById('err-' + field);
                            const input = document.getElementById(field);
                            if (err) { err.textContent = data.errors[field]; err.classList.add('show'); }
                            if (input) input.classList.add('invalid');
                        });
                    }
                    showBanner(banner, 'error', data.message || 'Something went wrong. Please try again.');
                }
            } catch (err) {
                showBanner(banner, 'error', 'Could not reach the server. Please check your connection and try again.');
            } finally {
                submitBtn.classList.remove('loading');
                submitBtn.textContent = 'Create account';
            }
        });

        // Clear individual field errors as the user corrects them
        [fullName, email, phone, dob, password, confirmPassword].forEach(input => {
            input.addEventListener('input', () => {
                input.classList.remove('invalid');
                const err = document.getElementById('err-' + input.id);
                if (err) err.classList.remove('show');
            });
        });
    }

    /* ---------- Login form ---------- */
    wireToggle('toggleLoginPassword', 'loginPassword');

    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        const loginEmail = document.getElementById('loginEmail');
        const loginPassword = document.getElementById('loginPassword');
        const rememberMe = document.getElementById('rememberMe');
        const banner = document.getElementById('formBanner');
        const submitBtn = document.getElementById('loginBtn');

        if (new URLSearchParams(window.location.search).get('expired') === '1') {
            showBanner(banner, 'error', 'Your session expired due to inactivity. Please log in again.');
        }

        function validateLoginForm() {
            let valid = true;

            const emailOk = isValidEmail(loginEmail.value.trim());
            showError('loginEmail', !emailOk);
            valid = valid && emailOk;

            const passwordOk = loginPassword.value.length > 0;
            showError('loginPassword', !passwordOk);
            valid = valid && passwordOk;

            return valid;
        }

        loginForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            banner.classList.remove('show', 'error', 'success');

            if (!validateLoginForm()) {
                showBanner(banner, 'error', 'Please enter a valid email and password.');
                return;
            }

            submitBtn.classList.add('loading');
            submitBtn.textContent = 'Logging in...';

            try {
                const response = await fetch('api/auth/login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin', // ensures the PHP session cookie is sent/stored
                    body: JSON.stringify({
                        email: loginEmail.value.trim(),
                        password: loginPassword.value,
                        rememberMe: !!rememberMe.checked
                    })
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    showBanner(banner, 'success', 'Login successful. Redirecting...');
                    setTimeout(() => { window.location.href = 'patient-dashboard.php'; }, 1000);
                } else {
                    // Generic message on purpose — never confirm whether the
                    // email exists or the password was wrong specifically.
                    showBanner(banner, 'error', data.message || 'Invalid email or password.');
                }
            } catch (err) {
                showBanner(banner, 'error', 'Could not reach the server. Please check your connection and try again.');
            } finally {
                submitBtn.classList.remove('loading');
                submitBtn.textContent = 'Log in';
            }
        });

        [loginEmail, loginPassword].forEach(input => {
            input.addEventListener('input', () => {
                input.classList.remove('invalid');
                const err = document.getElementById('err-' + input.id);
                if (err) err.classList.remove('show');
            });
        });
    }

    /* ---------- Forgot password form ---------- */
    const forgotForm = document.getElementById('forgotPasswordForm');
    if (forgotForm) {
        const forgotEmail = document.getElementById('forgotEmail');
        const banner = document.getElementById('formBanner');
        const submitBtn = document.getElementById('forgotBtn');

        forgotForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            banner.classList.remove('show', 'error', 'success');

            const emailOk = isValidEmail(forgotEmail.value.trim());
            showError('forgotEmail', !emailOk);
            if (!emailOk) {
                showBanner(banner, 'error', 'Please enter a valid email address.');
                return;
            }

            submitBtn.classList.add('loading');
            submitBtn.textContent = 'Sending...';

            try {
                const response = await fetch('api/auth/forgot-password.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email: forgotEmail.value.trim() })
                });

                const data = await response.json();

                // Always show the same neutral message, regardless of whether
                // the account exists — prevents attackers from using this form
                // to discover which emails are registered.
                showBanner(banner, 'success', data.message || 'If that email is registered, a reset link has been sent.');
                forgotForm.reset();
            } catch (err) {
                showBanner(banner, 'error', 'Could not reach the server. Please try again.');
            } finally {
                submitBtn.classList.remove('loading');
                submitBtn.textContent = 'Send reset link';
            }
        });

        forgotEmail.addEventListener('input', () => {
            forgotEmail.classList.remove('invalid');
            const err = document.getElementById('err-forgotEmail');
            if (err) err.classList.remove('show');
        });
    }

    /* ---------- Reset password form ---------- */
    wireToggle('toggleNewPassword', 'newPassword');
    wireToggle('toggleConfirmNewPassword', 'confirmNewPassword');

    const resetForm = document.getElementById('resetPasswordForm');
    if (resetForm) {
        const newPassword = document.getElementById('newPassword');
        const confirmNewPassword = document.getElementById('confirmNewPassword');
        const banner = document.getElementById('formBanner');
        const submitBtn = document.getElementById('resetBtn');

        const params = new URLSearchParams(window.location.search);
        const token = params.get('token') || '';

        if (!token) {
            showBanner(banner, 'error', 'This reset link is missing its token. Please request a new one from the Forgot Password page.');
            resetForm.querySelectorAll('input, button').forEach(el => el.disabled = true);
        }

        resetForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            banner.classList.remove('show', 'error', 'success');

            let valid = true;
            const passwordOk = isValidPassword(newPassword.value);
            showError('newPassword', !passwordOk);
            valid = valid && passwordOk;

            const confirmOk = confirmNewPassword.value === newPassword.value && newPassword.value !== '';
            showError('confirmNewPassword', !confirmOk);
            valid = valid && confirmOk;

            if (!valid) {
                showBanner(banner, 'error', 'Please correct the highlighted fields.');
                return;
            }

            submitBtn.classList.add('loading');
            submitBtn.textContent = 'Resetting...';

            try {
                const response = await fetch('api/auth/reset-password.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ token, newPassword: newPassword.value })
                });
                const data = await response.json();

                if (response.ok && data.success) {
                    showBanner(banner, 'success', 'Password reset successfully. Redirecting to login...');
                    resetForm.reset();
                    setTimeout(() => { window.location.href = 'login.html'; }, 1500);
                } else {
                    showBanner(banner, 'error', data.message || 'Could not reset your password. Please try again.');
                }
            } catch (err) {
                showBanner(banner, 'error', 'Could not reach the server. Please try again.');
            } finally {
                submitBtn.classList.remove('loading');
                submitBtn.textContent = 'Reset password';
            }
        });

        [newPassword, confirmNewPassword].forEach(input => {
            input.addEventListener('input', () => {
                input.classList.remove('invalid');
                const err = document.getElementById('err-' + input.id);
                if (err) err.classList.remove('show');
            });
        });
    }
});
