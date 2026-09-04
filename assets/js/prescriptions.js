/* ============================================================
   PrimeCare — My Prescriptions page behaviors
   Read-only prescription list + Request Repeat Prescription.
   Requesting a repeat only ever creates a pending request row —
   see api/prescriptions/request-repeat.php. It never issues
   medication automatically; a doctor must approve it via the
   Android app before it becomes Ready.
   ============================================================ */

document.addEventListener('DOMContentLoaded', function () {

    const card = document.getElementById('prescriptionsCard');
    const pillClass = { Active: 'pill-booked', Completed: 'pill-completed', Expired: 'pill-cancelled' };

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str ?? '';
        return div.innerHTML;
    }

    function formatDate(dateStr) {
        if (!dateStr) return '—';
        const d = new Date(dateStr + 'T00:00:00');
        return d.toLocaleDateString('en-ZA', { day: 'numeric', month: 'long', year: 'numeric' });
    }

    function render(prescriptions) {
        if (!prescriptions.length) {
            card.innerHTML = `
                <div class="empty-state">
                    <div class="icon-box">
                        <svg class="icon" viewBox="0 0 24 24"><rect x="3" y="9" width="18" height="6" rx="3" transform="rotate(-45 12 12)"/></svg>
                    </div>
                    <p>You have no prescriptions on record yet.</p>
                </div>`;
            return;
        }

        card.innerHTML = prescriptions.map(p => {
            const doctorName = p.doctor_first_name ? `Dr. ${p.doctor_first_name} ${p.doctor_last_name}` : 'PrimeCare';
            const pending = Number(p.has_pending_request) === 1;

            let actionHtml = '';
            if (p.status !== 'Expired') {
                actionHtml = pending
                    ? `<span class="pill pill-checkedin">Repeat requested</span>`
                    : `<button class="btn btn-secondary btn-sm repeat-btn" data-id="${p.prescription_id}">Request Repeat Prescription</button>`;
            }

            return `
                <div class="list-card">
                    <div class="list-card-main">
                        <h3>${escapeHtml(p.medication_name)}</h3>

                        <dl class="record-fields">
                            <dt>Medication</dt>
                            <dd>${escapeHtml(p.medication_name)}</dd>

                            <dt>Dosage</dt>
                            <dd>${escapeHtml(p.dosage)}</dd>

                            <dt>Instructions</dt>
                            <dd>${p.instructions ? escapeHtml(p.instructions) : 'No special instructions.'}</dd>

                            <dt>Date Issued</dt>
                            <dd>${formatDate(p.date_issued)}</dd>

                            <dt>Status</dt>
                            <dd><span class="pill ${pillClass[p.status] || 'pill-booked'}">${p.status}</span></dd>

                            <dt>Prescribed by</dt>
                            <dd>${escapeHtml(doctorName)}</dd>
                        </dl>
                    </div>
                    <div class="list-card-actions">
                        ${actionHtml}
                    </div>
                </div>`;
        }).join('');

        card.querySelectorAll('.repeat-btn').forEach(btn => {
            btn.addEventListener('click', () => triggerRequestRepeat(btn.dataset.id, btn));
        });
    }

    function triggerRequestRepeat(id, btn) {
        showConfirmModal(
            'Request repeat prescription?',
            'This will send a request to your doctor for approval. Medication is not issued automatically.',
            () => requestRepeat(id, btn),
            'Yes, send request'
        );
    }

    async function requestRepeat(id, btn) {
        btn.disabled = true;
        btn.textContent = 'Sending request...';

        try {
            const res = await csrfFetch('api/prescriptions/request-repeat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ prescriptionId: id })
            });
            const data = await res.json();

            if (data.success) {
                btn.outerHTML = '<span class="pill pill-checkedin">Repeat requested</span>';
                showToast('success', 'Repeat prescription requested', 'Your doctor will review this request for approval.');
            } else {
                showToast('error', 'Could not send request', data.message || 'Please try again.');
                btn.disabled = false;
                btn.textContent = 'Request Repeat Prescription';
            }
        } catch (err) {
            showToast('error', 'Connection problem', 'Could not reach the server. Please try again.');
            btn.disabled = false;
            btn.textContent = 'Request Repeat Prescription';
        }
    }

    async function loadPrescriptions() {
        try {
            const res = await fetch('api/prescriptions/list.php', { credentials: 'same-origin' });

            if (res.status === 401) { window.location.href = 'login.html'; return; }

            const data = await res.json();
            if (!data.success) throw new Error();

            render(data.prescriptions);
        } catch (err) {
            card.innerHTML = '<div class="empty-state"><p>Couldn\'t load your prescriptions. Please refresh the page.</p></div>';
        }
    }

    loadPrescriptions();
});
