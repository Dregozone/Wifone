# Task 2 – Calls Table Migration & Model

## Goal
Create the optional `calls` database table and a `Call` Eloquent model to record call history.

## Steps

1. Generate the migration and model together:
   ```
   php artisan make:model Call --migration --factory --no-interaction
   ```

2. The `calls` table schema should include:

   | Column       | Type              | Notes                              |
   |--------------|-------------------|------------------------------------|
   | id           | bigIncrements     |                                    |
   | caller_id    | foreignId → users | cascade delete                     |
   | receiver_id  | foreignId → users | cascade delete                     |
   | started_at   | timestamp, null   |                                    |
   | ended_at     | timestamp, null   |                                    |
   | status       | string            | enum: pending, completed, rejected, missed |
   | timestamps   | —                 |                                    |

3. On the `Call` model set:
   - `$fillable` for all columns
   - `caller()` and `receiver()` `belongsTo(User::class)` relationships
   - Cast `started_at` / `ended_at` to datetime

4. On the `User` model add:
   - `outgoingCalls()` hasMany relationship
   - `incomingCalls()` hasMany relationship

5. Run the migration:
   ```
   php artisan migrate
   ```

## Acceptance Criteria
- `php artisan migrate` runs cleanly.
- `calls` table exists with the correct columns.
- `Call` model relationships resolve without errors.

## Tests
Write a `tests/Unit/CallModelTest.php` (Pest) that:
- Creates a `Call` via the factory and asserts `caller` and `receiver` relations return `User` instances.
