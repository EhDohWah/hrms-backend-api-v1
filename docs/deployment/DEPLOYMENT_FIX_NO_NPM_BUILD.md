# Fix: Laravel Backend Running npm run build on Deployment

**Issue:** Laravel backend is incorrectly running `npm run build` during deployment  
**Date:** January 10, 2026  
**Status:** 🔴 Problem Identified

---

## 🚨 Problem

**Laravel backend (PHP) should NOT run `npm run build` during deployment.**

### Why This is Wrong:

1. **Laravel is PHP** - It doesn't need Node.js/npm for deployment
2. **Frontend is Separate** - Your Vue.js frontend (`hrms-frontend-dev`) is a separate project
3. **Waste of Resources** - Running npm build in backend wastes time and resources
4. **Potential Errors** - May fail if Node.js isn't installed on server

### What Should Happen:

**Backend Deployment Should Only Run:**
```bash
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force
php artisan optimize
```

**Frontend Deployment Should Run:**
```bash
npm install
npm run build
# Then deploy dist/ folder
```

---

## 🔍 Where This Might Be Configured

### **1. Laravel Forge Deployment Script**

If using Laravel Forge, check your deployment script:

**Location:** Forge Dashboard → Your Site → Deployment Script

**❌ Wrong:**
```bash
cd /home/forge/your-site
git pull origin main
composer install --no-dev --optimize-autoloader
npm install
npm run build  # ❌ REMOVE THIS
php artisan migrate --force
php artisan optimize
```

**✅ Correct:**
```bash
cd /home/forge/your-site
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

---

### **2. GitHub Actions / GitLab CI**

If using CI/CD, check your workflow file:

**Location:** `.github/workflows/deploy.yml` or `.gitlab-ci.yml`

**❌ Wrong:**
```yaml
- name: Install Dependencies
  run: composer install --no-dev

- name: Build Assets
  run: npm install && npm run build  # ❌ REMOVE THIS

- name: Deploy
  run: php artisan migrate --force
```

**✅ Correct:**
```yaml
- name: Install Dependencies
  run: composer install --no-dev --optimize-autoloader

- name: Cache Configuration
  run: |
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache

- name: Deploy
  run: |
    php artisan migrate --force
    php artisan optimize
```

---

### **3. Custom Deployment Script**

If you have a custom `deploy.sh` or similar:

**Location:** `deploy.sh`, `deploy.php`, or similar in project root

**❌ Wrong:**
```bash
#!/bin/bash
composer install --no-dev
npm install
npm run build  # ❌ REMOVE THIS
php artisan migrate --force
```

**✅ Correct:**
```bash
#!/bin/bash
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force
php artisan optimize
```

---

### **4. Dockerfile**

If using Docker:

**Location:** `Dockerfile` in project root

**❌ Wrong:**
```dockerfile
FROM php:8.2-fpm

# Install Node.js
RUN curl -fsSL https://deb.nodesource.com/setup_18.x | bash -
RUN apt-get install -y nodejs

# Install dependencies
RUN composer install --no-dev
RUN npm install
RUN npm run build  # ❌ REMOVE THIS

# Run migrations
RUN php artisan migrate --force
```

**✅ Correct:**
```dockerfile
FROM php:8.2-fpm

# Install PHP dependencies only
RUN composer install --no-dev --optimize-autoloader

# Cache Laravel
RUN php artisan config:cache
RUN php artisan route:cache
RUN php artisan view:cache

# Run migrations
RUN php artisan migrate --force
RUN php artisan optimize
```

---

### **5. Platform-Specific (Laravel Vapor, Heroku, etc.)**

#### **Laravel Vapor**

Vapor doesn't need npm build. Check `vapor.yml`:

**✅ Correct vapor.yml:**
```yaml
id: your-project-id
name: hrms-backend-api-v1
environments:
  production:
    deploy:
      - 'composer install --no-dev --optimize-autoloader'
      - 'php artisan migrate --force'
      - 'php artisan config:cache'
      - 'php artisan route:cache'
      - 'php artisan optimize'
```

#### **Heroku**

Check `composer.json` scripts or `Procfile`:

**✅ Correct Procfile:**
```
web: vendor/bin/heroku-php-apache2 public/
```

**No npm build needed!**

---

## 🔧 How to Fix

### **Step 1: Identify Where npm build is Called**

Check these locations:

1. **Laravel Forge:** Dashboard → Site → Deployment Script
2. **GitHub Actions:** `.github/workflows/*.yml`
3. **GitLab CI:** `.gitlab-ci.yml`
4. **Custom Script:** `deploy.sh`, `deploy.php`, etc.
5. **Docker:** `Dockerfile`
6. **Platform Config:** `vapor.yml`, `Procfile`, etc.

### **Step 2: Remove npm Commands**

Remove these lines:
```bash
npm install
npm run build
npm run dev
npm ci
```

### **Step 3: Verify Backend Has No package.json**

Check if `package.json` exists in backend:
```bash
cd hrms-backend-api-v1
ls -la package.json  # Should not exist
```

If it exists, **delete it** (unless you have a specific reason to keep it).

### **Step 4: Update Deployment Script**

Use this standard Laravel deployment script:

```bash
#!/bin/bash

cd /path/to/your/backend

# Pull latest code
git pull origin main

# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Clear and cache configuration
php artisan config:clear
php artisan config:cache

# Clear and cache routes
php artisan route:clear
php artisan route:cache

# Clear and cache views
php artisan view:clear
php artisan view:cache

# Run migrations
php artisan migrate --force

# Optimize Laravel
php artisan optimize

# Clear application cache
php artisan cache:clear

# Restart services (if using supervisor/queue workers)
# php artisan queue:restart
```

### **Step 5: Test Deployment**

1. Make changes to deployment script
2. Deploy to staging/test environment
3. Verify deployment completes successfully
4. Check deployment logs - should NOT see npm commands
5. Verify application works correctly

---

## ✅ Correct Deployment Flow

### **Backend (Laravel)**
```
1. Git pull
2. composer install --no-dev --optimize-autoloader
3. php artisan config:cache
4. php artisan route:cache
5. php artisan view:cache
6. php artisan migrate --force
7. php artisan optimize
8. Restart PHP-FPM / Queue workers
```

### **Frontend (Vue.js) - Separate Project**
```
1. Git pull
2. npm install
3. npm run build
4. Deploy dist/ folder to CDN/static hosting
```

---

## 🎯 Why This Happens

Common reasons:

1. **Copy-Paste Error** - Copied frontend deployment script to backend
2. **Laravel Vite Confusion** - Thought Laravel needs Vite (it doesn't for API-only)
3. **Old Laravel Setup** - Legacy Laravel used Mix/Webpack (not needed for API)
4. **Platform Default** - Some platforms auto-detect and run npm

---

## 📊 Performance Impact

**Before (With npm build):**
- Deployment time: ~5-10 minutes
- Server resources: Node.js + PHP
- Potential failures: npm install errors

**After (Without npm build):**
- Deployment time: ~1-2 minutes
- Server resources: PHP only
- More reliable: Fewer dependencies

---

## 🔍 Verification

After fixing, verify deployment logs show:

**✅ Should See:**
```
✓ composer install --no-dev --optimize-autoloader
✓ php artisan config:cache
✓ php artisan route:cache
✓ php artisan view:cache
✓ php artisan migrate --force
✓ php artisan optimize
```

**❌ Should NOT See:**
```
✗ npm install
✗ npm run build
✗ npm ci
```

---

## 🆘 Still Having Issues?

### **Check These:**

1. **Platform Documentation**
   - Laravel Forge: https://forge.laravel.com/docs
   - Laravel Vapor: https://docs.vapor.build
   - Your hosting provider docs

2. **Composer Scripts**
   - Check `composer.json` scripts section
   - Remove any npm commands from there

3. **Environment Variables**
   - Check if any env vars trigger npm build
   - Look for `NPM_BUILD=true` or similar

4. **Server Configuration**
   - Check nginx/apache config
   - Check supervisor config for queue workers

---

## 📝 Summary

**Problem:** Backend running `npm run build` unnecessarily  
**Solution:** Remove npm commands from backend deployment  
**Result:** Faster, more reliable deployments  

**Remember:**
- ✅ Backend = PHP + Composer
- ✅ Frontend = Node.js + npm
- ✅ Keep them separate!

---

**Last Updated:** January 10, 2026  
**Status:** Ready to Fix
