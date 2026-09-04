/* ============================================================
   PrimeCare — Medical Records page behaviors
   Read-only display only — no editing capability exists in the
   UI or the API. Patients can only ever view their own records
   (enforced server-side in api/records/list.php via the session).
   ============================================================ */

document.addEventListener('DOMContentLoaded', function () {

    const card = document.getElementById('recordsCard');

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str ?? '';
        return div.innerHTML;
    }

    function formatDate(dateStr) {
        if (!dateStr) return null;
        const d = new Date(dateStr + 'T00:00:00');
        return d.toLocaleDateString('en-ZA', { day: 'numeric', month: 'long', year: 'numeric' });
    }

    async function loadRecords() {
        try {
            const res = await fetch('api/records/list.php', { credentials: 'same-origin' });

            if (res.status === 401) { window.location.href = 'login.html'; return; }

            const data = await res.json();
            if (!data.success) throw new Error();

            if (!data.records.length) {
                card.innerHTML = `
                    <div class="empty-state">
                        <div class="icon-box">
                            <svg class="icon" viewBox="0 0 24 24"><path d="M9 12h6M9 16h6M9 8h2"/><path d="M6 2h9l5 5v13a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2z"/></svg>
                        </div>
                        <p>You have no medical records yet.</p>
                    </div>`;
                return;
            }

            card.innerHTML = data.records.map((r, i) => {
                const doctorName = r.doctor_first_name ? `Dr. ${r.doctor_first_name} ${r.doctor_last_name}` : 'PrimeCare';
                const followUp = formatDate(r.follow_up_date);

                return `
                    <div class="list-card">
                        <div class="list-card-main">
                            <h3>${escapeHtml(r.diagnosis)}</h3>

                            <dl class="record-fields">
                                <dt>Consultation Date</dt>
                                <dd>${formatDate(r.consultation_date)}</dd>

                                <dt>Doctor</dt>
                                <dd>${escapeHtml(doctorName)}</dd>

                                <dt>Diagnosis</dt>
                                <dd>${escapeHtml(r.diagnosis)}</dd>

                                <dt>Treatment</dt>
                                <dd>${r.treatment ? escapeHtml(r.treatment) : 'No treatment recorded.'}</dd>

                                <dt>Prescription</dt>
                                <dd>${r.prescriptions_summary ? escapeHtml(r.prescriptions_summary) : 'No prescription issued.'}</dd>

                                <dt>Follow-up Date</dt>
                                <dd>${followUp ? followUp : 'No follow-up scheduled.'}</dd>
                            </dl>

                            ${r.notes ? `
                                <button class="record-toggle" data-target="record-${i}">View additional notes</button>
                                <div class="record-detail" id="record-${i}">${escapeHtml(r.notes)}</div>
                            ` : ''}
                        </div>
                    </div>`;
            }).join('');

            card.querySelectorAll('.record-toggle').forEach(btn => {
                btn.addEventListener('click', () => {
                    const detail = document.getElementById(btn.dataset.target);
                    const isOpen = detail.classList.toggle('open');
                    btn.textContent = isOpen ? 'Hide additional notes' : 'View additional notes';
                });
            });

        } catch (err) {
            card.innerHTML = '<div class="empty-state"><p>Couldn\'t load your records. Please refresh the page.</p></div>';
        }
    }

    loadRecords();
});
