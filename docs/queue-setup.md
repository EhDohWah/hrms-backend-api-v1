# Queue Setup Guide

This document explains how to configure queue workers for the HRMS application.

## Overview

The HRMS uses Laravel queues for background processing of:
- Employee Excel imports
- Employment imports
- Payroll imports
- Employee funding allocation imports

## Development Setup

During development, the queue worker runs automatically with the dev command:

```bash
composer run dev
```

This starts:
- API server (`php artisan serve`)
- Queue worker (`php artisan queue:listen --tries=1`)
- Log viewer (`php artisan pail`)
- Vite dev server (`npm run dev`)

## Production Setup (VPS/Dedicated Server)

### Option 1: Database Queue with Supervisor (Recommended)

#### 1. Configure Environment

```env
QUEUE_CONNECTION=database
```

#### 2. Install Supervisor

```bash
# Ubuntu/Debian
sudo apt update
sudo apt install supervisor

# CentOS/RHEL
sudo yum install supervisor
```

#### 3. Create Supervisor Configuration

Create `/etc/supervisor/conf.d/hrms-worker.conf`:

```ini
[program:hrms-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/hrms-backend-api-v1/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/hrms-backend-api-v1/storage/logs/worker.log
stopwaitsecs=3600
```

**Configuration explained:**
- `numprocs=2`: Runs 2 worker processes (adjust based on server capacity)
- `--sleep=3`: Wait 3 seconds between checking for new jobs
- `--tries=3`: Retry failed jobs up to 3 times
- `--max-time=3600`: Restart worker every hour (prevents memory leaks)
- `user=www-data`: Run as web server user (adjust if different)

#### 4. Start Supervisor

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start hrms-worker:*
```

#### 5. Useful Supervisor Commands

```bash
# Check status
sudo supervisorctl status

# Restart workers (after code deployment)
sudo supervisorctl restart hrms-worker:*

# Stop workers
sudo supervisorctl stop hrms-worker:*

# View logs
tail -f /var/www/hrms-backend-api-v1/storage/logs/worker.log
```

### Option 2: Redis Queue (Higher Performance)

For high-volume job processing, use Redis instead of database.

#### 1. Install Redis

```bash
# Ubuntu/Debian
sudo apt install redis-server php-redis

# Start Redis
sudo systemctl enable redis-server
sudo systemctl start redis-server
```

#### 2. Configure Environment

```env
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

#### 3. Update Supervisor Config

Change the command in supervisor config:

```ini
command=php /var/www/hrms-backend-api-v1/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
```

### Option 3: Laravel Horizon (Redis + Dashboard)

For Redis queues with a beautiful monitoring dashboard:

```bash
composer require laravel/horizon
php artisan horizon:install
php artisan migrate
```

Update Supervisor to run Horizon instead:

```ini
[program:hrms-horizon]
process_name=%(program_name)s
command=php /var/www/hrms-backend-api-v1/artisan horizon
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/hrms-backend-api-v1/storage/logs/horizon.log
stopwaitsecs=3600
```

Access dashboard at: `https://your-domain.com/horizon`

## Deployment Checklist

After deploying new code, always restart queue workers:

```bash
# With Supervisor
sudo supervisorctl restart hrms-worker:*

# With Horizon
php artisan horizon:terminate
```

This ensures workers pick up the latest code changes.

## Monitoring Failed Jobs

```bash
# View failed jobs
php artisan queue:failed

# Retry a specific failed job
php artisan queue:retry {id}

# Retry all failed jobs
php artisan queue:retry all

# Clear all failed jobs
php artisan queue:flush
```

## Troubleshooting

### Jobs stuck in database
```bash
# Check pending jobs
php artisan tinker
>>> DB::table('jobs')->count()

# Clear all pending jobs (use with caution)
>>> DB::table('jobs')->truncate()
```

### Worker not processing jobs
1. Check Supervisor status: `sudo supervisorctl status`
2. Check worker logs: `tail -f storage/logs/worker.log`
3. Check Laravel logs: `tail -f storage/logs/laravel.log`
4. Verify queue connection: `php artisan queue:work --once` (processes one job)

### Memory issues
If workers consume too much memory, reduce `--max-time` or add `--max-jobs`:

```ini
command=php artisan queue:work database --sleep=3 --tries=3 --max-jobs=1000 --max-time=1800
```
