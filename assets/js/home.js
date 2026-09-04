/* ============================================================
   PrimeCare — Homepage-specific behaviors
   (testimonials carousel, quick contact form)
   ============================================================ */

document.addEventListener('DOMContentLoaded', function () {

    /* ---------- Testimonials carousel ---------- */
    const slides = document.querySelectorAll('.testimonial-slide');
    const dots = document.querySelectorAll('.testimonial-dots button');
    let current = 0;
    let timer = null;

    function showSlide(index) {
        slides.forEach((s, i) => s.classList.toggle('active', i === index));
        dots.forEach((d, i) => d.classList.toggle('active', i === index));
        current = index;
    }

    function nextSlide() {
        showSlide((current + 1) % slides.length);
    }

    function startAutoplay() {
        timer = setInterval(nextSlide, 6000);
    }

    function stopAutoplay() {
        clearInterval(timer);
    }

    if (slides.length) {
        dots.forEach((dot, i) => {
            dot.addEventListener('click', () => {
                showSlide(i);
                stopAutoplay();
                startAutoplay();
            });
        });
        startAutoplay();
    }

    /* ---------- Quick contact form (home page) ----------
       Client-side only for now — will POST to api/contact/send.php
       once the PHP backend is built. */
    const quickForm = document.getElementById('quickContactForm');
    if (quickForm) {
        quickForm.addEventListener('submit', function (e) {
            e.preventDefault();

            const name = document.getElementById('qcName').value.trim();
            const email = document.getElementById('qcEmail').value.trim();
            const message = document.getElementById('qcMessage').value.trim();
            const msgEl = document.getElementById('quickContactMsg');

            if (!name || !email || !message) {
                msgEl.className = 'form-msg error';
                msgEl.textContent = 'Please fill in all fields.';
                return;
            }
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                msgEl.className = 'form-msg error';
                msgEl.textContent = 'Please enter a valid email address.';
                return;
            }

            msgEl.className = 'form-msg success';
            msgEl.textContent = 'Thanks, ' + name + '. We\'ll be in touch soon.';
            quickForm.reset();
        });
    }
});
