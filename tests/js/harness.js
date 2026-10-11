/**
 * Fakes for everything resources/js/calls.js talks to: Alpine, Echo (Reverb), fetch,
 * the microphone, sessionStorage and the DOM. WebRTC and tones are mocked by the test file.
 */
import { vi } from 'vitest';

export function deferred() {
    let resolve;
    let reject;
    const promise = new Promise((res, rej) => {
        resolve = res;
        reject = rej;
    });

    return { promise, resolve, reject };
}

/**
 * Let pending promise callbacks (fetch, getUserMedia, awaits in calls.js) run.
 */
export async function flush() {
    for (let i = 0; i < 25; i++) {
        await Promise.resolve();
    }
}

/**
 * A tiny stand-in for Alpine: stores are proxies that re-run every effect on any change.
 */
export function fakeAlpine() {
    const stores = {};
    const effects = [];
    const runEffects = () => effects.forEach((effect) => effect());

    return {
        store(name, value) {
            if (value !== undefined) {
                stores[name] = new Proxy(value, {
                    set(target, key, newValue) {
                        const changed = target[key] !== newValue;
                        target[key] = newValue;

                        if (changed) {
                            runEffects();
                        }

                        return true;
                    },
                });
            }

            return stores[name];
        },
        effect(callback) {
            effects.push(callback);
            callback();
        },
    };
}

class FakeChannel {
    constructor(name) {
        this.name = name;
        this.listeners = {};
        this.whisperListeners = {};
        this.whispers = [];
        this.subscribedCallbacks = [];
        this.presence = {};
    }

    listen(event, callback) {
        this.listeners[event] = callback;

        return this;
    }

    listenForWhisper(event, callback) {
        this.whisperListeners[event] = callback;

        return this;
    }

    whisper(event, payload) {
        this.whispers.push({ event, payload });

        return this;
    }

    error() {
        return this;
    }

    subscribed(callback) {
        this.subscribedCallbacks.push(callback);

        return this;
    }

    here(callback) {
        this.presence.here = callback;

        return this;
    }

    joining(callback) {
        this.presence.joining = callback;

        return this;
    }

    leaving(callback) {
        this.presence.leaving = callback;

        return this;
    }

    /** Simulate Reverb confirming the subscription. */
    confirmSubscription() {
        this.subscribedCallbacks.forEach((callback) => callback());
    }

    /** Simulate a server event (e.g. '.call.initiated') arriving. */
    receive(event, payload) {
        this.listeners[event](payload);
    }

    /** Simulate the other browser whispering to us. */
    receiveWhisper(event, payload) {
        return this.whisperListeners[event]?.(payload);
    }
}

export function fakeEcho() {
    const connectionListeners = {};

    const echo = {
        channels: {},
        left: [],
        private(name) {
            echo.channels[name] ??= new FakeChannel(name);

            return echo.channels[name];
        },
        join(name) {
            return echo.private(name);
        },
        leave(name) {
            echo.left.push(name);
            delete echo.channels[name];
        },
        socketId: () => 'socket-1',
        connector: {
            pusher: {
                connection: {
                    bind: (event, callback) => (connectionListeners[event] = callback),
                },
            },
        },
        /** Simulate Pusher's connection moving to a new state. */
        changeConnectionState(current) {
            connectionListeners.state_change?.({ current });
        },
    };

    return echo;
}

/**
 * fetch() fake. Register responses per "METHOD /path"; a response can be a deferred
 * to hold the request open. Unregistered requests answer 200 {}.
 */
export function fakeServer() {
    const routes = {};
    const requests = [];

    const fetch = vi.fn(async (url, { method, body }) => {
        const key = `${method} ${url}`;
        requests.push({ key, body: body ? JSON.parse(body) : undefined });

        let reply = routes[key] ?? { status: 200, body: {} };

        if (reply.promise) {
            reply = await reply.promise;
        }

        return {
            ok: reply.status >= 200 && reply.status < 300,
            status: reply.status,
            json: async () => reply.body,
        };
    });

    return {
        fetch,
        requests,
        respond(key, status, body = {}) {
            routes[key] = { status, body };
        },
        hold(key) {
            const pending = deferred();
            routes[key] = pending;

            return pending;
        },
        sent: (key) => requests.some((request) => request.key === key),
    };
}

export function fakeStream() {
    const track = { stop: vi.fn() };

    return { track, getTracks: () => [track] };
}

/**
 * Install the browser globals calls.js expects. Returns handles to drive them.
 */
export function installBrowser({ echo, server }) {
    const storage = new Map();
    const microphone = { next: null };

    globalThis.window = globalThis;
    window.Echo = echo;
    window.iceServers = [{ urls: 'stun:stun.example.com' }];
    window.Livewire = { dispatch: vi.fn() };
    globalThis.fetch = server.fetch;
    globalThis.sessionStorage = {
        getItem: (key) => storage.get(key) ?? null,
        setItem: (key, value) => storage.set(key, String(value)),
        removeItem: (key) => storage.delete(key),
    };
    globalThis.document = {
        title: 'Contacts - Wifone',
        querySelector: () => ({ content: 'csrf-token' }),
        getElementById: () => ({ srcObject: null, play: () => Promise.resolve() }),
    };

    Object.defineProperty(globalThis, 'navigator', {
        configurable: true,
        value: {
            mediaDevices: {
                // Each call resolves with a fresh stream unless the test holds or fails the next one.
                getUserMedia: vi.fn(() => {
                    const pending = microphone.next;
                    microphone.next = null;

                    return pending ? pending.promise : Promise.resolve(fakeStream());
                }),
            },
        },
    });

    return {
        storage,
        /** Hold the next microphone request open until the test resolves or rejects it. */
        holdMicrophone() {
            microphone.next = deferred();

            return microphone.next;
        },
    };
}
