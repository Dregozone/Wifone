/**
 * When Wifone is shown in a frame by one of the sites it allows (config/wifone.php, e.g. the
 * Life OS game), tell that site about your calls, and let it answer, decline or hang up for you.
 *
 * Out (to the site around the frame):
 *   { source: 'wifone', type: 'ready' }                       signed in and listening for calls
 *   { source: 'wifone', type: 'call', state, callId, peerName, isCaller }   whenever the call changes
 *     state: idle | outgoing | incoming | connecting | active | reconnecting
 * In (from an allowed site only):
 *   { source: 'life-os', type: 'answer' | 'decline' | 'hangup' }
 *
 * Messages go only to the allowed origins (postMessage drops them anywhere else), and nothing is
 * accepted from any other origin. Visiting Wifone directly, none of this runs.
 */
export function bridgeToEmbedder(Alpine) {
    if (window.parent === window) {
        return;
    }

    const allowed = (document.querySelector('meta[name="wifone-embed-origins"]')?.content ?? '').split(' ').filter(Boolean);

    if (allowed.length === 0) {
        return;
    }

    const send = (message) => {
        for (const origin of allowed) {
            window.parent.postMessage({ source: 'wifone', ...message }, origin);
        }
    };

    const call = Alpine.store('call');

    Alpine.effect(() => {
        send({ type: 'call', state: call.state, callId: call.callId, peerName: call.peerName, isCaller: call.isCaller });
    });

    window.addEventListener('message', (event) => {
        if (!allowed.includes(event.origin) || event.data?.source !== 'life-os') {
            return;
        }

        if (event.data.type === 'answer') {
            call.accept();
        } else if (event.data.type === 'decline') {
            call.reject();
        } else if (event.data.type === 'hangup') {
            call.hangUp();
        }
    });

    send({ type: 'ready' });
}
