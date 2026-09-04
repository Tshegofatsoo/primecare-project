/* ============================================================
   PrimeCare — My Appointments page behaviors
   ============================================================ */

document.addEventListener('DOMContentLoaded', function () {

    const card = document.getElementById('appointmentsCard');
    const tabs = document.querySelectorAll('.tab-btn');
    let allAppointments = [];
    let currentTab = 'upcoming';

    const pillClass = { Booked: 'pill-booked', CheckedIn: 'pill-checkedin', Completed: 'pill-completed', Cancelled: 'pill-cancelled' };

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str ?? '';
        return div.innerHTML;
    }

    function formatTime(timeStr) {
        const [h, m] = timeStr.split(':');
        const hour12 = ((parseInt(h, 10) + 11) % 12) + 1;
        const period = parseInt(h, 10) >= 12 ? 'PM' : 'AM';
        return `${hour12}:${m} ${period}`;
    }

    function apptNumber(id) {
        return '#APT-' + String(id).padStart(6, '0');
    }

    function render() {
        const today = new Date().toISOString().split('T')[0];

        const filtered = allAppointments.filter(a => {
            if (currentTab === 'upcoming') {
                return ['Booked', 'CheckedIn'].includes(a.status) && a.appointment_date >= today;
            }
            return a.status === 'Completed' || a.status === 'Cancelled' || a.appointment_date < today;
        });

        if (!filtered.length) {
            card.innerHTML = `
                <div class="empty-state">
                    <div class="icon-box">
                        <svg class="icon" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M3 10h18M8 2v4M16 2v4"/></svg>
                    </div>
                    <p>${currentTab === 'upcoming' ? 'No upcoming appointments.' : 'No past appointments yet.'}</p>
                    ${currentTab === 'upcoming' ? '<a href="book-appointment.php" class="btn btn-primary">Book an appointment</a>' : ''}
                </div>`;
            return;
        }

        card.innerHTML = filtered.map(a => {
            const doctorName = a.doctor_first_name ? `Dr. ${a.doctor_first_name} ${a.doctor_last_name}` : 'To be assigned';
            const isFuture = a.appointment_date >= today;
            const canCancel = ['Booked', 'CheckedIn'].includes(a.status) && isFuture;
            // Reschedule is only offered for 'Booked' appointments — once
            // checked in at the clinic, rescheduling no longer applies.
            const canReschedule = a.status === 'Booked' && isFuture;

            return `
                <div class="list-card">
                    <div class="list-card-main">
                        <div class="appt-number">${apptNumber(a.appointment_id)}</div>
                        <h3>${a.appointment_date} · ${formatTime(a.appointment_time)}</h3>
                        <p>${escapeHtml(a.reason)}</p>
                        <p>${escapeHtml(doctorName)} · ${escapeHtml(a.department_name || 'General Practice')}</p>
                    </div>
                    <div class="list-card-actions">
                        <span class="pill ${pillClass[a.status] || 'pill-booked'}">${a.status}</span>
                        ${canReschedule ? `<button class="btn btn-secondary btn-sm reschedule-btn" data-id="${a.appointment_id}">Reschedule</button>` : ''}
                        ${canCancel ? `<button class="btn btn-secondary btn-sm cancel-btn" data-id="${a.appointment_id}">Cancel</button>` : ''}
                    </div>
                </div>`;
        }).join('');

        card.querySelectorAll('.cancel-btn').forEach(btn => {
            btn.addEventListener('click', () => triggerCancel(btn.dataset.id, btn));
        });
        card.querySelectorAll('.reschedule-btn').forEach(btn => {
            btn.addEventListener('click', () => openRescheduleModal(btn.dataset.id));
        });
    }

    function triggerCancel(id, btn) {
        showConfirmModal(
            'Cancel appointment?',
            'Are you sure you want to cancel this appointment? This cannot be undone.',
            () => cancelAppointment(id, btn),
            'Yes, cancel it'
        );
    }

    async function cancelAppointment(id, btn) {
        btn.disabled = true;
        btn.textContent = 'Cancelling...';

        try {
            const res = await csrfFetch('api/appointments/cancel.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ appointmentId: id })
            });
            const data = await res.json();

            if (data.success) {
                const appt = allAppointments.find(a => String(a.appointment_id) === String(id));
                if (appt) appt.status = 'Cancelled';
                render();
                showToast('success', 'Appointment cancelled', 'Your appointment has been cancelled successfully.');
            } else {
                showToast('error', 'Could not cancel appointment', data.message || 'Please try again.');
                btn.disabled = false;
                btn.textContent = 'Cancel';
            }
        } catch (err) {
            showToast('error', 'Connection problem', 'Could not reach the server. Please try again.');
            btn.disabled = false;
            btn.textContent = 'Cancel';
        }
    }

    async function loadAppointments() {
        try {
            const res = await fetch('api/appointments/list.php?scope=all', { credentials: 'same-origin' });

            if (res.status === 401) { window.location.href = 'login.html'; return; }

            const data = await res.json();
            if (!data.success) throw new Error();

            allAppointments = data.appointments;
            render();
        } catch (err) {
            card.innerHTML = '<div class="empty-state"><p>Couldn\'t load your appointments. Please refresh the page.</p></div>';
        }
    }

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            currentTab = tab.dataset.tab;
            render();
        });
    });

    /* ============================================================
       Reschedule modal
       ============================================================ */
    const modal = document.getElementById('rescheduleModal');
    const rescheduleDate = document.getElementById('rescheduleDate');
    const rescheduleSlotGrid = document.getElementById('rescheduleSlotGrid');
    const rescheduleTime = document.getElementById('rescheduleTime');
    const rescheduleBanner = document.getElementById('rescheduleBanner');
    const rescheduleConfirmBtn = document.getElementById('rescheduleConfirmBtn');
    let reschedulingId = null;

    const todayStr = new Date().toISOString().split('T')[0];
    const maxDate = new Date();
    maxDate.setDate(maxDate.getDate() + 30);
    rescheduleDate.min = todayStr;
    rescheduleDate.max = maxDate.toISOString().split('T')[0];

    function showModalError(fieldId, show, message) {
        const err = document.getElementById('err-' + fieldId);
        if (err) {
            if (message) err.textContent = message;
            err.classList.toggle('show', show);
        }
    }

    function generateRescheduleSlots() {
        rescheduleTime.value = '';
        rescheduleSlotGrid.innerHTML = '';

        if (!rescheduleDate.value) {
            rescheduleSlotGrid.innerHTML = '<p class="field-hint" style="grid-column:1/-1;">Select a date to see available times.</p>';
            return;
        }

        const selectedDate = new Date(rescheduleDate.value + 'T00:00:00');
        const day = selectedDate.getDay();

        if (day === 0) {
            rescheduleSlotGrid.innerHTML = '<p class="field-hint" style="grid-column:1/-1;">PrimeCare is closed on Sundays. Please choose another date.</p>';
            return;
        }

        const startHour = 8;
        const endHour = day === 6 ? 13 : 17;
        const isToday = rescheduleDate.value === todayStr;
        const now = new Date();

        // Block times already taken by this patient's OTHER appointments
        // (excluding the one currently being rescheduled).
        const bookedTimes = allAppointments
            .filter(a => a.appointment_date === rescheduleDate.value
                && String(a.appointment_id) !== String(reschedulingId)
                && ['Booked', 'CheckedIn'].includes(a.status))
            .map(a => a.appointment_time.slice(0, 5));

        let slots = [];
        for (let h = startHour; h < endHour; h++) {
            for (let m of [0, 30]) {
                const slotDate = new Date(selectedDate);
                slotDate.setHours(h, m, 0, 0);
                if (isToday && slotDate <= now) continue;
                const hh = String(h).padStart(2, '0');
                const mm = String(m).padStart(2, '0');
                slots.push(`${hh}:${mm}`);
            }
        }

        if (!slots.length) {
            rescheduleSlotGrid.innerHTML = '<p class="field-hint" style="grid-column:1/-1;">No remaining slots today. Please choose another date.</p>';
            return;
        }

        rescheduleSlotGrid.innerHTML = slots.map(s => {
            const [h, m] = s.split(':');
            const hour12 = ((parseInt(h, 10) + 11) % 12) + 1;
            const period = parseInt(h, 10) >= 12 ? 'PM' : 'AM';
            const isBooked = bookedTimes.includes(s);
            return `<button type="button" class="slot-btn" data-time="${s}" ${isBooked ? 'disabled title="You already have an appointment at this time"' : ''}>${hour12}:${m} ${period}</button>`;
        }).join('');

        rescheduleSlotGrid.querySelectorAll('.slot-btn:not([disabled])').forEach(btn => {
            btn.addEventListener('click', () => {
                rescheduleSlotGrid.querySelectorAll('.slot-btn').forEach(b => b.classList.remove('selected'));
                btn.classList.add('selected');
                rescheduleTime.value = btn.dataset.time;
                showModalError('rescheduleTime', false);
            });
        });
    }

    rescheduleDate.addEventListener('change', () => {
        showModalError('rescheduleDate', false);
        generateRescheduleSlots();
    });

    function openRescheduleModal(appointmentId) {
        reschedulingId = appointmentId;
        rescheduleDate.value = '';
        rescheduleTime.value = '';
        rescheduleBanner.classList.remove('show', 'error', 'success');
        showModalError('rescheduleDate', false);
        showModalError('rescheduleTime', false);
        generateRescheduleSlots();
        modal.classList.add('open');
    }

    function closeRescheduleModal() {
        modal.classList.remove('open');
        reschedulingId = null;
    }

    document.getElementById('rescheduleCloseBtn').addEventListener('click', closeRescheduleModal);
    document.getElementById('rescheduleCancelBtn').addEventListener('click', closeRescheduleModal);
    modal.addEventListener('click', (e) => { if (e.target === modal) closeRescheduleModal(); });

    rescheduleConfirmBtn.addEventListener('click', async () => {
        rescheduleBanner.classList.remove('show', 'error', 'success');

        let valid = true;
        if (!rescheduleDate.value) { showModalError('rescheduleDate', true, 'Please select a date.'); valid = false; } else showModalError('rescheduleDate', false);
        if (!rescheduleTime.value) { showModalError('rescheduleTime', true, 'Please select a time slot.'); valid = false; } else showModalError('rescheduleTime', false);

        if (!valid) {
            rescheduleBanner.className = 'form-banner show error';
            rescheduleBanner.textContent = 'Please select a new date and time.';
            showToast('warning', 'Missing information', 'Please select a new date and time before confirming.');
            return;
        }

        rescheduleConfirmBtn.classList.add('loading');
        rescheduleConfirmBtn.textContent = 'Rescheduling...';

        try {
            const res = await csrfFetch('api/appointments/reschedule.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    appointmentId: reschedulingId,
                    date: rescheduleDate.value,
                    time: rescheduleTime.value
                })
            });
            const data = await res.json();

            if (res.ok && data.success) {
                closeRescheduleModal();
                loadAppointments();
                showToast('success', 'Appointment rescheduled', 'Your appointment has been moved to the new date and time.');
            } else {
                if (data.errors) {
                    Object.keys(data.errors).forEach(field => showModalError(field, true, data.errors[field]));
                }
                rescheduleBanner.className = 'form-banner show error';
                rescheduleBanner.textContent = data.message || 'Could not reschedule this appointment.';
            }
        } catch (err) {
            rescheduleBanner.className = 'form-banner show error';
            rescheduleBanner.textContent = 'Could not reach the server. Please try again.';
        } finally {
            rescheduleConfirmBtn.classList.remove('loading');
            rescheduleConfirmBtn.textContent = 'Confirm reschedule';
        }
    });

    loadAppointments();
});
