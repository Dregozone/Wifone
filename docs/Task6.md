# Task 6 – Online Users Livewire Component

## Goal
Build a Livewire component that displays the list of all other registered users and provides a "Call" button for each. This component will be mounted on the dashboard page.

## Steps

1. Generate the component:
   ```
   php artisan make:livewire OnlineUsers --no-interaction
   ```

2. Component class (`app/Livewire/OnlineUsers.php`):
   - `mount()` — load all users except the authenticated user, ordered by name
   - `$onlineUserIds` (array) — updated by JavaScript via `$dispatch` or `$set` once presence channel data arrives
   - No polling needed — presence channel JS will push updates (see Task 9)

3. Component view (`resources/views/livewire/online-users.blade.php`):
   - Render a list/table of users, each row showing:
     - User name
     - Online indicator (green dot if `in_array($user->id, $onlineUserIds)`)
     - "Call" button (disabled if user is offline or if a call is already active)
   - Use Flux UI components (`<flux:table>`, `<flux:badge>`, `<flux:button>`) for styling
   - The "Call" button should invoke a JavaScript function `initiateCall(userId, userName)` via `onclick` — this JS is implemented in Task 8/9

4. Mount the component on the dashboard view (`resources/views/dashboard.blade.php`):
   - Replace placeholder content with `<livewire:online-users />`

## Acceptance Criteria
- Dashboard shows the list of users with name and a "Call" button.
- Online indicator is visible per user.

## Tests
Write `tests/Feature/Livewire/OnlineUsersTest.php` (Pest) that:
- Renders the component as an authenticated user
- Asserts other users appear in the rendered output
- Asserts the authenticated user does not appear in the list
