# Task 7 – Call UI: Incoming Call Modal & In-Call Overlay

## Goal
Add the remaining UI elements to the dashboard: an incoming call modal and an in-call overlay with a hang-up button. These are driven entirely by JavaScript state (Task 9) but the HTML structures must exist in the DOM.

## Steps

1. Create a `resources/views/partials/call-ui.blade.php` partial (or include directly in the layout) containing:

   ### Incoming Call Modal
   - Use `<flux:modal>` with an `id` or Alpine.js `x-data` / `x-show` to control visibility
   - Shows: caller name (`<span id="caller-name">`)
   - Buttons: **Accept** (calls `acceptCall()`) and **Reject** (calls `rejectCall()`)
   - Hidden by default; shown by JavaScript when `CallInitiated` fires

   ### In-Call Overlay / Banner
   - A fixed banner or card (hidden by default) that appears once a call is active
   - Shows: "In call with [name]" label
   - **Hang Up** button (calls `endCall()`)
   - Hidden by default; shown by JavaScript on call establishment

   ### Remote Audio Element
   - `<audio id="remote-audio" autoplay playsinline></audio>` — hidden, used by WebRTC to play the remote stream

2. Include the partial in the main app layout (`resources/views/layouts/app.blade.php`) so it is available on every auth page, or add it specifically to the dashboard view.

3. IDs / data attributes required by the JavaScript module (Task 8):
   - `#incoming-call-modal` — the modal wrapper element
   - `#caller-name` — span for the caller's display name
   - `#in-call-ui` — the in-call banner/overlay wrapper
   - `#in-call-with` — span showing who the call is with
   - `#remote-audio` — the audio element

## Acceptance Criteria
- Dashboard page renders without errors.
- Incoming call modal is in the DOM but visually hidden on load.
- In-call overlay is in the DOM but visually hidden on load.
- `#remote-audio` element is present.

## Tests
Write `tests/Feature/DashboardUiTest.php` (Pest) that:
- Visits `/dashboard` as an authenticated user
- Asserts `#incoming-call-modal` is present in the HTML
- Asserts `#remote-audio` is present in the HTML
- Asserts `#in-call-ui` is present in the HTML
