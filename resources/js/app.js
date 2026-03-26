import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { startCall, handleOffer, handleAnswer, addIceCandidate, hangUp } from './webrtc.js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});

// ── Private channel listeners ────────────────────────────────────────────────

if (window.authUserId) {
    window.Echo.private(`calls.${window.authUserId}`)

        .listen('.call.initiated', ({ fromUserId, fromUserName }) => {
            window.currentCallUserId = fromUserId;
            document.getElementById('caller-name').textContent = fromUserName;
            showIncomingCallModal();
        })

        .listen('.call.accepted', ({ fromUserId }) => {
            hideIncomingCallModal();
            showInCallUi(fromUserId);
            startCall(fromUserId);
        })

        .listen('.call.rejected', () => {
            hideIncomingCallModal();
            resetCallState();
            alert('Call was rejected.');
        })

        .listen('.call.ended', () => {
            hangUp(window.currentCallUserId);
            hideInCallUi();
            resetCallState();
        })

        .listen('.webrtc.offer', ({ fromUserId, offer }) => {
            window.currentCallUserId = fromUserId;
            showInCallUi(fromUserId);
            handleOffer(fromUserId, offer);
        })

        .listen('.webrtc.answer', ({ answer }) => {
            handleAnswer(answer);
        })

        .listen('.webrtc.ice-candidate', ({ candidate }) => {
            addIceCandidate(candidate);
        });
}

// ── Presence channel – online user tracking ──────────────────────────────────

function updateOnlineUsers(ids) {
    Livewire.dispatch('online-users-updated', { ids });
}

function addOnlineUser(id) {
    Livewire.dispatch('online-users-updated', {
        ids: [...(window._onlineUserIds ?? []), id],
    });
}

function removeOnlineUser(id) {
    Livewire.dispatch('online-users-updated', {
        ids: (window._onlineUserIds ?? []).filter((uid) => uid !== id),
    });
}

window.Echo.join('presence-online')
    .here((users) => {
        window._onlineUserIds = users.map((u) => u.id);
        updateOnlineUsers(window._onlineUserIds);
    })
    .joining((user) => {
        window._onlineUserIds = [...(window._onlineUserIds ?? []), user.id];
        addOnlineUser(user.id);
    })
    .leaving((user) => {
        window._onlineUserIds = (window._onlineUserIds ?? []).filter((id) => id !== user.id);
        removeOnlineUser(user.id);
    });

// ── Global UI helper functions ───────────────────────────────────────────────

function showIncomingCallModal() {
    const modal = document.getElementById('incoming-call-modal');
    if (modal) {
        modal.style.display = 'flex';
    }
}

function hideIncomingCallModal() {
    const modal = document.getElementById('incoming-call-modal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function showInCallUi(userId) {
    const ui = document.getElementById('in-call-ui');
    const label = document.getElementById('in-call-with');
    if (ui) {
        ui.style.display = 'flex';
    }
    if (label) {
        label.textContent = userId;
    }
}

function hideInCallUi() {
    const ui = document.getElementById('in-call-ui');
    if (ui) {
        ui.style.display = 'none';
    }
}

function resetCallState() {
    window.currentCallUserId = null;
}

window.initiateCall = function (toUserId, toUserName) {
    fetch('/call/initiate', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        },
        body: JSON.stringify({ to_user_id: toUserId }),
    })
        .then((response) => {
            if (response.ok) {
                window.currentCallUserId = toUserId;
                const callerName = document.getElementById('caller-name');
                if (callerName) {
                    callerName.textContent = toUserName;
                }
            }
        })
        .catch((err) => console.error('Failed to initiate call:', err));
};

window.acceptCall = function () {
    fetch('/call/accept', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        },
        body: JSON.stringify({ to_user_id: window.currentCallUserId }),
    })
        .then((response) => {
            if (response.ok) {
                hideIncomingCallModal();
                showInCallUi(window.currentCallUserId);
            }
        })
        .catch((err) => console.error('Failed to accept call:', err));
};

window.rejectCall = function () {
    fetch('/call/reject', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        },
        body: JSON.stringify({ to_user_id: window.currentCallUserId }),
    })
        .then((response) => {
            if (response.ok) {
                hideIncomingCallModal();
                resetCallState();
            }
        })
        .catch((err) => console.error('Failed to reject call:', err));
};

window.endCall = function () {
    fetch('/call/end', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        },
        body: JSON.stringify({ to_user_id: window.currentCallUserId }),
    })
        .then((response) => {
            if (response.ok) {
                hangUp(window.currentCallUserId);
                hideInCallUi();
                resetCallState();
            }
        })
        .catch((err) => console.error('Failed to end call:', err));
};
