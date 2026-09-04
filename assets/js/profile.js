/* ============================================================
   PrimeCare — Profile page behaviors
   ============================================================ */

document.addEventListener('DOMContentLoaded', function () {

    function wireToggle(toggleId, inputId) {
        const toggle = document.getElementById(toggleId);
        const input = document.getElementById(inputId);
        if (!toggle || !input) return;
        toggle.addEventListener('click', () => {
            input.type = input.type === 'password' ? 'text' : 'password';
        });
    }
    wireToggle('toggleCurrentPassword', 'currentPassword');
    wireToggle('toggleNewPassword', 'newPassword');
    wireToggle('toggleConfirmNewPassword', 'confirmNewPassword');

    function showError(fieldId, show) {
        const input = document.getElementById(fieldId);
        const err = document.getElementById('err-' + fieldId);
        if (input) input.classList.toggle('invalid', show);
        if (err) err.classList.toggle('show', show);
    }

    function showBanner(el, type, message) {
        el.className = 'form-banner show ' + type;
        el.textContent = message;
    }

    /* ---------- Load current profile ---------- */
    const firstName = document.getElementById('firstName');
    const lastName = document.getElementById('lastName');
    const profileEmail = document.getElementById('profileEmail');
    const phone = document.getElementById('phone');
    const address = document.getElementById('address');
    const emergencyContactName = document.getElementById('emergencyContactName');
    const emergencyContactPhone = document.getElementById('emergencyContactPhone');

    async function loadProfile() {
        try {
            const res = await fetch('api/profile/get.php', { credentials: 'same-origin' });
            if (res.status === 401) { window.location.href = 'login.html'; return; }

            const data = await res.json();
            if (!data.success) throw new Error();

            firstName.value = data.patient.first_name || '';
            lastName.value = data.patient.last_name || '';
            profileEmail.textContent = data.patient.email || '—';
            phone.value = data.patient.phone || '';
            address.value = data.patient.address || '';
            emergencyContactName.value = data.patient.emergency_contact_name || '';
            emergencyContactPhone.value = data.patient.emergency_contact_phone || '';
        } catch (err) {
            showBanner(document.getElementById('profileBanner'), 'error', 'Could not load your profile. Please refresh the page.');
        }
    }
    loadProfile();

    /* ---------- Update profile ---------- */
    const profileForm = document.getElementById('profileForm');
    const profileBanner = document.getElementById('profileBanner');
    const profileBtn = document.getElementById('profileBtn');

    function isValidPhone(value) {
        const digits = value.replace(/[\s\-()]/g, '');
        return /^\+?\d{7,15}$/.test(digits);
    }

    profileForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        profileBanner.classList.remove('show', 'error', 'success');

        let valid = true;
        if (!firstName.value.trim()) { showError('firstName', true); valid = false; } else showError('firstName', false);
        if (!lastName.value.trim()) { showError('lastName', true); valid = false; } else showError('lastName', false);
        if (!isValidPhone(phone.value.trim())) { showError('phone', true); valid = false; } else showError('phone', false);
        if (address.value.length > 255) { showError('address', true); valid = false; } else showError('address', false);

        // Emergency contact is optional — only validate the phone format
        // if something was actually entered.
        if (emergencyContactName.value.trim().length > 100) { showError('emergencyContactName', true); valid = false; } else showError('emergencyContactName', false);
        if (emergencyContactPhone.value.trim() && !isValidPhone(emergencyContactPhone.value.trim())) { showError('emergencyContactPhone', true); valid = false; } else showError('emergencyContactPhone', false);

        if (!valid) {
            showBanner(profileBanner, 'error', 'Please correct the highlighted fields.');
            return;
        }

        profileBtn.classList.add('loading');
        profileBtn.textContent = 'Saving...';

        try {
            const res = await csrfFetch('api/profile/update.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    firstName: firstName.value.trim(),
                    lastName: lastName.value.trim(),
                    phone: phone.value.trim(),
                    address: address.value.trim(),
                    emergencyContactName: emergencyContactName.value.trim(),
                    emergencyContactPhone: emergencyContactPhone.value.trim()
                })
            });
            const data = await res.json();

            if (res.ok && data.success) {
                showBanner(profileBanner, 'success', 'Profile updated successfully.');
            } else {
                if (data.errors) {
                    Object.keys(data.errors).forEach(field => showError(field, true));
                }
                showBanner(profileBanner, 'error', data.message || 'Could not update your profile.');
            }
        } catch (err) {
            showBanner(profileBanner, 'error', 'Could not reach the server. Please try again.');
        } finally {
            profileBtn.classList.remove('loading');
            profileBtn.textContent = 'Save changes';
        }
    });

    /* ---------- Change password ---------- */
    const passwordForm = document.getElementById('passwordForm');
    const passwordBanner = document.getElementById('passwordBanner');
    const passwordBtn = document.getElementById('passwordBtn');
    const currentPassword = document.getElementById('currentPassword');
    const newPassword = document.getElementById('newPassword');
    const confirmNewPassword = document.getElementById('confirmNewPassword');

    function isValidPassword(value) {
        return value.length >= 8 && /[A-Za-z]/.test(value) && /\d/.test(value);
    }

    passwordForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        passwordBanner.classList.remove('show', 'error', 'success');

        let valid = true;
        if (!currentPassword.value) { showError('currentPassword', true); valid = false; } else showError('currentPassword', false);
        if (!isValidPassword(newPassword.value)) { showError('newPassword', true); valid = false; } else showError('newPassword', false);
        if (confirmNewPassword.value !== newPassword.value || !newPassword.value) { showError('confirmNewPassword', true); valid = false; } else showError('confirmNewPassword', false);

        if (!valid) {
            showBanner(passwordBanner, 'error', 'Please correct the highlighted fields.');
            return;
        }

        passwordBtn.classList.add('loading');
        passwordBtn.textContent = 'Changing...';

        try {
            const res = await csrfFetch('api/profile/change-password.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    currentPassword: currentPassword.value,
                    newPassword: newPassword.value
                })
            });
            const data = await res.json();

            if (res.ok && data.success) {
                showBanner(passwordBanner, 'success', 'Password changed successfully.');
                passwordForm.reset();
            } else {
                showBanner(passwordBanner, 'error', data.message || 'Could not change your password.');
            }
        } catch (err) {
            showBanner(passwordBanner, 'error', 'Could not reach the server. Please try again.');
        } finally {
            passwordBtn.classList.remove('loading');
            passwordBtn.textContent = 'Change password';
        }
    });
});
