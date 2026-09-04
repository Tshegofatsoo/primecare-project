/* ============================================================
   PrimeCare — Contact page form behavior
   Client-side only for now — will POST to api/contact/send.php
   once that endpoint is built (not yet covered in this build).
   ============================================================ */

document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('contactForm');
    const banner = document.getElementById('contactBanner');
    if (!form) return;

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        const name = document.getElementById('cName').value.trim();
        const email = document.getElementById('cEmail').value.trim();
        const message = document.getElementById('cMessage').value.trim();

        if (!name || !email || !message) {
            banner.className = 'form-banner show error';
            banner.textContent = 'Please fill in all fields.';
            return;
        }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            banner.className = 'form-banner show error';
            banner.textContent = 'Please enter a valid email address.';
            return;
        }

        banner.className = 'form-banner show success';
        banner.textContent = 'Thanks, ' + name + '. We\'ll be in touch soon.';
        form.reset();
    });
});
