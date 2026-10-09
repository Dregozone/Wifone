/**
 * Call tones, synthesised with Web Audio so there are no sound files to load or cache.
 *
 *   ringtone: the receiver's incoming-call ring (plus vibration on phones)
 *   ringback: what the caller hears while the other side is ringing
 *
 * Browsers may refuse to start audio before the page has had a click or tap; the call
 * still rings visually then, so failures are ignored.
 */
const PATTERNS = {
    ringtone: { frequencies: [523.25, 659.25], beeps: [0, 0.45], beepLength: 0.35, volume: 0.18, every: 2.5 },
    ringback: { frequencies: [440, 480], beeps: [0], beepLength: 1.5, volume: 0.06, every: 4 },
};

const VIBRATION = [400, 200, 400];

let context = null;
let repeatTimer = null;
let playing = null;
let sounding = [];

function audioContext() {
    const AudioContext = window.AudioContext ?? window.webkitAudioContext;

    if (!AudioContext) {
        return null;
    }

    context ??= new AudioContext();
    context.resume?.().catch(() => {});

    return context;
}

function playOnce(pattern) {
    const ctx = audioContext();

    if (!ctx) {
        return;
    }

    const start = ctx.currentTime;

    for (const offset of pattern.beeps) {
        const gain = ctx.createGain();
        gain.connect(ctx.destination);
        gain.gain.setValueAtTime(0, start + offset);
        gain.gain.linearRampToValueAtTime(pattern.volume, start + offset + 0.02);
        gain.gain.setValueAtTime(pattern.volume, start + offset + pattern.beepLength - 0.05);
        gain.gain.linearRampToValueAtTime(0, start + offset + pattern.beepLength);

        for (const frequency of pattern.frequencies) {
            const oscillator = ctx.createOscillator();
            oscillator.frequency.value = frequency;
            oscillator.connect(gain);
            oscillator.start(start + offset);
            oscillator.stop(start + offset + pattern.beepLength);
            oscillator.onended = () => (sounding = sounding.filter((sound) => sound !== oscillator));
            sounding.push(oscillator);
        }
    }
}

/**
 * Start repeating a tone, replacing any tone already playing.
 *
 * @param {'ringtone'|'ringback'} name
 */
export function playTone(name) {
    if (playing === name) {
        return;
    }

    stopTone();
    playing = name;

    const pattern = PATTERNS[name];
    const ring = () => {
        try {
            playOnce(pattern);
        } catch {
            // Audio unavailable: the call UI still shows the call.
        }

        if (name === 'ringtone') {
            navigator.vibrate?.(VIBRATION);
        }
    };

    ring();
    repeatTimer = setInterval(ring, pattern.every * 1000);
}

export function stopTone() {
    if (playing === 'ringtone') {
        navigator.vibrate?.(0);
    }

    clearInterval(repeatTimer);
    repeatTimer = null;
    playing = null;

    // Cut off a beep that is still sounding, so the tone stops the moment the call moves on.
    for (const oscillator of sounding) {
        try {
            oscillator.stop();
        } catch {
            // Already stopped.
        }
    }
    sounding = [];
}
