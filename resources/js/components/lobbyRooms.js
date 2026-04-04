export function lobbyRooms(initOnlineCount = 0, initOnlineUsers = []) {
    return {
        onlineCount: initOnlineCount,
        onlineUsers: initOnlineUsers,

        init() {
            // app.js already joined presence channel. If here() fired before Alpine
            // initialised, grab the stored members immediately.
            if (window.__onlineMembers?.length) {
                this.onlineUsers = [...window.__onlineMembers];
                this.onlineCount = this.onlineUsers.length;
            }

            // Listen to presence events dispatched by app.js
            window.addEventListener('presence-here', (e) => {
                this.onlineUsers = e.detail.members;
                this.onlineCount = this.onlineUsers.length;
            });
            window.addEventListener('presence-joining', (e) => {
                const m = e.detail.member;
                if (!this.onlineUsers.find(u => u.id === m.id)) {
                    this.onlineUsers = [...this.onlineUsers, m];
                    this.onlineCount = this.onlineUsers.length;
                }
            });
            window.addEventListener('presence-leaving', (e) => {
                this.onlineUsers = this.onlineUsers.filter(u => u.id !== e.detail.member.id);
                this.onlineCount = this.onlineUsers.length;
            });

            // Room list updates
            if (!window.Echo) return;
            window.Echo.channel('lobby')
            .listen('LobbyRoomUpdated', (e) => {
                const list = this.$refs.roomList;
                if (!list) return;
                const existing = list.querySelector(`[data-room-id="${e.room.id}"]`);

                if (e.action === 'deleted') {
                    existing?.remove();
                    return;
                }

                if (e.action === 'created') {
                    const r = e.room;
                    const card = document.createElement('div');
                    card.className = 'card';
                    card.style.padding = '0';
                    card.setAttribute('data-room-id', r.id);
                    card.innerHTML = `
                        <div style="display:flex;align-items:center;gap:1rem;padding:0.875rem 1rem">
                            <div style="flex-shrink:0;width:44px;height:44px;background:#F5F5F7;border-radius:0.625rem;display:flex;flex-direction:column;align-items:center;justify-content:center">
                                <span style="font-size:1rem;font-weight:800;color:#111118;line-height:1">${r.boardSize}</span>
                                <span style="font-size:0.5625rem;font-weight:600;color:#9CA3AF;letter-spacing:0.02em">路</span>
                            </div>
                            <div style="flex:1;min-width:0">
                                <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;margin-bottom:0.25rem">
                                    <span style="font-size:0.9375rem;font-weight:700;color:#111118">${r.name}</span>
                                    ${r.isPrivate ? '<span class="badge badge-yellow"><ion-icon name="lock-closed-outline"></ion-icon> ส่วนตัว</span>' : ''}
                                    <span class="badge badge-green" data-status-badge><ion-icon name="time-outline"></ion-icon> รอผู้เล่น</span>
                                </div>
                                <div style="font-size:0.75rem;color:#6B6B80;display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap">
                                    <span>${r.creator} [${r.creatorRank}]</span>
                                    <span style="color:#D1D5DB">·</span>
                                    <span>${r.clockDescription}</span>
                                    <span style="color:#D1D5DB">·</span>
                                    <span>โคมิ ${r.komi}</span>
                                    ${r.handicap > 0 ? `<span style="color:#D1D5DB">·</span><span>หมากต่อ ${r.handicap}</span>` : ''}
                                </div>
                            </div>
                            <a href="/rooms/${r.id}" class="btn btn-primary btn-sm" style="flex-shrink:0" data-join-btn>
                                <ion-icon name="enter-outline"></ion-icon> เข้าร่วม
                            </a>
                        </div>`;

                    // Remove empty state placeholder if present
                    const empty = list.querySelector('[data-empty]');
                    empty?.remove();

                    list.prepend(card);
                    return;
                }

                if (existing) {
                    const badge = existing.querySelector('[data-status-badge]');
                    if (badge) {
                        badge.className = `badge ${e.room.status === 'waiting' ? 'badge-green' : 'badge-blue'}`;
                        badge.innerHTML = e.room.status === 'waiting'
                            ? '<ion-icon name="time-outline"></ion-icon> รอผู้เล่น'
                            : '<ion-icon name="game-controller-outline"></ion-icon> กำลังเล่น';
                    }
                    const joinBtn = existing.querySelector('[data-join-btn]');
                    if (joinBtn && e.room.status !== 'waiting') {
                        joinBtn.className = 'btn btn-secondary btn-sm';
                        joinBtn.textContent = 'ดู';
                    }
                }
            });
        },
    };
}
