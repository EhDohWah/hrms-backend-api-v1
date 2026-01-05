# Notification System - Backend Guide

## Overview
Backend implementation details for the HRMS notification system using Laravel Notifications, queued delivery, database persistence, and Laravel Reverb for real-time broadcasting.

## Table of Contents
1. Architecture Overview
2. Backend Implementation
3. Real-Time WebSocket (Laravel Reverb)
4. Database Schema
5. API Endpoints
6. Notification Types
7. How to Create New Notifications
8. Operations & Testing
9. Summary

---

## 1. Architecture Overview

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         NOTIFICATION FLOW                                │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  ┌──────────────┐     ┌──────────────┐     ┌──────────────────────────┐ │
│  │   Backend    │     │   Laravel    │     │       Frontend           │ │
│  │   Trigger    │────▶│   Reverb     │────▶│   (WebSocket Listener)   │ │
│  │              │     │  (WebSocket) │     │                          │ │
│  └──────────────┘     └──────────────┘     └──────────────────────────┘ │
│         │                                            │                   │
│         │                                            ▼                   │
│         │              ┌──────────────┐     ┌──────────────────────────┐ │
│         │              │   Database   │     │   Notification Store     │ │
│         └─────────────▶│   Storage    │◀────│      (Pinia)             │ │
│                        │ (Laravel DB) │     │                          │ │
│                        └──────────────┘     └──────────────────────────┘ │
│                                                       │                   │
│                                                       ▼                   │
│                                             ┌──────────────────────────┐ │
│                                             │     UI Display           │ │
│                                             │   (Notification Bell)    │ │
│                                             └──────────────────────────┘ │
└─────────────────────────────────────────────────────────────────────────┘
```

Key components (backend):
- Laravel Notifications with queued delivery.
- Laravel Reverb WebSocket broadcasting.
- Database persistence in the `notifications` table.
- API endpoints for listing and marking notifications as read.

---

## 2. Backend Implementation

### 2.1 User Model Setup

```php
// app/Models/User.php
<?php

namespace App\Models;

use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;
}
```

### 2.2 Notification Classes

#### ImportedCompletedNotification

```php
// app/Notifications/ImportedCompletedNotification.php
<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class ImportedCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $message;

    public function __construct($message)
    {
        $this->message = $message;
    }

    /**
     * Delivery channels: database + broadcast (real-time)
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Data stored in database
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'import_completed',
            'message' => $this->message,
        ];
    }

    /**
     * Data sent via WebSocket
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'type' => 'import_completed',
            'message' => $this->message,
        ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'import_completed',
            'message' => $this->message,
            'finished_at' => now()->toDateTimeString(),
        ];
    }
}
```

#### ImportFailedNotification

```php
// app/Notifications/ImportFailedNotification.php
<?php

namespace App\Notifications;

class ImportFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $message;
    public $errorDetails;
    public $importId;

    public function __construct($message, $errorDetails = null, $importId = null)
    {
        $this->message = $message;
        $this->errorDetails = $errorDetails;
        $this->importId = $importId;
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'import_failed',
            'message' => $this->message,
            'error_details' => $this->errorDetails,
            'import_id' => $this->importId,
            'failed_at' => now()->toDateTimeString(),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'type' => 'import_failed',
            'message' => $this->message,
            'import_id' => $this->importId,
            'failed_at' => now()->toDateTimeString(),
        ]);
    }
}
```

### 2.3 Notification Controller

```php
// app/Http/Controllers/Api/NotificationController.php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Get user notifications (latest 20)
     */
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()
            ->notifications()
            ->take(20)
            ->get();

        return response()->json($notifications);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['success' => true]);
    }
}
```

### 2.4 API Routes

```php
// routes/api/admin.php

Route::middleware('auth:sanctum')->group(function () {
    // Notification routes
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
    });
});
```

### 2.5 Broadcasting Channel

```php
// routes/channels.php
<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
}, ['guards' => ['api']]);
```

### 2.6 Triggering Notifications

Example from `GrantsImport.php`:

```php
// app/Imports/GrantsImport.php

public function sendCompletionNotification()
{
    $message = "Grant import finished! Processed: {$this->processedGrants} grants, " .
               "{$this->processedItems} grant items, " .
               "Warnings: {$this->warningCount}, Skipped: {$this->skippedCount}";

    $user = \App\Models\User::find($this->userId);
    if ($user) {
        $user->notify(new ImportedCompletedNotification($message));
    }
}
```

---

## 3. Real-Time WebSocket (Laravel Reverb)

### 3.1 Backend Configuration

```php
// config/broadcasting.php
return [
    'default' => env('BROADCAST_DRIVER', 'null'),
    
    'connections' => [
        'reverb' => [
            'driver' => 'reverb',
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'app_id' => env('REVERB_APP_ID'),
            'options' => [
                'host' => env('REVERB_HOST'),
                'port' => env('REVERB_PORT', 443),
                'scheme' => env('REVERB_SCHEME', 'https'),
            ],
        ],
    ],
];
```

```php
// config/reverb.php
return [
    'default' => env('REVERB_SERVER', 'reverb'),
    
    'servers' => [
        'reverb' => [
            'host' => env('REVERB_HOST', '0.0.0.0'),
            'port' => env('REVERB_PORT', 6001),
            // ...
        ],
    ],
    
    'apps' => [
        'apps' => [
            [
                'key' => env('REVERB_APP_KEY'),
                'secret' => env('REVERB_APP_SECRET'),
                'app_id' => env('REVERB_APP_ID'),
                'allowed_origins' => ['*'],
            ],
        ],
    ],
];
```

### 3.2 Environment Variables (Backend)

```env
BROADCAST_DRIVER=reverb
REVERB_APP_ID=your_app_id
REVERB_APP_KEY=your_app_key
REVERB_APP_SECRET=your_app_secret
REVERB_HOST=127.0.0.1
REVERB_PORT=8081
REVERB_SCHEME=http
```

---

## 4. Database Schema

Laravel uses the built-in `notifications` table:

```sql
CREATE TABLE notifications (
    id CHAR(36) PRIMARY KEY,
    type VARCHAR(255) NOT NULL,
    notifiable_type VARCHAR(255) NOT NULL,
    notifiable_id BIGINT UNSIGNED NOT NULL,
    data JSON NOT NULL,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    INDEX (notifiable_type, notifiable_id)
);
```

Sample row:

```json
{
    "id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
    "type": "App\\Notifications\\ImportedCompletedNotification",
    "notifiable_type": "App\\Models\\User",
    "notifiable_id": 1,
    "data": {
        "type": "import_completed",
        "message": "Grant import finished! Processed: 8 grants, 96 grant items, Warnings: 1, Skipped: 1"
    },
    "read_at": null,
    "created_at": "2025-12-09 10:30:00"
}
```

---

## 5. API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/notifications` | Get user's notifications (latest 20) |
| POST | `/api/notifications/mark-all-read` | Mark all notifications as read |

Example response (GET):

```json
[
    {
        "id": "uuid-here",
        "type": "App\\Notifications\\ImportedCompletedNotification",
        "notifiable_type": "App\\Models\\User",
        "notifiable_id": 1,
        "data": {
            "type": "import_completed",
            "message": "Grant import finished! Processed: 8 grants, 96 grant items, Warnings: 1, Skipped: 1"
        },
        "read_at": null,
        "created_at": "2025-12-09T10:30:00.000000Z",
        "updated_at": "2025-12-09T10:30:00.000000Z"
    }
]
```

---

## 6. Notification Types

| Type | Class | Description | Used In |
|------|-------|-------------|---------|
| `import_completed` | `ImportedCompletedNotification` | Successful import completion | Grants, Employees, Employments |
| `import_failed` | `ImportFailedNotification` | Import failure with errors | Grants, Employees, Employments |

---

## 7. How to Create New Notifications

### Step 1: Create Notification Class

```bash
php artisan make:notification NewFeatureNotification
```

### Step 2: Implement the Notification

```php
<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class NewFeatureNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $message;

    public function __construct($message)
    {
        $this->message = $message;
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'new_feature',
            'message' => $this->message,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'type' => 'new_feature',
            'message' => $this->message,
        ]);
    }
}
```

### Step 3: Trigger the Notification

```php
use App\Notifications\NewFeatureNotification;

// Send to a single user
$user->notify(new NewFeatureNotification('Your message here'));

// Send to multiple users
Notification::send($users, new NewFeatureNotification('Your message here'));
```

---

## 8. Operations & Testing

### Run WebSocket Server

```bash
php artisan reverb:start
php artisan reverb:start --debug
```

### Queue Worker (async delivery)

```bash
php artisan queue:work
php artisan queue:work --queue=notifications
```

### Quick Smoke Test

```php
$user = User::find(1);
$user->notify(new ImportedCompletedNotification('Test notification message'));
```

---

## 9. Summary

Backend responsibilities:
1. Create and queue notifications with Laravel Notifications.
2. Broadcast via Laravel Reverb on user-specific private channels.
3. Persist to the `notifications` table for history and read/unread state.
4. Expose APIs for listing and marking notifications as read.

