import './bootstrap';
import Alpine from 'alpinejs';
import { goBoard } from './components/goBoard';
import { clockTimer } from './components/clockTimer';
import { chatWindow } from './components/chatWindow';
import { notificationBell } from './components/notificationBell';
import { lobbyRooms } from './components/lobbyRooms';
import { roomWaiting } from './components/roomWaiting';
import { gameReplay } from './components/gameReplay';
import { gameAnnotationEditor } from './components/gameAnnotationEditor';

Alpine.data('goBoard', goBoard);
Alpine.data('clockTimer', clockTimer);
Alpine.data('chatWindow', chatWindow);
Alpine.data('notificationBell', notificationBell);
Alpine.data('lobbyRooms', lobbyRooms);
Alpine.data('roomWaiting', roomWaiting);
Alpine.data('gameReplay', gameReplay);
Alpine.data('gameReview', gameReplay);
Alpine.data('gameAnnotationEditor', gameAnnotationEditor);

window.Alpine = Alpine;
Alpine.start();

// Presence channel: join on every authenticated page so the user counts as online.
// All member data is stored globally and re-dispatched as DOM events so that
// lobby (or any component initialised after Echo subscribes) can still read it.
document.addEventListener('DOMContentLoaded', () => {
    if (!window.__AUTH_USER_ID__ || !window.Echo) return;

    window.__onlineMembers = [];

    window.Echo.join('online')
        .here((members) => {
            window.__onlineMembers = members;
            window.dispatchEvent(new CustomEvent('presence-here', { detail: { members } }));
        })
        .joining((member) => {
            if (!window.__onlineMembers.find(m => m.id === member.id)) {
                window.__onlineMembers = [...window.__onlineMembers, member];
            }
            window.dispatchEvent(new CustomEvent('presence-joining', { detail: { member } }));
        })
        .leaving((member) => {
            window.__onlineMembers = window.__onlineMembers.filter(m => m.id !== member.id);
            window.dispatchEvent(new CustomEvent('presence-leaving', { detail: { member } }));
        });
});
