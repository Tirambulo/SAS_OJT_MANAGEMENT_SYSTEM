(function () {
    'use strict';
    window.SASOjtNotifications = {
        refreshBadge: async function () {
            const button = document.getElementById('notification-button');
            if (!button) return;
            try {
                const response = await fetch('../../backend/shared/notification-controller.php', {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' }
                });
                if (!response.ok) return;
                const result = await response.json();
                if (!result.success) return;
                const unread = result.data.filter(function (item) { return Number(item.is_read) === 0; }).length;
                button.textContent = unread ? 'Notifications (' + unread + ')' : 'Notifications';
            } catch (error) {
                console.warn('Notification count is currently unavailable.');
            }
        }
    };
}());