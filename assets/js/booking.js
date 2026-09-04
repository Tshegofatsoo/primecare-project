/* ============================================================
   PrimeCare — Book Appointment page behaviors
   ============================================================ */

document.addEventListener('DOMContentLoaded', function () {

    const departmentSelect = document.getElementById('departmentId');
    const dateInput = document.getElementById('apptDate');
    const slotGrid = document.getElementById('slotGrid');
    const timeInput = document.getElementById('apptTime');
    const reasonInput = document.getElementById('reason');
    const form = document.getElementById('bookingForm');
    const banner = document.getElementById('formBanner');
    const bookBtn = document.getElementById('bookBtn');
    const sidebarUpcoming = document.getElementById('sidebarUpcoming');

    // Restrict the date picker to today .. +30 days
    const todayStr = new Date().toISOString().split('T')[0];
    const maxDate = new Date();
    maxDate.setDate(maxDate.getDate() + 30);
    dateInput.min = todayStr;
    dateInput.max = maxDate.toISOString().split('T')[0];

    // Cache of this patient's own upcoming appointments, used to grey out
    // time slots they've already booked (prevents double booking proactively,
    // instead of only rejecting it after the fact on submit).
    let upcomingAppointments = [];

    function showError(fieldId, show, message) {
        const input = document.getElementById(fieldId);
        const err = document.getElementById('err-' + fieldId);
        if (input) input.classList.toggle('invalid', show);
        if (err) {
            if (message) err.textContent = message;
            err.classList.toggle('show', show);
        }
    }

    function showBanner(type, message) {
        banner.className = 'form-banner show ' + type;
        banner.textContent = message;
        banner.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    /* ---------- Load departments ---------- */
    async function loadDepartments() {
        try {
            const res = await fetch('api/departments/list.php');
            const data = await res.json();
            if (!data.success) throw new Error();

            departmentSelect.innerHTML = '<option value="">Select a department</option>' +
                data.departments.map(d => `<option value="${d.department_id}">${d.department_name}</option>`).join('');
        } catch (err) {
            departmentSelect.innerHTML = '<option value="">Could not load departments</option>';
        }
    }

    /* ---------- Generate time slots based on selected date ---------- */
    function generateSlots() {
        timeInput.value = '';
        slotGrid.innerHTML = '';

        if (!dateInput.value) {
            slotGrid.innerHTML = '<p class="field-hint" style="grid-column:1/-1;">Select a date to see available times.</p>';
            return;
        }

        const selectedDate = new Date(dateInput.value + 'T00:00:00');
        const day = selectedDate.getDay(); // 0 = Sunday, 6 = Saturday

        if (day === 0) {
            slotGrid.innerHTML = '<p class="field-hint" style="grid-column:1/-1;">PrimeCare is closed on Sundays. Please choose another date.</p>';
            return;
        }

        const startHour = 8;
        const endHour = day === 6 ? 13 : 17;
        const isToday = dateInput.value === todayStr;
        const now = new Date();

        // Times this patient has already booked on the selected date —
        // used to disable those specific slots below.
        const bookedTimes = upcomingAppointments
            .filter(a => a.appointment_date === dateInput.value)
            .map(a => a.appointment_time.slice(0, 5));

        let slots = [];
        for (let h = startHour; h < endHour; h++) {
            for (let m of [0, 30]) {
                const slotDate = new Date(selectedDate);
                slotDate.setHours(h, m, 0, 0);
                if (isToday && slotDate <= now) continue; // skip past times today
                const hh = String(h).padStart(2, '0');
                const mm = String(m).padStart(2, '0');
                slots.push(`${hh}:${mm}`);
            }
        }

        if (!slots.length) {
            slotGrid.innerHTML = '<p class="field-hint" style="grid-column:1/-1;">No remaining slots today. Please choose another date.</p>';
            return;
        }

        slotGrid.innerHTML = slots.map(s => {
            const [h, m] = s.split(':');
            const hour12 = ((parseInt(h, 10) + 11) % 12) + 1;
            const period = parseInt(h, 10) >= 12 ? 'PM' : 'AM';
            const isBooked = bookedTimes.includes(s);
            return `<button type="button" class="slot-btn" data-time="${s}" ${isBooked ? 'disabled title="You already have an appointment at this time"' : ''}>${hour12}:${m} ${period}</button>`;
        }).join('');

        slotGrid.querySelectorAll('.slot-btn:not([disabled])').forEach(btn => {
            btn.addEventListener('click', () => {
                slotGrid.querySelectorAll('.slot-btn').forEach(b => b.classList.remove('selected'));
                btn.classList.add('selected');
                timeInput.value = btn.dataset.time;
                showError('apptTime', false);
            });
        });
    }

    dateInput.addEventListener('change', () => {
        showError('apptDate', false);
        generateSlots();
    });

    /* ---------- Load sidebar upcoming appointments ---------- */
    async function loadSidebarUpcoming() {
        try {
            const res = await fetch('api/appointments/list.php', { credentials: 'same-origin' });
            const data = await res.json();
            if (!data.success) throw new Error();

            upcomingAppointments = data.upcoming || [];

            if (!upcomingAppointments.length) {
                sidebarUpcoming.innerHTML = '<div class="empty-state"><p>No upcoming appointments yet.</p></div>';
            } else {
                const pillClass = { Booked: 'pill-booked', CheckedIn: 'pill-checkedin', Completed: 'pill-completed', Cancelled: 'pill-cancelled' };

                sidebarUpcoming.innerHTML = upcomingAppointments.slice(0, 5).map(a => {
                    const [h, m] = a.appointment_time.split(':');
                    const hour12 = ((parseInt(h, 10) + 11) % 12) + 1;
                    const period = parseInt(h, 10) >= 12 ? 'PM' : 'AM';
                    return `
                        <div class="list-card">
                            <div class="list-card-main">
                                <h3>${a.appointment_date} · ${hour12}:${m} ${period}</h3>
                                <p>${a.department_name || 'General'}</p>
                            </div>
                            <span class="pill ${pillClass[a.status] || 'pill-booked'}">${a.status}</span>
                        </div>`;
                }).join('');
            }

            // Re-render slots now that we know which times are already taken
            generateSlots();
        } catch (err) {
            sidebarUpcoming.innerHTML = '<div class="empty-state"><p>Couldn\'t load your appointments.</p></div>';
        }
    }

    /* ---------- Submit booking ---------- */
    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        banner.classList.remove('show', 'error', 'success');

        let valid = true;
        if (!departmentSelect.value) { showError('departmentId', true, 'Please select a department.'); valid = false; } else showError('departmentId', false);
        if (!dateInput.value) { showError('apptDate', true, 'Please select a date.'); valid = false; } else showError('apptDate', false);
        if (!timeInput.value) { showError('apptTime', true, 'Please select a time slot.'); valid = false; } else showError('apptTime', false);
        if (!reasonInput.value.trim()) { showError('reason', true, 'Please describe the reason for your visit.'); valid = false; } else showError('reason', false);

        if (!valid) {
            showBanner('error', 'Please complete all required fields before booking.');
            showToast('warning', 'Missing information', 'Please complete all required fields before booking.');
            return;
        }

        bookBtn.classList.add('loading');
        bookBtn.textContent = 'Booking...';

        try {
            const res = await csrfFetch('api/appointments/create.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    departmentId: departmentSelect.value,
                    date: dateInput.value,
                    time: timeInput.value,
                    reason: reasonInput.value.trim()
                })
            });

            const data = await res.json();

            if (res.ok && data.success) {
                showBanner('success', data.message || 'Your appointment has been booked successfully.');
                showToast('success', 'Appointment booked', data.message || 'Your appointment has been booked successfully.');
                form.reset();
                loadSidebarUpcoming(); // also regenerates the slot grid with fresh data
            } else {
                // Surface field-specific errors returned by the server so the
                // patient sees exactly which field needs correcting, with the
                // server's precise reason (e.g. exact operating hours).
                if (data.errors) {
                    Object.keys(data.errors).forEach(field => showError(field, true, data.errors[field]));
                }
                showBanner('error', data.message || 'We couldn\'t book that appointment. Please review the details and try again.');
                showToast('error', 'Booking failed', data.message || 'Please review the details and try again.');
            }
        } catch (err) {
            showBanner('error', 'Could not reach the server. Please check your connection and try again.');
        } finally {
            bookBtn.classList.remove('loading');
            bookBtn.textContent = 'Book appointment';
        }
    });

    loadDepartments();
    loadSidebarUpcoming();
});
