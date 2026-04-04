export function notificationBell() {
    return {
        open: false,
        notifications: [],
        unreadCount: 0,

        init() {
            this.loadNotifications();
            const userId = window.__AUTH_USER_ID__;
            if (userId) {
                window.Echo?.private(`user.${userId}`)
                    .listen('NotificationCreated', (e) => {
                        this.notifications.unshift({ ...e, read_at: null });
                        this.unreadCount++;
                    });
            }
        },

        async loadNotifications() {
            try {
                const res = await window.axios.get('/notifications?per_page=10');
                this.notifications = res.data.data || [];
                this.unreadCount = this.notifications.filter(n => !n.read_at).length;
            } catch {}
        },

        async markAllRead() {
            await window.axios.patch('/notifications/read-all');
            this.notifications.forEach(n => n.read_at = new Date().toISOString());
            this.unreadCount = 0;
        },

        async acceptChallenge(notification) {
            const challengeId = notification.data?.challenge_id;
            if (!challengeId) return;
            try {
                const res = await window.axios.post(`/challenges/${challengeId}/accept`);
                notification.data = { ...notification.data, _resolved: true };
                if (!notification.read_at) {
                    notification.read_at = new Date().toISOString();
                    this.unreadCount = Math.max(0, this.unreadCount - 1);
                }
                if (res.data.game_url) window.location = res.data.game_url;
            } catch (e) {
                alert(e.response?.data?.error || 'เกิดข้อผิดพลาด');
            }
        },

        async declineChallenge(notification) {
            const challengeId = notification.data?.challenge_id;
            if (!challengeId) return;
            try {
                await window.axios.post(`/challenges/${challengeId}/decline`);
                notification.data = { ...notification.data, _resolved: true };
                if (!notification.read_at) {
                    notification.read_at = new Date().toISOString();
                    this.unreadCount = Math.max(0, this.unreadCount - 1);
                }
            } catch {}
        },
    };
}
