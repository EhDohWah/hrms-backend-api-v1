# Grant Import Notification Implementation

## Overview

This document describes the notification system implementation for the Grant Import feature, which alerts users upon completion of grant data imports.

## Implementation Date

December 8, 2025

---

## Changes Made

### 1. Updated `GrantController.php`

**File**: `app/Http/Controllers/Api/GrantController.php`

#### Added Imports

```php
use App\Models\User;
use App\Notifications\ImportedCompletedNotification;
```

#### Added Notification Call in `upload()` Method

After successful import processing, added notification call:

```php
// Send completion notification to user
$this->sendImportNotification($processedGrants, $processedItems, $errors, $skippedGrants);
```

**Location**: Line 457, after building response data and before returning JSON response.

#### Added New Private Method: `sendImportNotification()`

```php
/**
 * Send import completion notification to the authenticated user
 *
 * @param  int  $processedGrants  Number of grants processed
 * @param  int  $processedItems  Number of grant items processed
 * @param  array  $errors  Array of error messages
 * @param  array  $skippedGrants  Array of skipped grant codes
 * @return void
 */
private function sendImportNotification(int $processedGrants, int $processedItems, array $errors, array $skippedGrants): void
{
    try {
        $message = "Grant import finished! Processed: {$processedGrants} grants, {$processedItems} grant items";

        if (count($errors) > 0) {
            $message .= ', Warnings: '.count($errors);
        }

        if (count($skippedGrants) > 0) {
            $message .= ', Skipped: '.count($skippedGrants);
        }

        $user = User::find(auth()->id());
        if ($user) {
            $user->notify(new ImportedCompletedNotification($message));
        }
    } catch (\Exception $e) {
        // Log the error but don't fail the import response
        \Log::error('Failed to send grant import notification', [
            'error' => $e->getMessage(),
            'user_id' => auth()->id(),
        ]);
    }
}
```

**Location**: Lines 1948-1981, at the end of the controller before the closing brace.

---

## How It Works

### Grant Import Flow (Synchronous)

Unlike the `EmployeesImport` which uses queued/asynchronous processing, the Grant Import is **synchronous** and processes immediately:

1. **User uploads Excel file** → `POST /api/grants/upload`
2. **Controller processes file** → Reads sheets, creates grants and items
3. **Transaction completes** → All data saved to database
4. **Notification sent** → User receives notification
5. **Response returned** → HTTP 200 with results

### Notification Message Format

The notification message includes:
- Number of grants processed
- Number of grant items processed
- Number of warnings/errors (if any)
- Number of skipped grants (if any)

#### Example Messages

**Successful import:**
```
Grant import finished! Processed: 3 grants, 15 grant items
```

**Import with warnings:**
```
Grant import finished! Processed: 3 grants, 15 grant items, Warnings: 2
```

**Import with skipped grants:**
```
Grant import finished! Processed: 2 grants, 10 grant items, Warnings: 3, Skipped: 1
```

---

## Notification Details

### Notification Class Used

**Class**: `ImportedCompletedNotification`

**Location**: `app/Notifications/ImportedCompletedNotification.php`

**Channels**: 
- `database` - Stored in `notifications` table
- `broadcast` - Sent via WebSocket/Pusher

### Notification Structure

```php
[
    'type' => 'import_completed',
    'message' => 'Grant import finished! Processed: 3 grants, 15 grant items',
    'finished_at' => '2025-12-08 10:30:00'
]
```

---

## Key Differences from Employee Import

| Feature | Employee Import | Grant Import |
|---------|----------------|--------------|
| **Processing** | Asynchronous (Queued) | Synchronous (Immediate) |
| **Notification Trigger** | `AfterImport` event | Direct call in controller |
| **Import Class** | `EmployeesImport` (implements `ShouldQueue`) | Direct controller processing |
| **Cache Usage** | Yes (for progress tracking) | No (immediate response) |
| **Response Code** | `202 Accepted` | `200 OK` |
| **Response Data** | `import_id`, `status` | Full results immediately |

---

## Error Handling

The notification system includes robust error handling:

1. **Try-Catch Block**: Wraps the entire notification process
2. **Non-Blocking**: If notification fails, the import still succeeds
3. **Error Logging**: Logs notification failures to Laravel log
4. **User Check**: Verifies user exists before sending notification

### Example Error Log

```php
\Log::error('Failed to send grant import notification', [
    'error' => 'User not found',
    'user_id' => 123,
]);
```

---

## Testing the Notification

### Prerequisites

1. User must be authenticated
2. User must have permission to upload grants
3. Database connection must be active
4. Broadcasting must be configured (for real-time notifications)

### Test Steps

1. **Upload a grant Excel file**:
   ```bash
   POST /api/grants/upload
   Content-Type: multipart/form-data
   Authorization: Bearer {token}
   
   file: grant_import.xlsx
   ```

2. **Check HTTP response** (should be 200 OK):
   ```json
   {
       "success": true,
       "message": "Grant data import completed",
       "data": {
           "processed_grants": 3,
           "processed_items": 15
       }
   }
   ```

3. **Check database notifications**:
   ```sql
   SELECT * FROM notifications 
   WHERE notifiable_id = {user_id} 
   ORDER BY created_at DESC 
   LIMIT 1;
   ```

4. **Check Laravel logs** (if notification failed):
   ```bash
   tail -f storage/logs/laravel.log
   ```

### Expected Notification Record

```json
{
    "id": "uuid-string",
    "type": "App\\Notifications\\ImportedCompletedNotification",
    "notifiable_type": "App\\Models\\User",
    "notifiable_id": 1,
    "data": {
        "type": "import_completed",
        "message": "Grant import finished! Processed: 3 grants, 15 grant items",
        "finished_at": "2025-12-08 10:30:00"
    },
    "read_at": null,
    "created_at": "2025-12-08 10:30:00",
    "updated_at": "2025-12-08 10:30:00"
}
```

---

## Frontend Integration

### Listening for Notifications

**Using Laravel Echo (WebSocket)**:

```javascript
// Listen for new notifications
Echo.private(`App.Models.User.${userId}`)
    .notification((notification) => {
        if (notification.type === 'import_completed') {
            // Show notification to user
            showToast(notification.message, 'success');
            
            // Optionally refresh grants list
            refreshGrantsList();
        }
    });
```

**Polling Database Notifications**:

```javascript
// GET /api/user/notifications
axios.get('/api/user/notifications')
    .then(response => {
        const unread = response.data.filter(n => !n.read_at);
        unread.forEach(notification => {
            if (notification.data.type === 'import_completed') {
                showToast(notification.data.message, 'success');
            }
        });
    });
```

---

## Related Files

### Modified Files
- `app/Http/Controllers/Api/GrantController.php`

### Existing Files (Used)
- `app/Notifications/ImportedCompletedNotification.php`
- `app/Models/User.php`

### Migration (Already Exists)
- `database/migrations/xxxx_create_notifications_table.php`

---

## Configuration

### Broadcasting Configuration

Ensure broadcasting is configured in `.env`:

```env
BROADCAST_DRIVER=pusher
# or
BROADCAST_DRIVER=redis

PUSHER_APP_ID=your-app-id
PUSHER_APP_KEY=your-app-key
PUSHER_APP_SECRET=your-app-secret
PUSHER_APP_CLUSTER=your-cluster
```

### Queue Configuration (Not Required for Grant Import)

Grant import doesn't use queues, but if you want to queue the notification itself:

```php
// In ImportedCompletedNotification.php
class ImportedCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    // ...
}
```

---

## Future Enhancements

### Possible Improvements

1. **Email Notifications**: Add email channel for important imports
2. **SMS Notifications**: For critical errors or large imports
3. **Slack/Teams Integration**: For team-wide notifications
4. **Notification Preferences**: Allow users to configure notification types
5. **Import History**: Link notification to detailed import log page
6. **Progress Tracking**: For very large files (requires async processing)

### Example Email Implementation

```php
public function via(object $notifiable): array
{
    return ['database', 'broadcast', 'mail'];
}

public function toMail(object $notifiable): MailMessage
{
    return (new MailMessage)
        ->subject('Grant Import Completed')
        ->line($this->message)
        ->action('View Grants', url('/grants'))
        ->line('Thank you for using our application!');
}
```

---

## Troubleshooting

### Issue: Notification Not Received

**Possible Causes**:
1. User not authenticated
2. Broadcasting not configured
3. Database connection issue
4. User model doesn't use `Notifiable` trait

**Solution**:
- Check Laravel logs
- Verify user is authenticated: `auth()->check()`
- Test database query: `User::find(auth()->id())`
- Verify broadcasting config: `php artisan config:cache`

### Issue: Notification Sent But Not Displayed

**Possible Causes**:
1. Frontend not listening for notifications
2. WebSocket connection issue
3. Frontend polling not implemented

**Solution**:
- Check browser console for WebSocket errors
- Verify Echo configuration in frontend
- Test notification endpoint: `GET /api/user/notifications`

### Issue: Duplicate Notifications

**Possible Cause**: Multiple upload calls

**Solution**: Ensure frontend prevents double-submission during upload

---

## API Reference

### Endpoint

```
POST /api/grants/upload
```

### Response (Success)

```json
{
    "success": true,
    "message": "Grant data import completed",
    "data": {
        "processed_grants": 3,
        "processed_items": 15,
        "warnings": [
            "Sheet 'Grant1' row 10: Duplicate grant item - Position 'Manager' with budget line code 'BL001' already exists"
        ],
        "skipped_grants": []
    }
}
```

**Note**: Notification is sent automatically after this response, not as part of the response.

---

## Summary

The Grant Import notification system provides users with immediate feedback after importing grant data:

✅ **Synchronous Processing** - Import completes immediately  
✅ **Automatic Notification** - Sent after successful import  
✅ **Detailed Feedback** - Includes counts and warnings  
✅ **Non-Blocking** - Notification failure doesn't affect import  
✅ **Multi-Channel** - Database and broadcast channels  
✅ **Error Safe** - Try-catch prevents failures  

The implementation follows Laravel best practices and is consistent with the existing `EmployeesImport` notification pattern, adapted for synchronous processing.

