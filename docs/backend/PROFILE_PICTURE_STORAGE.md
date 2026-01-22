# Profile Picture Storage Configuration

> **Document Version:** 1.1
> **Last Updated:** January 2026
> **Category:** Backend / File Storage

## Overview

This document describes the profile picture upload, storage, and retrieval system in the HRMS backend. It covers the Laravel storage configuration, file handling, and common issues with their solutions.

---

## Table of Contents

1. [Storage Architecture](#storage-architecture)
2. [Configuration](#configuration)
3. [API Endpoints](#api-endpoints)
4. [File Upload Flow](#file-upload-flow)
5. [Real-time Broadcasting](#real-time-broadcasting)
6. [Common Issues & Solutions](#common-issues--solutions)
7. [Deployment Checklist](#deployment-checklist)

---

## Storage Architecture

### Directory Structure

```
hrms-backend-api-v1/
├── storage/
│   └── app/
│       ├── private/          # Private files (default disk)
│       └── public/           # Publicly accessible files
│           └── profile_pictures/  # User profile pictures
├── public/
│   └── storage -> ../storage/app/public  # Symlink (REQUIRED)
```

### How It Works

1. **Upload**: Files are uploaded via the API and stored in `storage/app/public/profile_pictures/`
2. **Symlink**: Laravel creates a symlink `public/storage` → `storage/app/public`
3. **Access**: Files are served via `http://your-domain.com/storage/profile_pictures/{filename}`

---

## Configuration

### Environment Variables (`.env`)

```env
# Default filesystem disk (not used for profile pictures)
FILESYSTEM_DISK=local

# App URL - used for generating storage URLs
APP_URL=http://localhost:8000
```

### Filesystem Configuration (`config/filesystems.php`)

```php
'disks' => [
    'local' => [
        'driver' => 'local',
        'root' => storage_path('app/private'),
        'serve' => true,
        'throw' => false,
    ],

    'public' => [
        'driver' => 'local',
        'root' => storage_path('app/public'),
        'url' => env('APP_URL').'/storage',  // http://localhost:8000/storage
        'visibility' => 'public',
        'throw' => false,
    ],
],

'links' => [
    public_path('storage') => storage_path('app/public'),
],
```

### Key Points

- Profile pictures use the **`public`** disk explicitly (not the default disk)
- The `url` configuration determines the base URL for stored files
- The `links` array defines the symlink that must be created

---

## API Endpoints

### Update Profile Picture

```
POST /api/v1/user/profile-picture
```

**Request:**
- Content-Type: `multipart/form-data`
- Body: `profile_picture` (file, required, image, max 2MB)

**Response (Success):**
```json
{
    "success": true,
    "message": "Profile picture updated successfully",
    "data": {
        "profile_picture": "profile_pictures/A8bmeMNRSSyjnmhZVQkjsOjBZxUUW2Ron2G9uBCb.jpg",
        "url": "http://localhost:8000/storage/profile_pictures/A8bmeMNRSSyjnmhZVQkjsOjBZxUUW2Ron2G9uBCb.jpg"
    }
}
```

**Response (Validation Error):**
```json
{
    "message": "The profile picture field is required.",
    "errors": {
        "profile_picture": ["The profile picture field is required."]
    }
}
```

---

### Get Current User

```
GET /api/v1/user
```

**Headers:**
- `Authorization: Bearer {token}`

**Response (Success):**
```json
{
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "profile_picture": "profile_pictures/A8bmeMNRSSyjnmhZVQkjsOjBZxUUW2Ron2G9uBCb.jpg",
    "profile_picture_updated_at": 1705789200000,
    "roles": [
        {
            "id": 1,
            "name": "Admin"
        }
    ],
    "permissions": [
        "users.read",
        "users.create",
        "users.update",
        "users.delete"
    ]
}
```

**Important Notes:**
- This endpoint is used by the frontend to refresh user data after profile updates
- It returns the user object with roles and permissions
- The route is defined in `routes/api/admin.php` inside a `Route::prefix('user')` group
- The route path is `/` (not `/user`) to create `/api/v1/user` endpoint

**Route Definition:**
```php
// routes/api/admin.php
Route::prefix('user')->group(function () {
    // Get authenticated user with roles and permissions
    // Note: This creates /api/v1/user endpoint (not /api/v1/user/user)
    Route::get('/', [UserController::class, 'getUser']);

    // Self-profile update routes
    Route::post('/profile-picture', [UserController::class, 'updateProfilePicture']);
    Route::post('/username', [UserController::class, 'updateUsername']);
    Route::post('/password', [UserController::class, 'updatePassword']);
    Route::post('/email', [UserController::class, 'updateEmail']);
});
```

---

## File Upload Flow

### Controller Logic (`UserController.php`)

```php
public function updateProfilePicture(Request $request)
{
    // 1. Validate the request
    $request->validate([
        'profile_picture' => 'required|image|max:2048', // Max 2MB
    ]);

    $user = Auth::user();

    // 2. Delete old profile picture if exists
    if ($user->profile_picture) {
        Storage::disk('public')->delete($user->profile_picture);
    }

    // 3. Store new profile picture to 'public' disk
    $path = $request->file('profile_picture')
        ->store('profile_pictures', 'public');

    // 4. Update user record in database
    $user->profile_picture = $path;
    $user->save();

    // 5. Generate full URL for response
    $fullUrl = Storage::disk('public')->url($path);

    // 6. Broadcast real-time update event
    event(new UserProfileUpdated($user->id, 'profile_picture', [
        'profile_picture' => $path,
        'profile_picture_url' => $fullUrl,
    ]));

    return response()->json([
        'success' => true,
        'message' => 'Profile picture updated successfully',
        'data' => [
            'profile_picture' => $path,
            'url' => $fullUrl,
        ],
    ], 200);
}
```

### Database Storage

The `profile_picture` column in the `users` table stores the **relative path** only:

```
profile_pictures/A8bmeMNRSSyjnmhZVQkjsOjBZxUUW2Ron2G9uBCb.jpg
```

**NOT** the full URL. The frontend constructs the full URL using the `VITE_PUBLIC_URL` environment variable.

---

## Real-time Broadcasting

### Event Class (`UserProfileUpdated.php`)

```php
<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class UserProfileUpdated implements ShouldBroadcastNow
{
    public int $userId;
    public string $updateType;  // 'name', 'email', 'profile_picture', 'password'
    public array $updatedData;
    public string $updatedAt;

    public function __construct(int $userId, string $updateType, array $updatedData = [])
    {
        $this->userId = $userId;
        $this->updateType = $updateType;
        $this->updatedData = $updatedData;
        $this->updatedAt = now()->toIso8601String();
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("App.Models.User.{$this->userId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'user.profile-updated';
    }

    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
            'update_type' => $this->updateType,
            'data' => $this->updatedData,
            'updated_at' => $this->updatedAt,
            'message' => $this->getMessage(),
        ];
    }

    private function getMessage(): string
    {
        return match ($this->updateType) {
            'profile_picture' => 'Your profile picture has been updated successfully.',
            default => 'Your profile has been updated successfully.',
        };
    }
}
```

### Channel Authorization (`routes/channels.php`)

```php
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
```

---

## Common Issues & Solutions

### Issue 1: Profile Picture Returns 404

**Symptom:** Image URL returns 404 Not Found even though upload succeeded.

**Cause:** Storage symlink not created.

**Solution:**
```bash
php artisan storage:link
```

**Verification:**
```bash
# Check if symlink exists
ls -la public/storage

# Should show:
# storage -> ../storage/app/public
```

---

### Issue 2: Storage Symlink Already Exists Error

**Symptom:** `php artisan storage:link` fails with "The [public/storage] link already exists."

**Solution:**
```bash
# Remove existing symlink (Windows)
rmdir public\storage

# Remove existing symlink (Linux/Mac)
rm public/storage

# Recreate symlink
php artisan storage:link
```

---

### Issue 3: 404 Error on GET /api/v1/user After Route Changes

**Symptom:**
```
GET http://127.0.0.1:8000/api/v1/user 404 (Not Found)
Error: The route api/v1/user could not be found.
```

**Cause:** Laravel caches routes in production (and sometimes in development). After modifying route files, the cache isn't automatically cleared.

**Solution:**

**Option 1: Clear route cache**
```bash
php artisan route:clear
php artisan config:clear
```

**Option 2: Restart development server**
```bash
# Press Ctrl+C to stop the server, then:
php artisan serve
```

**Verification:**

Check if the route exists:
```bash
php artisan route:list --path=user
```

Expected output:
```
GET|HEAD  api/v1/user  .......... UserController@getUser
POST      api/v1/user/profile-picture  .......... UserController@updateProfilePicture
POST      api/v1/user/username  .......... UserController@updateUsername
POST      api/v1/user/password  .......... UserController@updatePassword
POST      api/v1/user/email  .......... UserController@updateEmail
```

**Common Route Definition Mistake:**

❌ **Incorrect (creates `/api/v1/user/user`):**
```php
Route::prefix('user')->group(function () {
    Route::get('/user', [UserController::class, 'getUser']);
});
```

✅ **Correct (creates `/api/v1/user`):**
```php
Route::prefix('user')->group(function () {
    Route::get('/', [UserController::class, 'getUser']);
});
```

**Why This Happens:**
- The `prefix('user')` adds `/user` to all routes in the group
- Using `Route::get('/user', ...)` adds another `/user`, creating `/user/user`
- Using `Route::get('/', ...)` correctly creates just `/user`

---

### Issue 4: Images Not Accessible After Deployment

**Cause:** Cloud platforms (Heroku, Laravel Vapor) don't support traditional symlinks.

**Solution:** Use cloud storage (S3, DigitalOcean Spaces):

```env
FILESYSTEM_DISK=s3

AWS_ACCESS_KEY_ID=your-key
AWS_SECRET_ACCESS_KEY=your-secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-bucket
AWS_URL=https://your-bucket.s3.amazonaws.com
```

Update controller to use configured disk:
```php
$path = $request->file('profile_picture')
    ->store('profile_pictures', config('filesystems.default'));
```

---

### Issue 5: Old Profile Picture Not Deleted

**Symptom:** Storage fills up with unused images.

**Solution:** The controller already handles this, but verify:
```php
if ($user->profile_picture) {
    Storage::disk('public')->delete($user->profile_picture);
}
```

---

### Issue 6: Image Validation Failing

**Common validation rules:**
```php
$request->validate([
    'profile_picture' => [
        'required',
        'image',                    // Must be image (jpeg, png, bmp, gif, svg, webp)
        'max:2048',                 // Max 2MB
        'dimensions:min_width=40,min_height=40',  // Optional: minimum dimensions
        'mimes:jpeg,png,jpg,gif',   // Optional: specific formats
    ],
]);
```

---

## Deployment Checklist

### Local Development

- [ ] Run `php artisan storage:link` after cloning repository
- [ ] Verify `storage/app/public` directory exists and is writable
- [ ] Check `.env` has correct `APP_URL`

### Production Deployment

- [ ] **Option A (Traditional Server):**
  - [ ] Run `php artisan storage:link` after deployment
  - [ ] Verify web server has write permissions to `storage/app/public`
  - [ ] Configure proper file permissions (usually 755 for directories, 644 for files)

- [ ] **Option B (Cloud Storage):**
  - [ ] Configure S3 or similar in `.env`
  - [ ] Update `FILESYSTEM_DISK=s3`
  - [ ] Verify CORS settings on storage bucket
  - [ ] Update frontend `VITE_PUBLIC_URL` to point to CDN/bucket URL

### CI/CD Pipeline

Add to your deployment script:
```bash
#!/bin/bash
# After composer install and migrations
php artisan storage:link 2>/dev/null || echo "Storage link already exists"
```

---

## Related Documentation

- [User Management API](../user-management/README.md)
- [Real-time Broadcasting Setup](../realtime/README.md)
- [Laravel Filesystem Documentation](https://laravel.com/docs/filesystem)

---

## Revision History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | Jan 2026 | HRMS Team | Initial documentation |
| 1.1 | Jan 2026 | HRMS Team | Added GET /user endpoint documentation, route cache issue solution, and route definition best practices |
