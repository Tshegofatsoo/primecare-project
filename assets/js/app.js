/* ============================================================
   PrimeCare — Shared behaviors used across every page
   (mobile navigation, sticky header shadow, scroll-reveal,
   toast notifications, confirm modal)
   ============================================================ */

/* ---------- Toast notifications ----------
   Global, reusable, professional-card-style replacement for
   window.alert(). Call as:
     showToast('success' | 'error' | 'warning', 'Title', 'Optional message')
   ---------------------------------------------------------- */
function ensureToastContainer() {
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'toast-container';
        document.body.appendChild(container);
    }
    return container;
}

const TOAST_ICONS = {
    success: '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4L12 14.01l-3-3"/>',
    error: '<circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/>',
    warning: '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4M12 17h.01"/>'
};

window.showToast = function (type, title, message, duration = 5000) {
    const container = ensureToastContainer();
    const toast = document.createElement('div');
    toast.className = 'toast toast-' + type;
    toast.setAttribute('role', 'status');

    toast.innerHTML = `
        <div class="toast-icon"><svg class="icon" viewBox="0 0 24 24">${TOAST_ICONS[type] || TOAST_ICONS.success}</svg></div>
        <div class="toast-body">
            <div class="toast-title"></div>
            ${message ? '<div class="toast-message"></div>' : ''}
        </div>
        <button class="toast-close" type="button" aria-label="Dismiss notification">
            <svg class="icon" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
        </button>`;

    // Set text via textContent, not innerHTML, so message content is never
    // interpreted as markup — consistent with the rest of the site's XSS-safe rendering.
    toast.querySelector('.toast-title').textContent = title;
    if (message) toast.querySelector('.toast-message').textContent = message;

    container.appendChild(toast);
    requestAnimationFrame(() => toast.classList.add('show'));

    const dismiss = () => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 250);
    };

    toast.querySelector('.toast-close').addEventListener('click', dismiss);
    if (duration) setTimeout(dismiss, duration);
};

/* ---------- Confirm modal ----------
   Global, reusable replacement for window.confirm(). Call as:
     showConfirmModal('Title', 'Message', onConfirmCallback, 'Confirm label')
   ---------------------------------------------------------- */
window.showConfirmModal = function (title, message, onConfirm, confirmLabel = 'Confirm') {
    const existing = document.getElementById('globalConfirmModal');
    if (existing) existing.remove();

    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay open';
    overlay.id = 'globalConfirmModal';
    overlay.innerHTML = `
        <div class="card modal-panel">
            <div class="modal-header"><h3 class="confirm-title"></h3></div>
            <p class="confirm-message" style="font-size:14px;color:var(--text-grey);"></p>
            <div class="modal-actions">
                <button class="btn btn-secondary" type="button" id="globalConfirmCancel">Cancel</button>
                <button class="btn btn-primary" type="button" id="globalConfirmOk"></button>
            </div>
        </div>`;

    overlay.querySelector('.confirm-title').textContent = title;
    overlay.querySelector('.confirm-message').textContent = message;
    overlay.querySelector('#globalConfirmOk').textContent = confirmLabel;

    document.body.appendChild(overlay);

    const close = () => overlay.remove();
    overlay.querySelector('#globalConfirmCancel').addEventListener('click', close);
    overlay.addEventListener('click', (e) => { if (e.target === overlay) close(); });
    overlay.querySelector('#globalConfirmOk').addEventListener('click', () => {
        close();
        onConfirm();
    });
};

/* ---------- CSRF token ----------
   Read from the <meta name="csrf-token"> tag that protected pages
   embed in <head> (see includes/dash-header.php usage). Empty on
   public pages that have no session yet (index, login, register).
   ---------------------------------------------------------- */
const csrfMeta = document.querySelector('meta[name="csrf-token"]');
window.csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

/**
 * Drop-in replacement for fetch() that automatically attaches the
 * CSRF token header on POST requests. Use this for every
 * state-changing request (booking, cancelling, updating, etc.);
 * plain fetch() is still fine for read-only GET requests.
 */
window.csrfFetch = function (url, options = {}) {
    options.credentials = options.credentials || 'same-origin';
    const method = (options.method || 'GET').toUpperCase();
    if (method === 'POST') {
        options.headers = Object.assign({}, options.headers, { 'X-CSRF-Token': window.csrfToken });
    }
    return fetch(url, options);
};

document.addEventListener('DOMContentLoaded', function () {

    /* ---------- Mobile nav toggle ---------- */
    const navToggle = document.getElementById('navToggle');
    const mainNav = document.getElementById('mainNav');

    if (navToggle && mainNav) {
        navToggle.addEventListener('click', function () {
            const isOpen = mainNav.classList.toggle('open');
            navToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });

        mainNav.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => mainNav.classList.remove('open'));
        });
    }

    /* ---------- Sticky header shadow on scroll ---------- */
    const header = document.getElementById('siteHeader');
    if (header) {
        const onScroll = () => {
            header.classList.toggle('scrolled', window.scrollY > 8);
        };
        window.addEventListener('scroll', onScroll);
        onScroll();
    }

    /* ---------- Logout (shared across every protected page) ---------- */
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', async () => {
            logoutBtn.disabled = true;
            try {
                await csrfFetch('api/auth/logout.php', { method: 'POST' });
            } finally {
                window.location.href = 'login.html';
            }
        });
    }

    /* ---------- Scroll-reveal animation ---------- */
    const revealEls = document.querySelectorAll('.reveal');
    if (revealEls.length && 'IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.15 });

        revealEls.forEach(el => observer.observe(el));
    } else {
        revealEls.forEach(el => el.classList.add('visible'));
    }
});
