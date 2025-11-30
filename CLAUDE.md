# Project Notes for Claude

## Database Rules

- **NEVER** flush, truncate, or delete users from the database unless specifically instructed to do so
- When running tests that use `RefreshDatabase`, be aware this will wipe all data including users
- If tests must be run, warn the user that the database will be reset

## Admin User Setup

To create an admin user:
```php
use App\Enums\UserRole;

$user = App\Models\User::create([
    'name' => 'Admin',
    'email' => 'admin@example.com',
    'password' => bcrypt('password'),
    'email_verified_at' => now(),
    'role' => UserRole::Admin,
]);
```
