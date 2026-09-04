/* ============================================================
   PrimeCare — Patient Dashboard behaviors
   Fetches upcoming appointment + notifications from the API
   and renders loading / populated / empty / error states.
   ============================================================ */

document.addEventListener('DOMContentLoaded', function () {

    const MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str ?? '';
        return div.innerHTML;
    }

    function formatTime(timeStr) {
        // timeStr from MySQL TIME column looks like "09:30:00"
        const [h, m] = timeStr.split(':');
        const hour = parseInt(h, 10);
        const period = hour >= 12 ? 'PM' : 'AM';
        const hour12 = ((hour + 11) % 12) + 1;
        return `${hour12}:${m} ${period}`;
    }

    function relativeTime(dateStr) {
        const then = new Date(dateStr.replace(' ', 'T'));
        const diffMs = Date.now() - then.getTime();
        const diffMins = Math.floor(diffMs / 60000);
        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return diffMins + 'm ago';
        const diffHrs = Math.floor(diffMins / 60);
        if (diffHrs < 24) return diffHrs + 'h ago';
        const diffDays = Math.floor(diffHrs / 24);
        return diffDays + 'd ago';
    }

    /* ---------- Upcoming appointment ---------- */
    async function loadUpcomingAppointment() {
        const card = document.getElementById('upcomingCard');
        const statUpcoming = document.getElementById('statUpcoming');

        try {
            const res = await fetch('api/appointments/list.php', { credentials: 'same-origin' });

            if (res.status === 401) {
                window.location.href = 'login.html';
                return;
            }

            const data = await res.json();

            if (!data.success) throw new Error(data.message || 'Failed to load');

            statUpcoming.textContent = data.count;

            if (!data.next) {
                card.innerHTML = `
                    <div class="empty-state">
                        <div class="icon-box">
                            <svg class="icon" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M3 10h18M8 2v4M16 2v4"/></svg>
                        </div>
                        <p>You have no upcoming appointments.</p>
                        <a href="book-appointment.php" class="btn btn-primary">Book an appointment</a>
                    </div>`;
                return;
            }

            const appt = data.next;
            const dateObj = new Date(appt.appointment_date + 'T00:00:00');
            const doctorName = appt.doctor_first_name
                ? `Dr. ${appt.doctor_first_name} ${appt.doctor_last_name}`
                : 'To be assigned';

            const pillClass = {
                Booked: 'pill-booked',
                CheckedIn: 'pill-checkedin',
                Completed: 'pill-completed',
                Cancelled: 'pill-cancelled'
            }[appt.status] || 'pill-booked';

            card.innerHTML = `
                <div class="appt-card">
                    <div class="appt-date-block">
                        <div class="day">${dateObj.getDate()}</div>
                        <div class="month">${MONTHS[dateObj.getMonth()]}</div>
                    </div>
                    <div class="appt-details">
                        <h3>${escapeHtml(appt.reason)}</h3>
                        <p>${formatTime(appt.appointment_time)} · ${escapeHtml(doctorName)}</p>
                        <p>${escapeHtml(appt.department_name || 'General Practice')}</p>
                    </div>
                    <span class="pill ${pillClass}">${escapeHtml(appt.status)}</span>
                </div>`;

        } catch (err) {
            card.innerHTML = `
                <div class="empty-state">
                    <p>Couldn't load your appointments right now. Please refresh the page.</p>
                </div>`;
        }
    }

    /* ---------- Notifications ---------- */
    async function loadNotifications() {
        const card = document.getElementById('notificationsCard');
        const statUnread = document.getElementById('statUnread');

        try {
            const res = await fetch('api/notifications/list.php', { credentials: 'same-origin' });

            if (res.status === 401) {
                window.location.href = 'login.html';
                return;
            }

            const data = await res.json();
            if (!data.success) throw new Error(data.message || 'Failed to load');

            statUnread.textContent = data.unreadCount;

            if (!data.notifications.length) {
                card.innerHTML = `
                    <div class="empty-state">
                        <div class="icon-box">
                            <svg class="icon" viewBox="0 0 24 24"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
                        </div>
                        <p>You're all caught up — no notifications yet.</p>
                    </div>`;
                return;
            }

            const iconByType = {
                Appointment: '<path d="M3 10h18M8 2v4M16 2v4"/><rect x="3" y="4" width="18" height="18" rx="2"/>',
                Prescription: '<rect x="3" y="9" width="18" height="6" rx="3" transform="rotate(-45 12 12)"/>',
                General: '<circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/>'
            };

            card.innerHTML = data.notifications.map(n => `
                <div class="notification-item ${n.is_read == 0 ? 'unread' : ''}">
                    <div class="icon-box">
                        <svg class="icon" style="width:18px;height:18px;" viewBox="0 0 24 24">${iconByType[n.type] || iconByType.General}</svg>
                    </div>
                    <div class="notification-content">
                        <div class="n-title">
                            ${escapeHtml(n.title)}
                            ${n.is_read == 0 ? '<span class="unread-dot"></span>' : ''}
                        </div>
                        <div class="n-message">${escapeHtml(n.message)}</div>
                        <div class="n-time">${relativeTime(n.created_at)}</div>
                    </div>
                </div>
            `).join('');

        } catch (err) {
            card.innerHTML = `
                <div class="empty-state">
                    <p>Couldn't load your notifications right now. Please refresh the page.</p>
                </div>`;
        }
    }

    loadUpcomingAppointment();
    loadNotifications();
});
