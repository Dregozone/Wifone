/**
 * Register the `invites` Alpine store: the number of call invites waiting for the user's
 * answer, shown as a badge in the navigation and kept current over Reverb.
 *
 * @param {import('alpinejs').Alpine} Alpine
 * @param {import('laravel-echo').default} Echo
 * @param {number} authUserId
 * @param {number} pendingCount The count when the page was loaded.
 */
export function registerInviteStore(Alpine, Echo, authUserId, pendingCount = 0) {
    Alpine.store('invites', { pendingCount });

    Echo.private(`user.${authUserId}`).listen('.invites.updated', (event) => {
        Alpine.store('invites').pendingCount = event.pendingCount;
    });
}

/**
 * A scheduled time in the viewer's own time zone, e.g. "Mon 20 Oct, 19:00".
 *
 * @param {string} isoTime
 */
export function formatLocalTime(isoTime) {
    return new Intl.DateTimeFormat(undefined, {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(isoTime));
}
