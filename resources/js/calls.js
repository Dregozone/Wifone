import { AudioPeer } from './webrtc.js';

const RING_TIMEOUT_MS = 30_000;
const RECONNECT_GRACE_MS = 20_000;
const HEARTBEAT_INTERVAL_MS = 20_000;
const NOTICE_DURATION_MS = 4_000;
const STORAGE_KEY = 'wifone.callId';

/**
 * Call lifecycle states:
 *   idle → outgoing (we are ringing someone) → connecting → active → idle
 *   idle → incoming (someone is ringing us)  → connecting → active → idle
 *   active ⇄ reconnecting (network blip, or either side reloaded the page)
 */
const IDLE = 'idle';
const OUTGOING = 'outgoing';
const INCOMING = 'incoming';
const CONNECTING = 'connecting';
const ACTIVE = 'active';
const RECONNECTING = 'reconnecting';

const IN_CALL_STATES = [CONNECTING, ACTIVE, RECONNECTING];

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

async function request(method, url, body = undefined) {
    const response = await fetch(url, {
        method,
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Socket-ID': window.Echo.socketId() ?? '',
        },
        body: body === undefined ? undefined : JSON.stringify(body),
    });

    if (!response.ok) {
        const error = new Error(`${method} ${url} failed with ${response.status}`);
        error.status = response.status;

        throw error;
    }

    return response.json();
}

const post = (url, body = {}) => request('POST', url, body);

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
 * sessionStorage is per tab and survives reloads, so only the tab that was in the
 * call resumes it (not the same user's other tabs or devices).
 */
const rememberedCall = {
    get: () => {
        try {
            return Number(sessionStorage.getItem(STORAGE_KEY)) || null;
        } catch {
            return null;
        }
    },
    set: (callId) => {
        try {
            sessionStorage.setItem(STORAGE_KEY, String(callId));
        } catch {
            // Storage unavailable (e.g. private mode restrictions): resuming after reload just won't work.
        }
    },
    clear: () => {
        try {
            sessionStorage.removeItem(STORAGE_KEY);
        } catch {
            // Ignore.
        }
    },
};

/**
 * Register the `presence` and `call` Alpine stores and wire up the Echo listeners.
 *
 * @param {import('alpinejs').Alpine} Alpine
 * @param {import('laravel-echo').default} Echo
 * @param {number} authUserId
 */
export function registerCallStores(Alpine, Echo, authUserId) {
    // Native WebRTC objects and timers live outside the (proxied) Alpine store on purpose.
    let peer = null;
    let localStream = null;
    let signalChannel = null;
    let ringTimer = null;
    let graceTimer = null;
    let heartbeatTimer = null;
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
        isCaller: false,
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

            Object.assign(this, { state: OUTGOING, isCaller: true, peerId: userId, peerName: userName });

            try {
                // Ask for the mic first, while we still have the click's user gesture.
                localStream = await getMicrophone();
                const { callId } = await post('/calls', { receiver_id: userId });
                this.callId = callId;
                rememberedCall.set(callId);

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
                rememberedCall.set(this.callId);
                startHeartbeat();

                joinSignalChannel(this.callId).subscribed(() => sendOffer());
            } catch (error) {
                console.error(error);
                this.hangUp(error.status === 403 ? 'The call is no longer available.' : 'Could not connect the call.');
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

            Object.assign(this, { state: INCOMING, callId, isCaller: false, peerId: callerId, peerName: callerName });
            startIncomingRingTimer();
        },

        onAccepted({ callId }) {
            if (callId !== this.callId) {
                return;
            }

            if (this.state === OUTGOING) {
                clearTimeout(ringTimer);
                this.state = CONNECTING;
                startHeartbeat();
            } else if (this.state === INCOMING) {
                // We're the receiver and another of our devices picked up.
                cleanUp();
                this.flash('Answered on another device.');
            }
        },

        onRejected({ callId }) {
            if (callId !== this.callId) {
                return;
            }

            const wasCalling = this.state === OUTGOING;
            const name = this.peerName;
            cleanUp();

            // Receiver side: another of our devices declined, so just stop ringing quietly.
            if (wasCalling) {
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
            if (userId !== this.peerId || this.state === IDLE) {
                return;
            }

            if (IN_CALL_STATES.includes(this.state)) {
                // They may just be reloading the page: give them a chance to rejoin.
                this.state = RECONNECTING;
                startGraceTimer(`${this.peerName} went offline.`);
            } else {
                this.hangUp(`${this.peerName} went offline.`);
            }
        },
    });

    const call = Alpine.store('call');
    const presence = Alpine.store('presence');

    function joinSignalChannel(callId) {
        signalChannel = Echo.private(`call.${callId}`)
            .listenForWhisper('offer', async (offer) => {
                if (!peer) {
                    createPeer();
                }

                await peer.handleOffer(offer);
            })
            .listenForWhisper('answer', (answer) => peer?.handleAnswer(answer))
            .listenForWhisper('ice', (candidate) => peer?.addIceCandidate(candidate))
            .listenForWhisper('rejoin', () => {
                // The other side reloaded or lost its connection: rebuild the connection with a fresh offer.
                if (IN_CALL_STATES.includes(call.state)) {
                    call.state = RECONNECTING;
                    startGraceTimer(`Lost connection to ${call.peerName}.`);
                    sendOffer();
                }
            })
            .error((error) => {
                console.error('Call channel error:', error);
                call.hangUp('Could not join the call channel.');
            });

        return signalChannel;
    }

    /**
     * Ask the other side to send us a fresh offer (after a reload or a failed connection).
     */
    function requestRenegotiation() {
        peer?.close();
        peer = null;
        signalChannel?.whisper('rejoin', {});
    }

    async function sendOffer() {
        peer?.close();
        createPeer();
        await peer.createOffer();
    }

    function createPeer() {
        const thisPeer = new AudioPeer({
            localStream,
            iceServers: window.iceServers ?? [{ urls: 'stun:stun.l.google.com:19302' }],
            audioElement: document.getElementById('remote-audio'),
            onSignal: (type, payload) => signalChannel?.whisper(type, payload),
            onStateChange: (state) => {
                if (peer !== thisPeer) {
                    return;
                }

                console.debug('WebRTC connection state:', state);
                handleConnectionState(state);
            },
        });

        peer = thisPeer;
    }

    function handleConnectionState(state) {
        if (state === 'connected') {
            clearTimeout(graceTimer);
            graceTimer = null;
            call.state = ACTIVE;
            call.startedAt ??= Date.now();
            startHeartbeat();

            if (!durationTimer) {
                durationTimer = setInterval(() => (call.now = Date.now()), 1000);
            }
        }

        if (state === 'disconnected' && call.state === ACTIVE) {
            // Often recovers on its own within a few seconds.
            call.state = RECONNECTING;
            startGraceTimer('The connection was lost.');
        }

        if (state === 'failed') {
            if (!call.startedAt) {
                call.hangUp('The connection failed. A TURN server may be needed on this network.');

                return;
            }

            call.state = RECONNECTING;
            startGraceTimer('The connection was lost.');

            // Only one side asks, so both don't send offers at once.
            if (call.isCaller) {
                requestRenegotiation();
            }
        }
    }

    function startGraceTimer(message) {
        if (graceTimer) {
            return;
        }

        graceTimer = setTimeout(() => {
            graceTimer = null;

            if (call.state === RECONNECTING) {
                call.hangUp(message);
            }
        }, RECONNECT_GRACE_MS);
    }

    function startIncomingRingTimer() {
        // If the caller vanished without cancelling, stop ringing eventually.
        ringTimer = setTimeout(() => {
            if (call.state === INCOMING) {
                call.hangUp(`Missed call from ${call.peerName}.`);
            }
        }, RING_TIMEOUT_MS + 5_000);
    }

    function startHeartbeat() {
        if (heartbeatTimer) {
            return;
        }

        heartbeatTimer = setInterval(async () => {
            if (!call.callId) {
                return;
            }

            try {
                await post(`/calls/${call.callId}/heartbeat`);
            } catch (error) {
                // 403: the server considers the call over (e.g. the other side hung up while we were offline).
                if (error.status === 403) {
                    cleanUp();
                    call.flash('Call ended.');
                }
            }
        }, HEARTBEAT_INTERVAL_MS);
    }

    /**
     * After a page reload, pick the call back up if this tab was in one.
     */
    async function resumeRememberedCall() {
        const callId = rememberedCall.get();

        if (!callId) {
            return;
        }

        let details;

        try {
            details = await request('GET', `/calls/${callId}`);
        } catch {
            rememberedCall.clear();

            return;
        }

        const { status, isCaller, peerId, peerName, startedAt } = details;

        if (status === 'ringing' && isCaller) {
            // We can't pick the ringing back up cleanly, so cancel it.
            post(`/calls/${callId}/end`).catch(console.error);
            rememberedCall.clear();

            return;
        }

        if (status === 'ringing') {
            Object.assign(call, { state: INCOMING, callId, isCaller, peerId, peerName });
            startIncomingRingTimer();

            return;
        }

        if (status !== 'active') {
            rememberedCall.clear();

            return;
        }

        Object.assign(call, {
            state: RECONNECTING,
            callId,
            isCaller,
            peerId,
            peerName,
            startedAt: startedAt ? Date.parse(startedAt) : Date.now(),
        });
        durationTimer = setInterval(() => (call.now = Date.now()), 1000);
        startGraceTimer(`Could not reconnect to ${peerName}.`);

        try {
            localStream = await getMicrophone();
        } catch (error) {
            call.hangUp(error.message);

            return;
        }

        startHeartbeat();
        joinSignalChannel(callId).subscribed(() => requestRenegotiation());
    }

    function cleanUp() {
        clearTimeout(ringTimer);
        clearTimeout(graceTimer);
        clearInterval(heartbeatTimer);
        clearInterval(durationTimer);
        graceTimer = null;
        heartbeatTimer = null;
        durationTimer = null;

        peer?.close();
        peer = null;

        localStream?.getTracks().forEach((track) => track.stop());
        localStream = null;

        if (call.callId) {
            Echo.leave(`call.${call.callId}`);
        }
        signalChannel = null;
        rememberedCall.clear();

        const audio = document.getElementById('remote-audio');
        if (audio) {
            audio.srcObject = null;
        }

        const hadCall = call.callId !== null;

        Object.assign(call, { state: IDLE, callId: null, isCaller: false, peerId: null, peerName: '', startedAt: null });

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

    resumeRememberedCall();
}
