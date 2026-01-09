# Queue Worker Restart Required After Code Changes

## Issue

After updating the employment import code to remove benefit percentage fields, the error persisted:
```
SQLSTATE[42S22]: Invalid column name 'health_welfare_percentage'
```

Even though:
- ✅ Import logic was updated
- ✅ Template generation was updated
- ✅ Model `$fillable` and `$casts` were updated
- ✅ All tests were passing

## Root Cause

**Laravel queue workers cache the application code** in memory for performance. When you make code changes, running queue workers continue using the old cached code until they are restarted.

This is a common issue when:
- Updating import/export logic
- Modifying job classes
- Changing model definitions
- Updating any code executed by queued jobs

## Solution

### 1. Restart Queue Workers
```bash
php artisan queue:restart
```

This sends a signal to all running queue workers to gracefully restart after finishing their current job.

### 2. Clear All Caches (Recommended)
```bash
# PowerShell (Windows)
php artisan cache:clear; php artisan config:clear; php artisan route:clear; php artisan view:clear

# Bash (Linux/Mac)
php artisan cache:clear && php artisan config:clear && php artisan route:clear && php artisan view:clear
```

### 3. Verify Queue Worker Restarted

Check that the queue worker has picked up the new code by:
1. Monitoring the queue worker logs
2. Testing the import again
3. Checking Laravel logs for errors

## When to Restart Queue Workers

You **MUST** restart queue workers after:
- ✅ Updating job classes
- ✅ Modifying import/export logic
- ✅ Changing model definitions
- ✅ Updating any code used in queued jobs
- ✅ Deploying new code to production
- ✅ Making database schema changes

## Production Deployment

In production, always include queue worker restart in your deployment process:

```bash
# Example deployment script
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart  # ← CRITICAL!
```

## Alternative: Supervisor Auto-Restart

If using Supervisor to manage queue workers, you can configure automatic restarts:

```ini
[program:laravel-worker]
command=php artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
```

The `--max-time=3600` option will automatically restart workers after 1 hour, ensuring they pick up code changes.

## Troubleshooting

### Queue Worker Not Restarting

If `php artisan queue:restart` doesn't work:

1. **Manually stop the worker:**
   - Find the process: `ps aux | grep "queue:work"`
   - Kill it: `kill -9 <PID>`
   - Restart: `php artisan queue:work`

2. **Check if worker is running:**
   ```bash
   php artisan queue:listen --timeout=60
   ```

3. **Use Horizon (if installed):**
   ```bash
   php artisan horizon:terminate
   ```

### Still Getting Errors After Restart

If errors persist after restart:

1. ✅ Verify code changes are saved
2. ✅ Clear all caches
3. ✅ Check if multiple queue workers are running
4. ✅ Verify database schema matches model
5. ✅ Check for syntax errors in updated code

## Related Commands

```bash
# View queue status
php artisan queue:work --once  # Process one job and exit

# Monitor queue in real-time
php artisan queue:listen

# Clear failed jobs
php artisan queue:flush

# Retry failed jobs
php artisan queue:retry all
```

## Key Takeaway

🔴 **ALWAYS restart queue workers after code changes that affect queued jobs!**

This is especially important for:
- Import/Export operations
- Background processing
- Scheduled tasks
- Email sending
- File processing

---

**Issue Resolved**: January 9, 2026  
**Solution**: Queue worker restart + cache clear  
**Status**: ✅ Working correctly after restart
