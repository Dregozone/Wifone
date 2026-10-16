import { describe, expect, it } from 'vitest';
import { fakeAlpine, fakeEcho } from './harness.js';
import { registerInviteStore } from '../../resources/js/invites.js';

describe('the pending invites badge', () => {
    it('starts from the count the page was loaded with', () => {
        const Alpine = fakeAlpine();
        registerInviteStore(Alpine, fakeEcho(), 1, 2);

        expect(Alpine.store('invites').pendingCount).toBe(2);
    });

    it('updates live when an invite is sent, answered or cancelled', () => {
        const Alpine = fakeAlpine();
        const echo = fakeEcho();
        registerInviteStore(Alpine, echo, 1, 0);

        echo.channels['user.1'].receive('.invites.updated', { pendingCount: 3 });

        expect(Alpine.store('invites').pendingCount).toBe(3);
    });
});
