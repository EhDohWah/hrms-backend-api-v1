# Quick Test Script - Password Reset

Run these commands to quickly test the password reset functionality.

## Setup Test User

```bash
cd d:\HR_management_system\3. Implementation\hrms-backend-api-v1

# Create test user via Tinker
php artisan tinker
```

```php
// In Tinker - Create test user
$user = new App\Models\User();
$user->name = 'Test User';
$user->email = 'test@hrms.local';
$user->password = Hash::make('OldPassword123!');
$user->status = 'active';
$user->save();
echo "Test user created: test@hrms.local / OldPassword123!\n";
exit
```

## Test 1: Request Password Reset

```bash
# From backend directory
curl -X POST http://localhost:8000/api/v1/forgot-password \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"test@hrms.local\"}"
```

**Expected Response:**
```json
{
  "success": true,
  "message": "We have emailed your password reset link!"
}
```

## Test 2: Check Email in Logs

```bash
# View last 50 lines of log
tail -n 50 storage/logs/laravel.log | grep -A 20 "reset-password"
```

**Look for:** A line containing the reset URL with token

## Test 3: Extract Token and Test Reset

```bash
# Replace TOKEN and EMAIL with values from log
curl -X POST http://localhost:8000/api/v1/reset-password \
  -H "Content-Type: application/json" \
  -d "{
    \"token\":\"YOUR_64_CHAR_TOKEN_HERE\",
    \"email\":\"test@hrms.local\",
    \"password\":\"NewPassword123!\",
    \"password_confirmation\":\"NewPassword123!\"
  }"
```

**Expected Response:**
```json
{
  "success": true,
  "message": "Your password has been reset successfully!"
}
```

## Test 4: Login with New Password

```bash
curl -X POST http://localhost:8000/api/v1/login \
  -H "Content-Type: application/json" \
  -d "{
    \"email\":\"test@hrms.local\",
    \"password\":\"NewPassword123!\"
  }"
```

**Expected Response:**
```json
{
  "success": true,
  "access_token": "...",
  "token_type": "Bearer",
  "expires_in": 21600,
  "user": {...}
}
```

## Test 5: Rate Limiting

```bash
# Run this 4 times quickly
for i in {1..4}; do
  echo "Request $i:"
  curl -X POST http://localhost:8000/api/v1/forgot-password \
    -H "Content-Type: application/json" \
    -d "{\"email\":\"test@hrms.local\"}"
  echo "\n"
done
```

**Expected:** 4th request should return 429 status with rate limit message

## Test 6: Invalid Token

```bash
curl -X POST http://localhost:8000/api/v1/reset-password \
  -H "Content-Type: application/json" \
  -d "{
    \"token\":\"invalid_token_12345\",
    \"email\":\"test@hrms.local\",
    \"password\":\"NewPassword123!\",
    \"password_confirmation\":\"NewPassword123!\"
  }"
```

**Expected Response:**
```json
{
  "success": false,
  "message": "Invalid reset token format.",
  "errors": {
    "token": ["Invalid reset token format."]
  }
}
```

## Test 7: Expired Token

```bash
# First, create a token and manually expire it
php artisan tinker
```

```php
// In Tinker - Expire token
DB::table('password_reset_tokens')
  ->where('email', 'test@hrms.local')
  ->update(['created_at' => now()->subHours(2)]);
exit
```

Then try to use the old token - should get "expired" error.

## Test 8: Check Database State

```bash
php artisan tinker
```

```php
// Check password reset tokens
DB::table('password_reset_tokens')->get();

// Check user password was updated
$user = User::where('email', 'test@hrms.local')->first();
echo "User password hash: " . $user->password . "\n";

// Check personal access tokens (should be empty after reset)
DB::table('personal_access_tokens')
  ->where('tokenable_id', $user->id)
  ->get();
  
exit
```

## Cleanup

```bash
php artisan tinker
```

```php
// Delete test user
User::where('email', 'test@hrms.local')->delete();

// Clear rate limiter cache
Artisan::call('cache:clear');

exit
```

## Frontend Testing

1. Open browser to `http://localhost:8080/forgot-password`
2. Enter: `test@hrms.local`
3. Check log for token
4. Navigate to reset URL from log
5. Enter new password: `NewPassword123!`
6. Submit and verify redirect to login
7. Login with new password

## Verification Checklist

- [ ] Forgot password request succeeds (200)
- [ ] Email logged with reset link
- [ ] Token is 64 characters
- [ ] Reset password succeeds (200)
- [ ] Old password no longer works
- [ ] New password allows login
- [ ] Rate limiting works (429 on 4th request)
- [ ] Invalid tokens rejected (422)
- [ ] Expired tokens rejected (400)
- [ ] All tokens revoked after reset

---

**All tests passing?** ✅ Password reset is working correctly!
