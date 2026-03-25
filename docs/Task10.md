# Task 10 – Tests & Final Verification

## Goal
Write the full test suite, run all tests, fix any failures, and do a final end-to-end smoke check.

## Tests to Write / Verify

### Unit Tests
| File | Covers |
|------|--------|
| `tests/Unit/CallModelTest.php` | Call model relationships (from Task 2) |

### Feature Tests
| File | Covers |
|------|--------|
| `tests/Feature/Events/BroadcastEventsTest.php` | All 7 events broadcast on correct channels (Task 3) |
| `tests/Feature/ChannelAuthTest.php` | Channel authorisation rules (Task 4) |
| `tests/Feature/CallControllerTest.php` | All 7 controller endpoints (Task 5) |
| `tests/Feature/Livewire/OnlineUsersTest.php` | OnlineUsers component render (Task 6) |
| `tests/Feature/DashboardUiTest.php` | DOM structure — modal, audio element, in-call UI (Task 7) |

## Steps

1. Ensure all test files are created (scaffold any missing ones from previous tasks).

2. Run the full suite:
   ```
   php artisan test --compact
   ```

3. Fix any failing tests or underlying implementation issues uncovered.

4. Run Pint to ensure code style is clean:
   ```
   vendor/bin/pint --dirty --format agent
   ```

5. Run the suite again after Pint to confirm no regressions.

## Manual End-to-End Smoke Test (two browser tabs)
1. Start the dev server: `composer run dev` (starts Laravel + Vite + Reverb together)
2. Open two browser tabs; log in as two different users
3. Verify both users appear in each other's online user list
4. From Tab A, click **Call** on User B
5. Verify Tab B shows the incoming call modal with User A's name
6. Click **Accept** in Tab B
7. Verify audio is established (both tabs should be able to hear the other side if using a real microphone, or at minimum the connection state should reach `connected`)
8. Click **Hang Up** in either tab
9. Verify the in-call UI disappears in both tabs

## Acceptance Criteria
- All automated tests pass.
- No Pint formatting errors.
- Manual smoke test completes without JavaScript console errors.
- WebRTC `connectionState` reaches `connected` in the browser for a same-machine loopback test.
