/**
 * Thin wrapper around a single audio-only RTCPeerConnection.
 *
 * Signalling is left to the caller: `onSignal(type, payload)` is invoked with
 * 'offer' | 'answer' | 'ice' messages that must reach the remote peer, and the
 * remote peer's messages are fed back in via handleOffer / handleAnswer / addIceCandidate.
 */
export class AudioPeer {
    /**
     * @param {object} options
     * @param {MediaStream} options.localStream
     * @param {RTCIceServer[]} options.iceServers
     * @param {HTMLAudioElement|null} options.audioElement
     * @param {(type: string, payload: object) => void} options.onSignal
     * @param {(state: RTCPeerConnectionState) => void} options.onStateChange
     */
    constructor({ localStream, iceServers, audioElement, onSignal, onStateChange }) {
        this.pendingCandidates = [];
        this.onSignal = onSignal;

        this.pc = new RTCPeerConnection({ iceServers });

        for (const track of localStream.getTracks()) {
            this.pc.addTrack(track, localStream);
        }

        this.pc.onicecandidate = ({ candidate }) => {
            if (candidate) {
                this.onSignal('ice', candidate.toJSON());
            }
        };

        this.pc.ontrack = ({ streams: [stream] }) => {
            if (audioElement) {
                audioElement.srcObject = stream;
                audioElement.play().catch((error) => console.warn('Remote audio playback blocked:', error));
            }
        };

        this.pc.onconnectionstatechange = () => onStateChange(this.pc.connectionState);
    }

    async createOffer() {
        const offer = await this.pc.createOffer();
        await this.pc.setLocalDescription(offer);
        this.onSignal('offer', { type: offer.type, sdp: offer.sdp });
    }

    /**
     * @param {RTCSessionDescriptionInit} offer
     */
    async handleOffer(offer) {
        await this.pc.setRemoteDescription(offer);
        await this.flushPendingCandidates();

        const answer = await this.pc.createAnswer();
        await this.pc.setLocalDescription(answer);
        this.onSignal('answer', { type: answer.type, sdp: answer.sdp });
    }

    /**
     * @param {RTCSessionDescriptionInit} answer
     */
    async handleAnswer(answer) {
        await this.pc.setRemoteDescription(answer);
        await this.flushPendingCandidates();
    }

    /**
     * Candidates can arrive before the remote description is set; queue them until it is.
     *
     * @param {RTCIceCandidateInit} candidate
     */
    async addIceCandidate(candidate) {
        if (!this.pc.remoteDescription) {
            this.pendingCandidates.push(candidate);

            return;
        }

        await this.pc.addIceCandidate(candidate);
    }

    async flushPendingCandidates() {
        const candidates = this.pendingCandidates;
        this.pendingCandidates = [];

        for (const candidate of candidates) {
            await this.pc.addIceCandidate(candidate);
        }
    }

    close() {
        this.pc.onicecandidate = null;
        this.pc.ontrack = null;
        this.pc.onconnectionstatechange = null;
        this.pc.close();
    }
}
