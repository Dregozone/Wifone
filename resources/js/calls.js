import { AudioPeer } from './webrtc.js';

const RING_TIMEOUT_MS = 30_000;
const NOTICE_DURATION_MS = 4_000;

/**
 * Call lifecycle states:
 *   idle → outgoing (we are ringing someone) → connecting → active → idle
 *   idle → incoming (someone is ringing us)  → connecting → active → idle
 */
const IDLE = 'idle';
const OUTGOING = 'outgoing';
const INCOMING = 'incoming';
const CONNECTING = 'connecting';
const ACTIVE = 'active';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

async function post(url, body = {}) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Socket-ID': window.Echo.socketId() ?? '',
        },
        body: JSON.stringify(body),
    });

    if (!response.ok) {
        throw new Error(`POST ${url} failed with ${response.status}`);
    }

    return response.json();
}

async function getMicrophone() {
    if (!navigator.mediaDevices?.getUserMedia) {
        throw new Error('Microphone access needs a secure (HTTPS) connection.');
    }

    try {
        return await navigator.mediaDevices.getUserMedia({ audio: true, video: false });
    } catch (error) {
        if (error.name === 'NotAllowedError') {
            throw new Error('Microphone access was denied. Allow it in your browser to make calls.');
        }

        throw new Error(`Could not access the microphone: ${error.message}`);
    }
}

/**
 * Register the `presence` and `call` Alpine stores and wire up the Echo listeners.
 *
 * @param {import('alpinejs').Alpine} Alpine
 * @param {import('laravel-echo').default} Echo
 * @param {number} authUserId
 */
export function registerCallStores(Alpine, Echo, authUserId) {
    // Native WebRTC objects live outside the (proxied) Alpine store on purpose.
    let peer = null;
    let localStream = null;
    let signalChannel = null;
    let ringTimer = null;
    let durationTimer = null;
    let noticeTimer = null;

    Alpine.store('presence', {
        onlineIds: [],

        isOnline(userId) {
            return this.onlineIds.includes(userId);
        },
    });

    Alpine.store('call', {
        state: IDLE,
        callId: null,
        peerId: null,
        peerName: '',
        startedAt: null,
        now: Date.now(),
        notice: '',

        durationLabel() {
            if (!this.startedAt) {
                return '0:00';
            }

            const seconds = Math.max(0, Math.floor((this.now - this.startedAt) / 1000));

            return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
        },

        flash(message) {
            this.notice = message;
            clearTimeout(noticeTimer);
            noticeTimer = setTimeout(() => (this.notice = ''), NOTICE_DURATION_MS);
        },

        /**
         * Caller: ring another user.
         */
        async start(userId, userName) {
            if (this.state !== IDLE) {
                return;
            }

            Object.assign(this, { state: OUTGOING, peerId: userId, peerName: userName });

            try {
                // Ask for the mic first, while we still have the click's user gesture.
                localStream = await getMicrophone();
                const { callId } = await post('/calls', { receiver_id: userId });
                this.callId = callId;

                // Subscribe now so we're already listening when the receiver sends the offer.
                joinSignalChannel(callId);

                ringTimer = setTimeout(() => {
                    if (this.state === OUTGOING) {
                        this.hangUp(`${this.peerName} didn't answer.`);
                    }
                }, RING_TIMEOUT_MS);
            } catch (error) {
                console.error(error);
                this.hangUp(error.message);
            }
        },

        /**
         * Receiver: answer the ringing call. The receiver creates the WebRTC offer.
         */
        async accept() {
            if (this.state !== INCOMING) {
                return;
            }

            this.state = CONNECTING;
            clearTimeout(ringTimer);

            try {
                localStream = await getMicrophone();
            } catch (error) {
                this.reject();
                this.flash(error.message);

                return;
            }

            try {
                await post(`/calls/${this.callId}/accept`);

                joinSignalChannel(this.callId).subscribed(async () => {
                    createPeer();
                    await peer.createOffer();
                });
            } catch (error) {
                console.error(error);
                this.hangUp('Could not connect the call.');
            }
        },

        /**
         * Receiver: decline the ringing call.
         */
        reject() {
            if (this.callId) {
                post(`/calls/${this.callId}/reject`).catch(console.error);
            }

            cleanUp();
        },

        /**
         * Either side: hang up, or cancel while still ringing.
         */
        hangUp(message = '') {
            if (this.callId) {
                post(`/calls/${this.callId}/end`).catch(console.error);
            }

            cleanUp();

            if (message) {
                this.flash(message);
            }
        },

        // ── Server events (private `calls.{authUserId}` channel) ─────────────

        onIncoming({ callId, callerId, callerName }) {
            if (this.state !== IDLE) {
                // Already busy: decline the new call without touching the current one.
                post(`/calls/${callId}/reject`).catch(console.error);

                return;
            }

            Object.assign(this, { state: INCOMING, callId, peerId: callerId, peerName: callerName });

            // If the caller vanished without cancelling, stop ringing eventually.
            ringTimer = setTimeout(() => {
                if (this.state === INCOMING) {
                    this.hangUp(`Missed call from ${this.peerName}.`);
                }
            }, RING_TIMEOUT_MS + 5_000);
        },

        onAccepted({ callId }) {
            if (callId === this.callId && this.state === OUTGOING) {
                clearTimeout(ringTimer);
                this.state = CONNECTING;
            }
        },

        onRejected({ callId }) {
            if (callId === this.callId) {
                const name = this.peerName;
                cleanUp();
                this.flash(`${name} declined the call.`);
            }
        },

        onEnded({ callId, status }) {
            if (callId === this.callId) {
                const name = this.peerName;
                const wasRinging = this.state === INCOMING;
                cleanUp();
                this.flash(wasRinging || status === 'missed' ? `Missed call from ${name}.` : 'Call ended.');
            }
        },

        onPeerLeft(userId) {
            if (userId === this.peerId && this.state !== IDLE) {
                this.hangUp(`${this.peerName} went offline.`);
            }
        },
    });

    const call = Alpine.store('call');
    const presence = Alpine.store('presence');

    function joinSignalChannel(callId) {
        signalChannel = Echo.private(`call.${callId}`)
            .listenForWhisper('offer', async (offer) => {
                // Caller side: the receiver sent us their offer.
                if (!peer) {
                    createPeer();
                }

                await peer.handleOffer(offer);
            })
            .listenForWhisper('answer', (answer) => peer?.handleAnswer(answer))
            .listenForWhisper('ice', (candidate) => peer?.addIceCandidate(candidate))
            .error((error) => {
                console.error('Call channel error:', error);
                call.hangUp('Could not join the call channel.');
            });

        return signalChannel;
    }

    function createPeer() {
        peer = new AudioPeer({
            localStream,
            iceServers: window.iceServers ?? [{ urls: 'stun:stun.l.google.com:19302' }],
            audioElement: document.getElementById('remote-audio'),
            onSignal: (type, payload) => signalChannel?.whisper(type, payload),
            onStateChange: (state) => {
                console.debug('WebRTC connection state:', state);

                if (state === 'connected' && call.state !== ACTIVE) {
                    call.state = ACTIVE;
                    call.startedAt = Date.now();
                    durationTimer = setInterval(() => (call.now = Date.now()), 1000);
                }

                if (state === 'failed') {
                    call.hangUp('The connection failed. A TURN server may be needed on this network.');
                }
            },
        });
    }

    function cleanUp() {
        clearTimeout(ringTimer);
        clearInterval(durationTimer);

        peer?.close();
        peer = null;

        localStream?.getTracks().forEach((track) => track.stop());
        localStream = null;

        if (call.callId) {
            Echo.leave(`call.${call.callId}`);
        }
        signalChannel = null;

        const audio = document.getElementById('remote-audio');
        if (audio) {
            audio.srcObject = null;
        }

        const hadCall = call.callId !== null;

        Object.assign(call, { state: IDLE, callId: null, peerId: null, peerName: '', startedAt: null });

        if (hadCall) {
            // Give the server a moment to record the final status, then refresh any open call log.
            setTimeout(() => window.Livewire?.dispatch('call-finished'), 500);
        }
    }

    // ── Echo subscriptions ───────────────────────────────────────────────────

    Echo.private(`calls.${authUserId}`)
        .listen('.call.initiated', (event) => call.onIncoming(event))
        .listen('.call.accepted', (event) => call.onAccepted(event))
        .listen('.call.rejected', (event) => call.onRejected(event))
        .listen('.call.ended', (event) => call.onEnded(event));

    Echo.join('online')
        .here((users) => (presence.onlineIds = users.map((user) => user.id)))
        .joining((user) => (presence.onlineIds = [...new Set([...presence.onlineIds, user.id])]))
        .leaving((user) => {
            presence.onlineIds = presence.onlineIds.filter((id) => id !== user.id);
            call.onPeerLeft(user.id);
        });

    // Best effort: tell the other side if this tab is closed mid-call.
    window.addEventListener('pagehide', () => {
        if (call.callId) {
            const data = new FormData();
            data.append('_token', csrfToken());
            navigator.sendBeacon(`/calls/${call.callId}/end`, data);
        }
    });
}
