export function chatWindow(chatRoomId) {
    return {
        chatRoomId,
        messages: [],
        draft: '',

        init() {
            // Load initial messages from API
            window.axios.get(`/chat/${chatRoomId}/messages`).then(res => {
                this.messages = res.data;
                this.$nextTick(() => setTimeout(() => this.scrollToBottom(), 50));
            }).catch(() => {});

            // Listen for new messages
            window.Echo?.join(`chat.${chatRoomId}`)
                .listen('ChatMessageSent', (e) => {
                    this.messages.push(e);
                    this.$nextTick(() => this.scrollToBottom());
                });
        },

        async sendMessage() {
            const body = this.draft.trim();
            if (!body) return;
            this.draft = '';
            try {
                const res = await window.axios.post(`/chat/${chatRoomId}/messages`, { body });
                // Our own message is pushed via Axios response
                this.messages.push(res.data);
                this.$nextTick(() => this.scrollToBottom());
            } catch (err) {
                this.draft = body; // restore on error
            }
        },

        scrollToBottom() {
            const el = this.$refs.msgContainer;
            if (el) el.scrollTop = el.scrollHeight;
        },
    };
}
