export function roomWaiting(initStatus, roomId) {
    return {
        status: initStatus,
        pollInterval: null,

        init() {
            if (this.status === 'playing') return;

            // WebSocket: redirect immediately when game starts
            window.Echo?.channel('lobby').listen('LobbyRoomUpdated', (e) => {
                if (e.room.id != roomId) return;
                this.status = e.room.status;
                if (e.room.status === 'playing' && e.room.gameId) {
                    window.location.href = '/games/' + e.room.gameId;
                }
            });

            // Polling fallback: every 3s in case WebSocket is unavailable
            this.pollInterval = setInterval(async () => {
                try {
                    const { data } = await window.axios.get(`/rooms/${roomId}/status`);
                    this.status = data.status;
                    if (data.status === 'playing' && data.gameId) {
                        clearInterval(this.pollInterval);
                        window.location.href = '/games/' + data.gameId;
                    } else if (data.status === 'cancelled') {
                        clearInterval(this.pollInterval);
                    }
                } catch {}
            }, 3000);
        },

        destroy() {
            clearInterval(this.pollInterval);
        },
    };
}
