import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { fakeAlpine, fakeEcho, fakeServer, fakeStream, flush, installBrowser } from './harness.js';

const peers = vi.hoisted(() => []);

vi.mock('../../resources/js/webrtc.js', () => ({
    AudioPeer: class {
        constructor(options) {
            this.options = options;
            this.createOffer = vi.fn(async () => {});
            this.handleOffer = vi.fn(async () => {});
            this.handleAnswer = vi.fn(async () => {});
            this.addIceCandidate = vi.fn(async () => {});
            this.close = vi.fn();
            peers.push(this);
        }

        /** Simulate RTCPeerConnection reporting a new connection state. */
        changeState(state) {
            this.options.onStateChange(state);
        }
    },
}));

vi.mock('../../resources/js/tones.js', () => ({
    playTone: vi.fn(),
    stopTone: vi.fn(),
}));

const { registerCallStores } = await import('../../resources/js/calls.js');
const { playTone, stopTone } = await import('../../resources/js/tones.js');

const ME = 1;
const SAM = { id: 2, name: 'Sam Rivers' };

let echo;
let server;
let browser;
let call;
let presence;

function boot() {
    const Alpine = fakeAlpine();
    registerCallStores(Alpine, echo, ME);
    call = Alpine.store('call');
    presence = Alpine.store('presence');
}

const myChannel = () => echo.channels[`calls.${ME}`];
const onlineChannel = () => echo.channels.online;

beforeEach(() => {
    vi.useFakeTimers();
    vi.spyOn(console, 'error').mockImplementation(() => {});
    vi.spyOn(console, 'warn').mockImplementation(() => {});
    vi.spyOn(console, 'debug').mockImplementation(() => {});
    peers.length = 0;

    echo = fakeEcho();
    server = fakeServer();
    browser = installBrowser({ echo, server });
});

afterEach(() => {
    vi.useRealTimers();
});

/**
 * Get to the point where Sam is ringing us.
 */
function ringFromSam(callId = 7) {
    myChannel().receive('.call.initiated', { callId, callerId: SAM.id, callerName: SAM.name });
}

/**
 * Get to a connected call where we are the caller.
 */
async function connectedOutgoingCall(callId = 7) {
    server.respond('POST /calls', 201, { callId });
    call.start(SAM.id, SAM.name);
    await flush();
    myChannel().receive('.call.accepted', { callId });
    await echo.channels[`call.${callId}`].receiveWhisper('offer', { type: 'offer', sdp: 'v=0' });
    peers.at(-1).changeState('connected');
}

describe('making a call', () => {
    beforeEach(boot);

    it('rings the other person and listens for their offer', async () => {
        server.respond('POST /calls', 201, { callId: 7 });

        call.start(SAM.id, SAM.name);
        await flush();

        expect(server.requests[0]).toEqual({ key: 'POST /calls', body: { receiver_id: SAM.id } });
        expect(call.state).toBe('outgoing');
        expect(call.callId).toBe(7);
        expect(echo.channels['call.7']).toBeDefined();
        expect(browser.storage.get('wifone.callId')).toBe('7');
        expect(playTone).toHaveBeenLastCalledWith('ringback');
    });

    it('does nothing when already in a call', async () => {
        await connectedOutgoingCall();
        const requestsBefore = server.requests.length;

        call.start(3, 'Alex');
        await flush();

        expect(server.requests).toHaveLength(requestsBefore);
        expect(call.peerId).toBe(SAM.id);
    });

    it('releases the microphone and never rings if cancelled while the mic prompt is open', async () => {
        const microphone = browser.holdMicrophone();

        call.start(SAM.id, SAM.name);
        call.hangUp();
        const stream = fakeStream();
        microphone.resolve(stream);
        await flush();

        expect(stream.track.stop).toHaveBeenCalled();
        expect(server.sent('POST /calls')).toBe(false);
        expect(call.state).toBe('idle');
    });

    it('cancels the call on the server if hung up while it was being created', async () => {
        const creating = server.hold('POST /calls');

        call.start(SAM.id, SAM.name);
        await flush();
        call.hangUp();
        creating.resolve({ status: 201, body: { callId: 9 } });
        await flush();

        expect(server.sent('POST /calls/9/end')).toBe(true);
        expect(echo.channels['call.9']).toBeUndefined();
        expect(call.state).toBe('idle');
        expect(call.callId).toBeNull();
    });

    it('shows the server\'s reason when the call is refused', async () => {
        server.respond('POST /calls', 409, { message: 'Sam Rivers is on another call.' });

        call.start(SAM.id, SAM.name);
        await flush();

        expect(call.state).toBe('idle');
        expect(call.notice).toBe('Sam Rivers is on another call.');
        expect(stopTone).toHaveBeenCalled();
    });

    it('explains a denied microphone without contacting the server', async () => {
        browser.holdMicrophone().reject(Object.assign(new Error('denied'), { name: 'NotAllowedError' }));

        call.start(SAM.id, SAM.name);
        await flush();

        expect(server.requests).toHaveLength(0);
        expect(call.notice).toBe('Microphone access was denied. Allow it in your browser to make calls.');
    });

    it('gives up after 30 seconds without an answer', async () => {
        server.respond('POST /calls', 201, { callId: 7 });
        call.start(SAM.id, SAM.name);
        await flush();

        vi.advanceTimersByTime(30_000);

        expect(server.sent('POST /calls/7/end')).toBe(true);
        expect(call.state).toBe('idle');
        expect(call.notice).toBe("Sam Rivers didn't answer.");
    });

    it('tells the caller when the call is declined', async () => {
        server.respond('POST /calls', 201, { callId: 7 });
        call.start(SAM.id, SAM.name);
        await flush();

        myChannel().receive('.call.rejected', { callId: 7 });

        expect(call.state).toBe('idle');
        expect(call.notice).toBe('Sam Rivers declined the call.');
        expect(echo.left).toContain('call.7');
    });

    it('accepts the offer even if it arrives before call.accepted', async () => {
        server.respond('POST /calls', 201, { callId: 7 });
        call.start(SAM.id, SAM.name);
        await flush();

        await echo.channels['call.7'].receiveWhisper('offer', { type: 'offer', sdp: 'v=0' });

        expect(peers).toHaveLength(1);
        expect(peers[0].handleOffer).toHaveBeenCalledWith({ type: 'offer', sdp: 'v=0' });
    });

    it('shows the call as connected with a running timer', async () => {
        await connectedOutgoingCall();

        vi.advanceTimersByTime(65_000);

        expect(call.state).toBe('active');
        expect(call.durationLabel()).toBe('1:05');
        expect(stopTone).toHaveBeenCalled();
    });
});

describe('receiving a call', () => {
    beforeEach(boot);

    it('rings and shows who is calling in the tab title', () => {
        ringFromSam();

        expect(call.state).toBe('incoming');
        expect(call.peerName).toBe(SAM.name);
        expect(playTone).toHaveBeenLastCalledWith('ringtone');
        expect(document.title).toBe('📞 Sam Rivers is calling…');
    });

    it('answers, joins the call channel and sends the offer once subscribed', async () => {
        ringFromSam();

        call.accept();
        await flush();
        echo.channels['call.7'].confirmSubscription();
        await flush();

        expect(server.sent('POST /calls/7/accept')).toBe(true);
        expect(call.state).toBe('connecting');
        expect(peers).toHaveLength(1);
        expect(peers[0].createOffer).toHaveBeenCalled();
        expect(document.title).toBe('Contacts - Wifone');
        expect(stopTone).toHaveBeenCalled();
    });

    it('lets the caller\'s cancel win while the mic prompt is open', async () => {
        ringFromSam();
        const microphone = browser.holdMicrophone();

        call.accept();
        myChannel().receive('.call.ended', { callId: 7, status: 'missed' });
        const stream = fakeStream();
        microphone.resolve(stream);
        await flush();

        expect(stream.track.stop).toHaveBeenCalled();
        expect(server.sent('POST /calls/7/accept')).toBe(false);
        expect(call.state).toBe('idle');
        expect(call.notice).toBe('Missed call from Sam Rivers.');
    });

    it('explains when the call was gone by the time it was answered', async () => {
        ringFromSam();
        server.respond('POST /calls/7/accept', 409, { message: 'The call is no longer available.' });

        call.accept();
        await flush();

        expect(call.state).toBe('idle');
        expect(call.notice).toBe('The call is no longer available.');
        expect(echo.channels['call.7']).toBeUndefined();
    });

    it('declines the call when the microphone is unavailable', async () => {
        ringFromSam();
        browser.holdMicrophone().reject(Object.assign(new Error('none'), { name: 'NotFoundError' }));

        call.accept();
        await flush();

        expect(server.sent('POST /calls/7/reject')).toBe(true);
        expect(call.notice).toBe('No microphone was found. Connect one to make calls.');
    });

    it('declines', () => {
        ringFromSam();

        call.reject();

        expect(server.sent('POST /calls/7/reject')).toBe(true);
        expect(call.state).toBe('idle');
    });

    it('stops ringing quietly when answered on another device', () => {
        ringFromSam();

        myChannel().receive('.call.accepted', { callId: 7 });

        expect(call.state).toBe('idle');
        expect(call.notice).toBe('Answered on another device.');
    });

    it('stops ringing when declined on another device', () => {
        ringFromSam();

        myChannel().receive('.call.rejected', { callId: 7 });

        expect(call.state).toBe('idle');
        expect(call.notice).toBe('');
    });

    it('turns a second caller away without disturbing the current call', async () => {
        await connectedOutgoingCall(7);

        myChannel().receive('.call.initiated', { callId: 8, callerId: 3, callerName: 'Alex' });
        await flush();

        expect(server.sent('POST /calls/8/reject')).toBe(true);
        expect(call.callId).toBe(7);
        expect(call.state).toBe('active');
    });

    it('stops ringing if the caller vanished without cancelling', () => {
        ringFromSam();

        vi.advanceTimersByTime(35_000);

        expect(call.state).toBe('idle');
        expect(call.notice).toBe('Missed call from Sam Rivers.');
    });

    it('ignores events for other calls', () => {
        ringFromSam(7);

        myChannel().receive('.call.ended', { callId: 99, status: 'completed' });
        myChannel().receive('.call.accepted', { callId: 99 });

        expect(call.state).toBe('incoming');
    });
});

describe('during a call', () => {
    beforeEach(boot);

    it('ends when the other side hangs up', async () => {
        await connectedOutgoingCall();

        myChannel().receive('.call.ended', { callId: 7, status: 'completed' });

        expect(call.state).toBe('idle');
        expect(call.notice).toBe('Call ended.');
        expect(peers[0].close).toHaveBeenCalled();
        expect(browser.storage.has('wifone.callId')).toBe(false);
    });

    it('waits 20 seconds for a peer who went offline before hanging up', async () => {
        await connectedOutgoingCall();

        onlineChannel().presence.leaving({ id: SAM.id });
        expect(call.state).toBe('reconnecting');

        vi.advanceTimersByTime(19_000);
        expect(call.state).toBe('reconnecting');

        vi.advanceTimersByTime(1_000);
        expect(call.state).toBe('idle');
        expect(server.sent('POST /calls/7/end')).toBe(true);
        expect(call.notice).toBe('Sam Rivers went offline.');
    });

    it('recovers when the peer reloads and asks to rejoin', async () => {
        await connectedOutgoingCall();
        onlineChannel().presence.leaving({ id: SAM.id });

        echo.channels['call.7'].receiveWhisper('rejoin', {});
        await flush();
        peers.at(-1).changeState('connected');
        vi.advanceTimersByTime(30_000);

        expect(peers).toHaveLength(2);
        expect(peers[0].close).toHaveBeenCalled();
        expect(call.state).toBe('active');
    });

    it('asks for a fresh offer when the connection fails mid-call (caller side)', async () => {
        await connectedOutgoingCall();

        peers[0].changeState('failed');

        expect(call.state).toBe('reconnecting');
        expect(echo.channels['call.7'].whispers.map(({ event }) => event)).toContain('rejoin');
    });

    it('blames the network when the first connection attempt fails', async () => {
        server.respond('POST /calls', 201, { callId: 7 });
        call.start(SAM.id, SAM.name);
        await flush();
        myChannel().receive('.call.accepted', { callId: 7 });
        await echo.channels['call.7'].receiveWhisper('offer', { type: 'offer', sdp: 'v=0' });

        peers[0].changeState('failed');

        expect(call.state).toBe('idle');
        expect(call.notice).toBe('The connection failed. A TURN server may be needed on this network.');
    });

    it('ignores state changes from a connection it already replaced', async () => {
        await connectedOutgoingCall();
        echo.channels['call.7'].receiveWhisper('rejoin', {});
        await flush();

        peers[0].changeState('failed');

        expect(call.state).toBe('reconnecting');
        expect(echo.channels['call.7'].whispers.filter(({ event }) => event === 'rejoin')).toHaveLength(0);
    });

    it('sends heartbeats and ends when the server says the call is over', async () => {
        await connectedOutgoingCall();
        server.respond('POST /calls/7/heartbeat', 403);

        await vi.advanceTimersByTimeAsync(20_000);

        expect(server.sent('POST /calls/7/heartbeat')).toBe(true);
        expect(call.state).toBe('idle');
        expect(call.notice).toBe('Call ended.');
    });

    it('ignores a late offer after hanging up', async () => {
        await connectedOutgoingCall();
        const channel = echo.channels['call.7'];
        call.hangUp();

        await channel.receiveWhisper('offer', { type: 'offer', sdp: 'v=0' });

        expect(peers).toHaveLength(1);
    });

    it('survives a malformed ICE candidate', async () => {
        await connectedOutgoingCall();
        peers[0].addIceCandidate.mockRejectedValueOnce(new Error('bad candidate'));

        await echo.channels['call.7'].receiveWhisper('ice', { candidate: 'garbage' });
        await flush();

        expect(call.state).toBe('active');
    });
});

describe('after a page reload', () => {
    it('rejoins an active call and asks the peer for a fresh offer', async () => {
        browser.storage.set('wifone.callId', '7');
        server.respond('GET /calls/7', 200, {
            callId: 7,
            status: 'active',
            isCaller: true,
            peerId: SAM.id,
            peerName: SAM.name,
            startedAt: new Date(Date.now() - 60_000).toISOString(),
        });

        boot();
        await flush();
        echo.channels['call.7'].confirmSubscription();

        expect(call.state).toBe('reconnecting');
        expect(call.peerName).toBe(SAM.name);
        expect(echo.channels['call.7'].whispers.map(({ event }) => event)).toEqual(['rejoin']);
    });

    it('cancels a call it was still ringing out', async () => {
        browser.storage.set('wifone.callId', '7');
        server.respond('GET /calls/7', 200, { callId: 7, status: 'ringing', isCaller: true, peerId: SAM.id, peerName: SAM.name, startedAt: null });

        boot();
        await flush();

        expect(server.sent('POST /calls/7/end')).toBe(true);
        expect(call.state).toBe('idle');
        expect(browser.storage.has('wifone.callId')).toBe(false);
    });

    it('forgets a call that ended while the page was reloading', async () => {
        browser.storage.set('wifone.callId', '7');
        server.respond('GET /calls/7', 200, { callId: 7, status: 'completed', isCaller: true, peerId: SAM.id, peerName: SAM.name, startedAt: null });

        boot();
        await flush();

        expect(call.state).toBe('idle');
        expect(browser.storage.has('wifone.callId')).toBe(false);
    });
});

describe('presence and connection', () => {
    beforeEach(boot);

    it('tracks who is online', () => {
        onlineChannel().presence.here([{ id: 2 }, { id: 3 }]);
        onlineChannel().presence.joining({ id: 4 });
        onlineChannel().presence.leaving({ id: 3 });

        expect(presence.onlineIds).toEqual([2, 4]);
        expect(presence.isOnline(3)).toBe(false);
    });

    it('flags a lost connection to Reverb until it is back', () => {
        echo.changeConnectionState('connecting');
        expect(presence.connected).toBe(true);

        echo.changeConnectionState('unavailable');
        expect(presence.connected).toBe(false);

        echo.changeConnectionState('connected');
        expect(presence.connected).toBe(true);
    });
});
