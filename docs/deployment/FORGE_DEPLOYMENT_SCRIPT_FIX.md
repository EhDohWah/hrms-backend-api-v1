# Fix Laravel Forge Deployment Script - Remove npm run build

**Platform:** Laravel Forge  
**Issue:** Backend running `npm run build` unnecessarily  
**Date:** January 10, 2026

---

## 🔍 Where to Find the Deployment Script

### **Step 1: Log into Laravel Forge**

1. Go to: https://forge.laravel.com
2. Log in with your credentials

### **Step 2: Navigate to Your Site**

1. Click on your **Server** (the server where your backend is hosted)
2. Click on your **Site** (the site for `hrms-backend-api-v1`)

### **Step 3: Find Deployment Script**

1. In the site dashboard, look for the **"Deploy"** tab or section
2. Click on **"Deploy Script"** or **"Deployment Script"**
3. You'll see a text area with your deployment commands

**Location in Forge UI:**
```
Forge Dashboard
  → Your Server
    → Your Site (hrms-backend-api-v1)
      → Deploy Tab
        → Deploy Script (or "Edit Deploy Script")
```

---

## 📝 Current Script (Probably Looks Like This)

**❌ WRONG - Has npm commands:**

```bash
cd /home/forge/your-site-name
git pull origin $FORGE_SITE_BRANCH
$FORGE_COMPOSER install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-suggest

# ❌ REMOVE THESE LINES:
npm install
npm run build

php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

---

## ✅ Correct Script (Remove npm Commands)

**✅ CORRECT - No npm commands:**

```bash
cd /home/forge/your-site-name
git pull origin $FORGE_SITE_BRANCH
$FORGE_COMPOSER install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-suggest

php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

---

## 🔧 How to Fix

### **Step 1: Open Deployment Script**

1. Log into Forge
2. Go to: Server → Site → **Deploy** tab
3. Click **"Edit Deploy Script"** or find the script text area

### **Step 2: Remove npm Commands**

**Find and DELETE these lines:**
```bash
npm install
npm run build
npm ci
npm run production
```

**Or if you see:**
```bash
if [ -f package.json ]; then
    npm install
    npm run build
fi
```
**DELETE the entire if block**

### **Step 3: Save the Script**

1. Click **"Save"** or **"Update Deploy Script"** button
2. Forge will save your changes

### **Step 4: Test Deployment**

1. Click **"Deploy Now"** button
2. Watch the deployment logs
3. Verify it completes without npm commands
4. Check that your application still works

---

## 📋 Complete Correct Deployment Script

Copy and paste this into your Forge deployment script:

```bash
cd /home/forge/your-site-name
git pull origin $FORGE_SITE_BRANCH

$FORGE_COMPOSER install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-suggest

php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

**Note:** Replace `your-site-name` with your actual site name (Forge usually sets this automatically)

---

## 🎯 What Each Command Does

| Command | Purpose |
|---------|---------|
| `cd /home/forge/your-site-name` | Navigate to site directory |
| `git pull origin $FORGE_SITE_BRANCH` | Pull latest code from Git |
| `$FORGE_COMPOSER install ...` | Install PHP dependencies |
| `php artisan migrate --force` | Run database migrations |
| `php artisan config:cache` | Cache configuration files |
| `php artisan route:cache` | Cache routes for performance |
| `php artisan view:cache` | Cache Blade views |
| `php artisan queue:restart` | Restart queue workers |

**No npm commands needed!**

---

## 🔍 Visual Guide to Forge Dashboard

```
┌─────────────────────────────────────────┐
│ Laravel Forge Dashboard                 │
├─────────────────────────────────────────┤
│                                         │
│  Servers                                │
│  ┌───────────────────────────────────┐  │
│  │ Your Server Name                  │  │
│  │                                   │  │
│  │  Sites:                           │  │
│  │  ┌─────────────────────────────┐  │  │
│  │  │ hrms-backend-api-v1         │  │  │
│  │  │                             │  │  │
│  │  │  Tabs:                      │  │  │
│  │  │  [App] [Deploy] [SSL] ...   │  │  │
│  │  │                             │  │  │
│  │  │  Click "Deploy" tab          │  │  │
│  │  │                             │  │  │
│  │  │  ┌───────────────────────┐  │  │  │
│  │  │  │ Deploy Script:        │  │  │  │
│  │  │  │ [Text area with       │  │  │  │
│  │  │  │  deployment commands] │  │  │  │
│  │  │  │                       │  │  │  │
│  │  │  │ [Save] [Deploy Now]   │  │  │  │
│  │  │  └───────────────────────┘  │  │  │
│  │  └─────────────────────────────┘  │  │
│  └───────────────────────────────────┘  │
└─────────────────────────────────────────┘
```

---

## ✅ Verification Checklist

After fixing, verify:

- [ ] Deployment script saved in Forge
- [ ] No `npm` commands in script
- [ ] Deployment completes successfully
- [ ] Application works correctly
- [ ] Deployment logs show no npm errors
- [ ] Deployment is faster (no npm install time)

---

## 🚨 Common Mistakes to Avoid

### **❌ Don't Add:**
```bash
npm install
npm run build
npm ci
```

### **❌ Don't Add:**
```bash
if [ -f package.json ]; then
    npm install
    npm run build
fi
```

### **❌ Don't Add:**
```bash
# Build assets
npm install && npm run build
```

### **✅ Do Keep:**
```bash
# Only Composer and Artisan commands
composer install
php artisan migrate
php artisan optimize
```

---

## 📊 Before vs After

### **Before (With npm):**
```
Deployment Time: ~5-10 minutes
├─ Git pull: 30 seconds
├─ Composer install: 2 minutes
├─ npm install: 3-5 minutes ❌
├─ npm run build: 1-2 minutes ❌
├─ Migrations: 30 seconds
└─ Cache: 10 seconds
```

### **After (Without npm):**
```
Deployment Time: ~2-3 minutes
├─ Git pull: 30 seconds
├─ Composer install: 2 minutes
├─ Migrations: 30 seconds
└─ Cache: 10 seconds
```

**Result: 50-70% faster deployments!**

---

## 🆘 Troubleshooting

### **Problem: Can't find Deploy Script**

**Solutions:**
1. Make sure you're on the correct **Site** (not Server level)
2. Look for tabs: **App**, **Deploy**, **SSL**, **Daemons**, etc.
3. Click the **Deploy** tab
4. If still not visible, check your Forge permissions

### **Problem: Script won't save**

**Solutions:**
1. Check you have edit permissions for the site
2. Try refreshing the page
3. Check browser console for errors
4. Contact Forge support if issue persists

### **Problem: Deployment still runs npm**

**Solutions:**
1. Double-check script was saved
2. Clear browser cache and refresh
3. Check if there's a **"Quick Deploy"** hook that has npm
4. Check **Daemons** section for any npm processes
5. Verify you edited the correct site

### **Problem: Application breaks after removing npm**

**Solutions:**
1. This shouldn't happen - Laravel doesn't need npm
2. Check if you're using Laravel Mix/Vite for frontend assets
3. If yes, those should be built separately (not in backend deployment)
4. Frontend should be deployed separately to CDN/static hosting

---

## 📞 Need Help?

**Laravel Forge Support:**
- Documentation: https://forge.laravel.com/docs
- Support: support@forge.laravel.com
- Community: Laravel Forge Discord

**Common Issues:**
- Can't access deployment script → Check permissions
- Script not saving → Try different browser
- Still seeing npm → Check all hooks/daemons

---

## 🎯 Summary

**Where to Find:**
1. Log into Laravel Forge
2. Go to: Server → Site → **Deploy** tab
3. Find **"Deploy Script"** text area

**What to Remove:**
- `npm install`
- `npm run build`
- Any npm-related commands

**What to Keep:**
- `composer install`
- `php artisan migrate`
- `php artisan config:cache`
- `php artisan route:cache`
- `php artisan view:cache`
- `php artisan queue:restart`

**Result:**
- ✅ Faster deployments
- ✅ More reliable
- ✅ No Node.js dependency needed

---

**Last Updated:** January 10, 2026  
**Status:** Ready to Fix
