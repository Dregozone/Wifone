let peerConnection = null;
let localStream = null;
let remoteStream = null;
let remoteUserId = null;

/**
 * Build a new RTCPeerConnection using the ICE servers injected by the Blade layout.
 *
 * @returns {RTCPeerConnection}
 */
function createPeerConnection() {
    const iceServers = window.iceServers ?? [{ urls: 'stun:stun.l.google.com:19302' }];

    const pc = new RTCPeerConnection({ iceServers });

    pc.onicecandidate = ({ candidate }) => {
        if (candidate && remoteUserId !== null) {
            fetch('/call/candidate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({
                    to_user_id: remoteUserId,
                    candidate: candidate.toJSON(),
                }),
            }).catch((err) => console.error('Failed to send ICE candidate:', err));
        }
    };

    pc.ontrack = ({ streams }) => {
        const [stream] = streams;
        remoteStream = stream;

        const audio = document.getElementById('remote-audio');
        if (audio) {
            audio.srcObject = stream;
        }
    };

    pc.onconnectionstatechange = () => {
        console.log('WebRTC connection state:', pc.connectionState);

        if (pc.connectionState === 'failed' || pc.connectionState === 'closed') {
            hangUp(remoteUserId);
        }
    };

    return pc;
}

/**
 * Request microphone access, create an offer, and POST it to /call/offer.
 *
 * @param {number} toUserId
 */
export async function startCall(toUserId) {
    remoteUserId = toUserId;

    try {
        localStream = await navigator.mediaDevices.getUserMedia({ audio: true, video: false });
    } catch (err) {
        if (err.name === 'NotAllowedError') {
            alert('Microphone access was denied. Please allow microphone access to make calls.');
        } else {
            console.error('getUserMedia error:', err);
        }
        return;
    }

    peerConnection = createPeerConnection();

    for (const track of localStream.getTracks()) {
        peerConnection.addTrack(track, localStream);
    }

    const offer = await peerConnection.createOffer();
    await peerConnection.setLocalDescription(offer);

    await fetch('/call/offer', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        },
        body: JSON.stringify({
            to_user_id: toUserId,
            offer: { type: offer.type, sdp: offer.sdp },
        }),
    });
}

/**
 * Set the remote SDP offer, create an answer, and POST it to /call/answer.
 *
 * @param {number} fromUserId
 * @param {{ type: string, sdp: string }} offer
 */
export async function handleOffer(fromUserId, offer) {
    remoteUserId = fromUserId;

    try {
        localStream = await navigator.mediaDevices.getUserMedia({ audio: true, video: false });
    } catch (err) {
        if (err.name === 'NotAllowedError') {
            alert('Microphone access was denied. Please allow microphone access to answer calls.');
        } else {
            console.error('getUserMedia error:', err);
        }
        return;
    }

    peerConnection = createPeerConnection();

    for (const track of localStream.getTracks()) {
        peerConnection.addTrack(track, localStream);
    }

    await peerConnection.setRemoteDescription(new RTCSessionDescription(offer));

    const answer = await peerConnection.createAnswer();
    await peerConnection.setLocalDescription(answer);

    await fetch('/call/answer', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        },
        body: JSON.stringify({
            to_user_id: fromUserId,
            answer: { type: answer.type, sdp: answer.sdp },
        }),
    });
}

/**
 * Set the remote SDP answer received from the callee.
 *
 * @param {{ type: string, sdp: string }} answer
 */
export async function handleAnswer(answer) {
    if (!peerConnection) {
        return;
    }

    await peerConnection.setRemoteDescription(new RTCSessionDescription(answer));
}

/**
 * Add a received ICE candidate to the peer connection.
 *
 * @param {RTCIceCandidateInit} candidate
 */
export async function addIceCandidate(candidate) {
    if (!peerConnection) {
        return;
    }

    await peerConnection.addIceCandidate(new RTCIceCandidate(candidate));
}

/**
 * Close the peer connection, stop mic tracks, and POST to /call/end.
 *
 * @param {number|null} toUserId
 */
export function hangUp(toUserId) {
    if (localStream) {
        for (const track of localStream.getTracks()) {
            track.stop();
        }
        localStream = null;
    }

    if (peerConnection) {
        peerConnection.onicecandidate = null;
        peerConnection.ontrack = null;
        peerConnection.onconnectionstatechange = null;
        peerConnection.close();
        peerConnection = null;
    }

    remoteStream = null;

    const userId = toUserId ?? remoteUserId;
    remoteUserId = null;

    if (userId !== null) {
        fetch('/call/end', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify({ to_user_id: userId }),
        }).catch((err) => console.error('Failed to send hang-up:', err));
    }
}

/**
 * Return the remote MediaStream (useful for debugging).
 *
 * @returns {MediaStream|null}
 */
export function getRemoteStream() {
    return remoteStream;
}
