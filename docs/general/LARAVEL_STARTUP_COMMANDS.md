# 🚀 Laravel HRMS Backend - Startup Commands Reference

**Project:** HR Management System - Backend API v1
**Framework:** Laravel 11
**Database:** MSSQL Server
**Last Updated:** November 6, 2025

---

## 📋 Table of Contents

1. [Quick Start](#quick-start)
2. [Required Commands](#required-commands)
3. [Optional Commands](#optional-commands)
4. [Additional Commands](#additional-commands)
5. [Development Commands](#development-commands)
6. [Production Setup](#production-setup)
7. [Troubleshooting](#troubleshooting)

---

## 🎯 Quick Start

### Minimum Setup (Basic Testing)
```bash
# Navigate to backend directory
cd "C:\Users\Turtle\Desktop\HR Management System\3. Implementation\HRMS-V1\hrms-backend-api-v1"

# Start Laravel server
php artisan serve
```
✅ **Server runs at:** `http://localhost:8000`

### Full Setup (All Features)
Open **4 separate terminals** and run these commands:

**Terminal 1 - API Server:**
```bash
cd "C:\Users\Turtle\Desktop\HR Management System\3. Implementation\HRMS-V1\hrms-backend-api-v1"
php artisan serve
```

**Terminal 2 - Queue Worker:**
```bash
cd "C:\Users\Turtle\Desktop\HR Management System\3. Implementation\HRMS-V1\hrms-backend-api-v1"
php artisan queue:work --tries=3 --timeout=300
```

**Terminal 3 - Scheduler:**
```bash
cd "C:\Users\Turtle\Desktop\HR Management System\3. Implementation\HRMS-V1\hrms-backend-api-v1"
php artisan schedule:work
```

**Terminal 4 - WebSocket Server (Reverb):**
```bash
cd "C:\Users\Turtle\Desktop\HR Management System\3. Implementation\HRMS-V1\hrms-backend-api-v1"
php artisan reverb:start
```

---

## ✅ Required Commands

### 1. Laravel Development Server
```bash
php artisan serve
```

**Purpose:** Starts the Laravel API server
**Port:** `http://localhost:8000`
**Required For:** All API requests from frontend
**Keep Running:** Yes ✓
**Restart When:** Never (unless you stop it)

**Custom Port:**
```bash
php artisan serve --port=8080
```

**Custom Host:**
```bash
php artisan serve --host=0.0.0.0 --port=8000
```

---

### 2. Queue Worker
```bash
php artisan queue:work --tries=3 --timeout=300
```

**Purpose:** Processes background jobs and queued tasks
**Required For:**
- ✅ Bulk payroll creation
- ✅ Email notifications
- ✅ Heavy calculations
- ✅ Export jobs (PDF, Excel)

**Keep Running:** Yes ✓
**Restart When:** After ANY code changes

**Options:**
- `--tries=3` - Retry failed jobs 3 times
- `--timeout=300` - Kill jobs after 5 minutes
- `--queue=high,default` - Process specific queues
- `--sleep=3` - Wait 3 seconds when queue is empty
- `--max-jobs=1000` - Stop after processing 1000 jobs
- `--max-time=3600` - Stop after 1 hour

**Monitor Queue:**
```bash
php artisan queue:monitor
```

**Retry Failed Jobs:**
```bash
# Retry all failed jobs
php artisan queue:retry all

# Retry specific job
php artisan queue:retry 5
```

**Clear Failed Jobs:**
```bash
php artisan queue:flush
```

**Clear All Queued Jobs:**
```bash
php artisan queue:clear
```

---

## 🔵 Optional Commands

### 3. Task Scheduler
```bash
php artisan schedule:work
```

**Purpose:** Runs scheduled tasks automatically
**Required For:**
- ⏰ Daily probation transitions (runs at 00:01)
- 📊 Scheduled reports
- 🧹 Database cleanup tasks

**Keep Running:** Only in development
**Production Alternative:** Use Windows Task Scheduler or Cron

**List All Scheduled Tasks:**
```bash
php artisan schedule:list
```

**Run Scheduler Manually (One-time):**
```bash
php artisan schedule:run
```

**Test Scheduler:**
```bash
# Run tasks due now
php artisan schedule:test
```

---

### 4. WebSocket Server (Laravel Reverb)
```bash
php artisan reverb:start
```

**Purpose:** Enables real-time notifications via WebSockets
**Port:** `http://127.0.0.1:8081`
**Required For:**
- 🔄 Real-time bulk payroll progress updates
- 🔔 Live notifications
- 📡 Broadcasting events

**Keep Running:** Yes (if using real-time features)

**Configuration:**
Check `.env` file:
```env
BROADCAST_CONNECTION=reverb  # Uncomment this line
REVERB_APP_ID=710404
REVERB_APP_KEY=lwzlina3oymluc9m9nog
REVERB_APP_SECRET=ekb1xpbaujifidaky0gh
REVERB_HOST="127.0.0.1"
REVERB_PORT=8081
REVERB_SCHEME=http
```

**Custom Port:**
```bash
php artisan reverb:start --port=8082
```

**Debug Mode:**
```bash
php artisan reverb:start --debug
```

---

## 🛠️ Additional Commands

### Database Migration Commands

**Run All Migrations:**
```bash
php artisan migrate
```

**Rollback Last Migration:**
```bash
php artisan migrate:rollback
```

**Rollback Last 3 Migrations:**
```bash
php artisan migrate:rollback --step=3
```

**Reset All Migrations (⚠️ Deletes all data!):**
```bash
php artisan migrate:reset
```

**Refresh Migrations (⚠️ Deletes all data!):**
```bash
php artisan migrate:fresh
```

**Refresh + Seed:**
```bash
php artisan migrate:fresh --seed
```

**Check Migration Status:**
```bash
php artisan migrate:status
```

---

### Cache Management Commands

**Clear All Caches at Once:**
```bash
php artisan optimize:clear
```

**Clear Application Cache:**
```bash
php artisan cache:clear
```

**Clear Configuration Cache:**
```bash
php artisan config:clear
```

**Clear Route Cache:**
```bash
php artisan route:clear
```

**Clear View Cache:**
```bash
php artisan view:clear
```

**Clear Event Cache:**
```bash
php artisan event:clear
```

**Optimize for Production:**
```bash
php artisan optimize
```

**Generate Cached Files:**
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

---

### Probation Management Commands

**Process Probation Transitions (Manual):**
```bash
php artisan employment:process-probation-transitions
```

**Dry-Run Mode (Preview Changes):**
```bash
php artisan employment:process-probation-transitions --dry-run
```

**Process Specific Employment:**
```bash
php artisan employment:process-probation-transitions --employment=1
```

**Features:**
- ✅ Automatically transitions employees from probation to regular status
- ✅ Updates funding allocations from `probation_salary` to `pass_probation_salary`
- ✅ Marks old allocations as `historical`
- ✅ Creates new `active` allocations
- ✅ Logs changes to employment history

**Scheduled Execution:**
- Runs automatically daily at **00:01 Bangkok time**
- Configured in `bootstrap/app.php`

---

### Artisan List Commands

**List All Artisan Commands:**
```bash
php artisan list
```

**Search for Specific Commands:**
```bash
php artisan list | grep queue
```

**Get Help for a Command:**
```bash
php artisan help migrate
```

---

### Database Seeding

**Run All Seeders:**
```bash
php artisan db:seed
```

**Run Specific Seeder:**
```bash
php artisan db:seed --class=BulkPayrollPermissionSeeder
```

---

### Route Management

**List All Routes:**
```bash
php artisan route:list
```

**Filter Routes by Name:**
```bash
php artisan route:list --name=employment
```

**Filter Routes by Method:**
```bash
php artisan route:list --method=POST
```

**Show Route Details:**
```bash
php artisan route:list --path=employments
```

---

### Code Generation Commands

**Create Model:**
```bash
php artisan make:model ModelName -m
```

**Create Controller:**
```bash
php artisan make:controller ControllerName
```

**Create Migration:**
```bash
php artisan make:migration create_table_name
```

**Create Seeder:**
```bash
php artisan make:seeder SeederName
```

**Create Request:**
```bash
php artisan make:request RequestName
```

**Create Resource:**
```bash
php artisan make:resource ResourceName
```

**Create Command:**
```bash
php artisan make:command CommandName
```

**Create Service:**
```bash
php artisan make:class Services/ServiceName
```

---

## 💻 Development Commands

### Testing Commands

**Run All Tests:**
```bash
php artisan test
```

**Run Specific Test:**
```bash
php artisan test --filter=ProbationTest
```

**Run Tests with Coverage:**
```bash
php artisan test --coverage
```

---

### Code Quality Commands

**Run PHP CS Fixer (Laravel Pint):**
```bash
vendor/bin/pint
```

**Dry Run (Preview Changes):**
```bash
vendor/bin/pint --test
```

**Fix Specific Directory:**
```bash
vendor/bin/pint app/Services
```

---

### Maintenance Mode

**Enable Maintenance Mode:**
```bash
php artisan down
```

**Enable with Secret Bypass:**
```bash
php artisan down --secret="maintenance-bypass-token"
```

**Disable Maintenance Mode:**
```bash
php artisan up
```

**Bypass URL:** `http://localhost:8000/maintenance-bypass-token`

---

## 🏭 Production Setup

### Windows Server (Production)

#### 1. Install Supervisor for Queue Workers

**Install Supervisor:** Download from [Supervisor for Windows](https://github.com/Supervisor/supervisor)

**Configuration File** (`supervisor.conf`):
```ini
[program:hrms-queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php "C:\path\to\hrms-backend-api-v1\artisan" queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=C:\path\to\hrms-backend-api-v1\storage\logs\worker.log
stopwaitsecs=3600
```

**Start Supervisor:**
```bash
supervisord -c supervisor.conf
supervisorctl start hrms-queue-worker:*
```

---

#### 2. Windows Task Scheduler for Scheduled Tasks

**Create Task:**
1. Open **Task Scheduler**
2. Create **New Task**
3. Name: `Laravel Scheduler - HRMS`
4. **Trigger:** Every 1 minute
5. **Action:** Start a Program
   - Program: `php`
   - Arguments: `"C:\path\to\hrms-backend-api-v1\artisan" schedule:run`
   - Start in: `C:\path\to\hrms-backend-api-v1`

**Command Line Alternative:**
```bash
schtasks /create /tn "Laravel-HRMS-Scheduler" /tr "php \"C:\path\to\hrms-backend-api-v1\artisan\" schedule:run" /sc minute /mo 1
```

---

#### 3. Configure IIS or Apache

**IIS Configuration:**
- Point document root to `public` folder
- Install PHP FastCGI
- Configure `web.config` for URL rewriting

**Apache Configuration:**
- Point DocumentRoot to `public` folder
- Enable `mod_rewrite`
- Use `.htaccess` in `public` folder

---

## 🔧 Troubleshooting

### Issue: Queue Worker Not Processing Jobs

**Solution 1: Restart Queue Worker**
```bash
# Stop current worker (Ctrl+C)
# Start new worker
php artisan queue:work --tries=3 --timeout=300
```

**Solution 2: Check Queue Connection**
```bash
# Check .env file
QUEUE_CONNECTION=database  # or redis, sync
```

**Solution 3: Clear Failed Jobs**
```bash
php artisan queue:flush
php artisan queue:restart
```

---

### Issue: Scheduler Not Running

**Solution: Check Schedule List**
```bash
php artisan schedule:list
```

**Manual Test:**
```bash
php artisan schedule:run
```

**Check Logs:**
```bash
tail -f storage/logs/laravel.log
```

---

### Issue: Cache Not Clearing

**Solution: Nuclear Option**
```bash
php artisan optimize:clear
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Manually delete cache files
rm -rf bootstrap/cache/*.php
rm -rf storage/framework/cache/*
rm -rf storage/framework/views/*
```

---

### Issue: Reverb WebSocket Not Connecting

**Solution 1: Check Port Availability**
```bash
# Check if port 8081 is in use
netstat -ano | findstr :8081
```

**Solution 2: Change Port**
```env
# In .env file
REVERB_PORT=8082
```

**Solution 3: Check Firewall**
- Allow port 8081 in Windows Firewall
- Check antivirus settings

---

### Issue: Migration Errors

**Solution: Check Database Connection**
```bash
php artisan tinker
>>> DB::connection()->getPdo();
```

**Reset Migrations (⚠️ Deletes Data!):**
```bash
php artisan migrate:fresh
```

---

## 📊 Monitoring Commands

### Check System Status

**Check PHP Version:**
```bash
php -v
```

**Check Laravel Version:**
```bash
php artisan --version
```

**Check Database Connection:**
```bash
php artisan db:show
```

**Check Queue Status:**
```bash
php artisan queue:monitor
```

**Check Environment:**
```bash
php artisan env
```

---

## 🔐 Security Commands

### Generate Application Key

```bash
php artisan key:generate
```

### Clear Sensitive Cached Data

```bash
php artisan config:clear
php artisan cache:clear
```

---

## 📝 Log Commands

### View Real-time Logs

```bash
# Windows
Get-Content storage/logs/laravel.log -Wait -Tail 50

# Git Bash
tail -f storage/logs/laravel.log
```

### Clear Old Logs

```bash
# Manually delete log files
rm storage/logs/laravel-*.log
```

---

## 🎯 Feature-Specific Commands

### Probation Management

```bash
# Process probation transitions
php artisan employment:process-probation-transitions

# Dry-run mode
php artisan employment:process-probation-transitions --dry-run

# Specific employment
php artisan employment:process-probation-transitions --employment=1
```

### Bulk Payroll

No specific command - uses queue worker:
```bash
php artisan queue:work --tries=3 --timeout=300
```

### Real-time Notifications

```bash
# Start WebSocket server
php artisan reverb:start
```

---

## 📦 Composer Commands

### Install Dependencies

```bash
composer install
```

### Update Dependencies

```bash
composer update
```

### Dump Autoload

```bash
composer dump-autoload
```

---

## 🚀 Summary: Daily Startup Sequence

### Development (Local Testing)

1. **Terminal 1 - API Server** (Required)
   ```bash
   php artisan serve
   ```

2. **Terminal 2 - Queue Worker** (If testing bulk payroll)
   ```bash
   php artisan queue:work --tries=3 --timeout=300
   ```

3. **Terminal 3 - Scheduler** (If testing probation)
   ```bash
   php artisan schedule:work
   ```

4. **Terminal 4 - WebSocket** (If testing real-time updates)
   ```bash
   php artisan reverb:start
   ```

---

### Production

1. **Configure IIS/Apache** to serve `public` folder
2. **Install Supervisor** for queue workers (auto-restart)
3. **Configure Task Scheduler** for scheduled tasks (runs every minute)
4. **Run Reverb as a Service** using NSSM or similar tool

---

## 📞 Support

For issues or questions:
- Check `storage/logs/laravel.log` for errors
- Run `php artisan optimize:clear` to clear caches
- Restart queue worker after code changes
- Ensure database connection is active

---

**Document Version:** 1.0
**Last Updated:** November 6, 2025
**Maintained By:** Development Team
