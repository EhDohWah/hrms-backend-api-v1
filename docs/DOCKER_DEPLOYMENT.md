# HRMS Docker Deployment Guide

> **Version:** 1.0.0
> **Last Updated:** February 2026
> **Compatibility:** Laravel 12, Vue 3, SQL Server 2022, PHP 8.3

Complete guide to containerize and deploy the HRMS application (backend API + frontend) using Docker.

---

## Table of Contents

1. [Overview](#overview)
2. [Prerequisites](#prerequisites)
3. [Repository Structure](#repository-structure)
4. [Backend Dockerfile](#backend-dockerfile)
5. [Frontend Dockerfile](#frontend-dockerfile)
6. [Docker Compose Configuration](#docker-compose-configuration)
7. [Nginx Configuration](#nginx-configuration)
8. [Environment Configuration](#environment-configuration)
9. [Deployment Steps](#deployment-steps)
10. [Development Setup](#development-setup)
11. [SSL/HTTPS Setup](#sslhttps-setup)
12. [Backup and Restore](#backup-and-restore)
13. [Maintenance and Updates](#maintenance-and-updates)
14. [Troubleshooting](#troubleshooting)
15. [Scaling](#scaling)

---

## Overview

### Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────────────┐
│                           Docker Network                                 │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│    Internet                                                             │
│        │                                                                │
│        ▼                                                                │
│   ┌─────────┐                                                          │
│   │  Nginx  │ :80/:443 (Reverse Proxy + SSL)                           │
│   └────┬────┘                                                          │
│        │                                                                │
│   ┌────┴────────────────────────┐                                      │
│   │              │              │                                       │
│   ▼              ▼              ▼                                       │
│ /api/*      /app/* (WS)      /* (static)                               │
│   │              │              │                                       │
│   ▼              ▼              ▼                                       │
│ ┌──────────┐ ┌──────────┐ ┌──────────┐                                 │
│ │ Backend  │ │  Reverb  │ │ Frontend │                                 │
│ │ PHP-FPM  │ │ WebSocket│ │  Nginx   │                                 │
│ │  :9000   │ │  :6001   │ │  :80     │                                 │
│ └────┬─────┘ └──────────┘ └──────────┘                                 │
│      │                                                                  │
│      ├───────────────────┬───────────────┐                             │
│      ▼                   ▼               ▼                              │
│ ┌──────────┐       ┌──────────┐    ┌──────────┐                        │
│ │  MSSQL   │       │  Redis   │    │  Queue   │                        │
│ │  :1433   │       │  :6379   │    │ Workers  │                        │
│ └──────────┘       └──────────┘    └──────────┘                        │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

### Services Overview

| Service | Image | Port | Purpose |
|---------|-------|------|---------|
| `nginx` | nginx:1.25-alpine | 80, 443 | Reverse proxy, SSL termination, load balancing |
| `backend` | Custom PHP 8.3-FPM | 9000 (internal) | Laravel API application |
| `frontend` | Custom nginx:alpine | 8080 (internal) | Vue.js SPA static files |
| `mssql` | mcr.microsoft.com/mssql/server:2022-latest | 1433 (internal) | SQL Server Express database |
| `queue` | Same as backend | - | Laravel queue workers (Supervisor) |
| `reverb` | Same as backend | 6001 | Laravel Reverb WebSocket server |
| `redis` | redis:7-alpine | 6379 (internal) | Cache and queue broker |

---

## Prerequisites

### Server Requirements

| Environment | RAM | CPU | Storage | OS |
|-------------|-----|-----|---------|-----|
| Development | 4 GB | 2 cores | 20 GB | Windows/macOS/Linux |
| Production | 8 GB | 4 cores | 50 GB+ | Ubuntu 22.04/24.04 LTS |

### Software Requirements

- Docker Engine 24.0+
- Docker Compose 2.20+
- Git 2.40+

### Verify Installation

```bash
docker --version        # Docker version 24.0.0+
docker compose version  # Docker Compose version v2.20.0+
git --version          # git version 2.40.0+
```

---

## Repository Structure

Create a new deployment repository with this structure:

```
hrms-deployment/
├── docker-compose.yml              # Production orchestration
├── docker-compose.override.yml     # Development overrides (auto-loaded)
├── .env.example                    # Environment template
├── .env                            # Your environment (git-ignored)
├── .gitignore
├── README.md
│
├── nginx/                          # Main reverse proxy
│   ├── nginx.conf                  # Production config
│   ├── nginx.dev.conf              # Development config
│   └── ssl/                        # SSL certificates (git-ignored)
│       ├── fullchain.pem
│       └── privkey.pem
│
├── backend/                        # Laravel API (git submodule)
│   ├── Dockerfile
│   ├── .dockerignore
│   └── docker/
│       ├── nginx.conf              # PHP-FPM upstream
│       ├── php.ini                 # Production PHP settings
│       ├── php.dev.ini             # Development PHP settings
│       ├── supervisord.conf        # Process manager
│       └── entrypoint.sh           # Container init script
│
├── frontend/                       # Vue.js SPA (git submodule)
│   ├── Dockerfile
│   ├── .dockerignore
│   └── docker/
│       └── nginx.conf              # SPA routing
│
└── database/
    └── init/                       # Optional SQL init scripts
        └── 01-create-database.sql
```

### Initialize with Git Submodules

```bash
# Create deployment repository
mkdir hrms-deployment && cd hrms-deployment
git init

# Add backend and frontend as submodules
git submodule add https://github.com/YOUR_ORG/hrms-backend-api-v1.git backend
git submodule add https://github.com/YOUR_ORG/hrms-frontend-dev.git frontend

# Create directory structure
mkdir -p nginx/ssl database/init
```

---

## Backend Dockerfile

Create `backend/Dockerfile`:

```dockerfile
# =============================================================================
# HRMS Backend API - Production Dockerfile
# Laravel 12 + PHP 8.3 + SQL Server
# =============================================================================

# -----------------------------------------------------------------------------
# Stage 1: Composer Dependencies
# -----------------------------------------------------------------------------
FROM composer:2.7 AS composer

WORKDIR /app

# Copy composer files first (for better caching)
COPY composer.json composer.lock ./

# Install dependencies without dev packages
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --no-interaction

# Copy application code
COPY . .

# Generate optimized autoloader
RUN composer dump-autoload --optimize --no-dev

# -----------------------------------------------------------------------------
# Stage 2: Production Application
# -----------------------------------------------------------------------------
FROM php:8.3-fpm-bookworm AS production

LABEL maintainer="HRMS Team"
LABEL description="HRMS Backend API - Laravel 12"

# Set environment
ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=UTC

# Install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    # Basic utilities
    curl \
    git \
    unzip \
    zip \
    # Required for PHP extensions
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libicu-dev \
    libxml2-dev \
    libonig-dev \
    # Required for SQL Server
    gnupg2 \
    apt-transport-https \
    # Process manager
    supervisor \
    # Clean up
    && rm -rf /var/lib/apt/lists/*

# -----------------------------------------------------------------------------
# Install Microsoft ODBC Driver 18 for SQL Server
# -----------------------------------------------------------------------------
RUN curl -fsSL https://packages.microsoft.com/keys/microsoft.asc | gpg --dearmor -o /usr/share/keyrings/microsoft-prod.gpg \
    && echo "deb [arch=amd64 signed-by=/usr/share/keyrings/microsoft-prod.gpg] https://packages.microsoft.com/debian/12/prod bookworm main" > /etc/apt/sources.list.d/mssql-release.list \
    && apt-get update \
    && ACCEPT_EULA=Y apt-get install -y --no-install-recommends \
        msodbcsql18 \
        unixodbc-dev \
    && rm -rf /var/lib/apt/lists/*

# -----------------------------------------------------------------------------
# Install PHP Extensions
# -----------------------------------------------------------------------------

# Configure and install GD
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd

# Install other PHP extensions
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    mysqli \
    zip \
    bcmath \
    intl \
    opcache \
    pcntl \
    xml \
    mbstring \
    exif

# Install SQL Server extensions (pdo_sqlsrv & sqlsrv)
RUN pecl install sqlsrv-5.12.0 pdo_sqlsrv-5.12.0 \
    && docker-php-ext-enable sqlsrv pdo_sqlsrv

# Install Redis extension
RUN pecl install redis \
    && docker-php-ext-enable redis

# -----------------------------------------------------------------------------
# Configure PHP
# -----------------------------------------------------------------------------
COPY docker/php.ini /usr/local/etc/php/conf.d/99-hrms.ini

# -----------------------------------------------------------------------------
# Configure Supervisor
# -----------------------------------------------------------------------------
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# -----------------------------------------------------------------------------
# Application Setup
# -----------------------------------------------------------------------------
WORKDIR /var/www/html

# Copy application from composer stage
COPY --from=composer /app/vendor ./vendor
COPY . .

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Copy and set entrypoint
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Expose PHP-FPM port
EXPOSE 9000

# Health check
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD php-fpm-healthcheck || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
```

### Backend .dockerignore

Create `backend/.dockerignore`:

```
# Git
.git
.gitignore
.gitattributes

# IDE
.idea
.vscode
*.swp
*.swo

# Dependencies (will be installed in build)
/vendor

# Development files
.env
.env.local
.env.*.local
phpunit.xml
.phpunit.result.cache
.php-cs-fixer.cache

# Testing
/tests
/coverage
*.test.php

# Documentation
/docs
*.md
!README.md

# Docker files in subdirectories
docker-compose*.yml

# OS files
.DS_Store
Thumbs.db

# Logs
*.log
/storage/logs/*
!/storage/logs/.gitignore

# Cache
/storage/framework/cache/*
!/storage/framework/cache/.gitignore
/storage/framework/sessions/*
!/storage/framework/sessions/.gitignore
/storage/framework/views/*
!/storage/framework/views/.gitignore
/bootstrap/cache/*
!/bootstrap/cache/.gitignore

# Node (if any)
node_modules
```

### Backend PHP Configuration

Create `backend/docker/php.ini`:

```ini
; =============================================================================
; HRMS Production PHP Configuration
; =============================================================================

[PHP]
; Error handling
display_errors = Off
display_startup_errors = Off
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT
log_errors = On
error_log = /var/www/html/storage/logs/php-error.log

; Performance
memory_limit = 256M
max_execution_time = 120
max_input_time = 120
post_max_size = 100M
upload_max_filesize = 100M
max_file_uploads = 20

; Security
expose_php = Off
allow_url_fopen = On
allow_url_include = Off

; Sessions
session.cookie_httponly = 1
session.cookie_secure = 1
session.use_strict_mode = 1

; Date
date.timezone = UTC

[opcache]
opcache.enable = 1
opcache.enable_cli = 1
opcache.memory_consumption = 128
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 10000
opcache.revalidate_freq = 0
opcache.validate_timestamps = 0
opcache.save_comments = 1

[sqlsrv]
; SQL Server specific settings
sqlsrv.ClientBufferMaxKBSize = 10240

[pdo_sqlsrv]
; PDO SQL Server settings
pdo_sqlsrv.client_buffer_max_kb_size = 10240
```

### Backend Supervisor Configuration

Create `backend/docker/supervisord.conf`:

```ini
; =============================================================================
; HRMS Supervisor Configuration
; =============================================================================

[supervisord]
nodaemon=true
user=root
logfile=/var/log/supervisor/supervisord.log
pidfile=/var/run/supervisord.pid
childlogdir=/var/log/supervisor

[program:php-fpm]
command=php-fpm -F
autostart=true
autorestart=true
priority=5
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

; =============================================================================
; Queue Workers (for queue service only)
; =============================================================================
; Uncomment these when running the queue container

;[program:queue-default]
;process_name=%(program_name)s_%(process_num)02d
;command=php /var/www/html/artisan queue:work redis --sleep=3 --tries=3 --timeout=90 --memory=128
;autostart=true
;autorestart=true
;stopasgroup=true
;killasgroup=true
;user=www-data
;numprocs=2
;redirect_stderr=true
;stdout_logfile=/var/www/html/storage/logs/queue-worker.log
;stopwaitsecs=3600
```

### Backend Entrypoint Script

Create `backend/docker/entrypoint.sh`:

```bash
#!/bin/bash
set -e

# =============================================================================
# HRMS Backend Entrypoint Script
# =============================================================================

echo "=========================================="
echo "HRMS Backend API - Container Starting"
echo "=========================================="

# -----------------------------------------------------------------------------
# Wait for Database
# -----------------------------------------------------------------------------
echo "[1/6] Waiting for database connection..."

MAX_RETRIES=30
RETRY_COUNT=0

until php artisan tinker --execute="DB::connection()->getPdo();" 2>/dev/null; do
    RETRY_COUNT=$((RETRY_COUNT + 1))
    if [ $RETRY_COUNT -ge $MAX_RETRIES ]; then
        echo "ERROR: Database connection failed after $MAX_RETRIES attempts"
        exit 1
    fi
    echo "  Waiting for database... (attempt $RETRY_COUNT/$MAX_RETRIES)"
    sleep 5
done

echo "  Database connected successfully!"

# -----------------------------------------------------------------------------
# Generate App Key (if not set)
# -----------------------------------------------------------------------------
echo "[2/6] Checking application key..."

if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:" ]; then
    echo "  Generating new application key..."
    php artisan key:generate --force
fi

# -----------------------------------------------------------------------------
# Run Migrations (Production Safe)
# -----------------------------------------------------------------------------
echo "[3/6] Running database migrations..."

php artisan migrate --force --no-interaction

# -----------------------------------------------------------------------------
# Cache Configuration
# -----------------------------------------------------------------------------
echo "[4/6] Caching configuration..."

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# -----------------------------------------------------------------------------
# Storage Link
# -----------------------------------------------------------------------------
echo "[5/6] Creating storage link..."

php artisan storage:link --force 2>/dev/null || true

# -----------------------------------------------------------------------------
# Set Permissions
# -----------------------------------------------------------------------------
echo "[6/6] Setting permissions..."

chown -R www-data:www-data /var/www/html/storage
chown -R www-data:www-data /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage
chmod -R 775 /var/www/html/bootstrap/cache

echo "=========================================="
echo "HRMS Backend API - Ready!"
echo "=========================================="

# Execute the main command
exec "$@"
```

### Backend Nginx Configuration

Create `backend/docker/nginx.conf`:

```nginx
# =============================================================================
# HRMS Backend - Nginx Configuration (PHP-FPM Upstream)
# =============================================================================

server {
    listen 80;
    server_name _;

    root /var/www/html/public;
    index index.php;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Logging
    access_log /var/log/nginx/access.log;
    error_log /var/log/nginx/error.log;

    # Max upload size
    client_max_body_size 100M;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_proxied any;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml;

    # Handle Laravel routes
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM handling
    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;

        fastcgi_hide_header X-Powered-By;
        fastcgi_read_timeout 120;
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
    }

    # Deny access to hidden files
    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Deny access to sensitive files
    location ~* \.(env|log|git|svn|htaccess)$ {
        deny all;
    }
}
```

---

## Frontend Dockerfile

Create `frontend/Dockerfile`:

```dockerfile
# =============================================================================
# HRMS Frontend - Production Dockerfile
# Vue.js 3 + Vite
# =============================================================================

# -----------------------------------------------------------------------------
# Stage 1: Build
# -----------------------------------------------------------------------------
FROM node:20-alpine AS builder

WORKDIR /app

# Install dependencies first (better caching)
COPY package.json package-lock.json ./
RUN npm ci --prefer-offline

# Copy source code
COPY . .

# Build arguments for environment variables
# These MUST be passed at build time
ARG VITE_API_BASE_URL
ARG VITE_PUBLIC_URL
ARG VITE_ENV=production
ARG VITE_REVERB_APP_KEY
ARG VITE_REVERB_HOST
ARG VITE_REVERB_PORT
ARG VITE_REVERB_SCHEME
ARG VITE_BROADCASTING_AUTH_ENDPOINT

# Set environment variables for build
ENV VITE_API_BASE_URL=$VITE_API_BASE_URL
ENV VITE_PUBLIC_URL=$VITE_PUBLIC_URL
ENV VITE_ENV=$VITE_ENV
ENV VITE_REVERB_APP_KEY=$VITE_REVERB_APP_KEY
ENV VITE_REVERB_HOST=$VITE_REVERB_HOST
ENV VITE_REVERB_PORT=$VITE_REVERB_PORT
ENV VITE_REVERB_SCHEME=$VITE_REVERB_SCHEME
ENV VITE_BROADCASTING_AUTH_ENDPOINT=$VITE_BROADCASTING_AUTH_ENDPOINT

# Increase Node memory for large builds
ENV NODE_OPTIONS="--max-old-space-size=4096"

# Build the application
RUN npm run build

# -----------------------------------------------------------------------------
# Stage 2: Production Server
# -----------------------------------------------------------------------------
FROM nginx:1.25-alpine AS production

LABEL maintainer="HRMS Team"
LABEL description="HRMS Frontend - Vue.js SPA"

# Copy built assets from builder
COPY --from=builder /app/dist /usr/share/nginx/html

# Copy nginx configuration
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf

# Create non-root user
RUN addgroup -g 1001 -S nginx-app && \
    adduser -S -D -H -u 1001 -h /var/cache/nginx -s /sbin/nologin -G nginx-app nginx-app && \
    chown -R nginx-app:nginx-app /usr/share/nginx/html && \
    chown -R nginx-app:nginx-app /var/cache/nginx && \
    touch /var/run/nginx.pid && \
    chown -R nginx-app:nginx-app /var/run/nginx.pid

# Expose port
EXPOSE 80

# Health check
HEALTHCHECK --interval=30s --timeout=5s --start-period=5s --retries=3 \
    CMD wget --no-verbose --tries=1 --spider http://localhost/ || exit 1

CMD ["nginx", "-g", "daemon off;"]
```

### Frontend .dockerignore

Create `frontend/.dockerignore`:

```
# Git
.git
.gitignore

# Dependencies
node_modules

# Build output (we build inside Docker)
dist
build

# IDE
.idea
.vscode
*.swp

# Environment (should be passed as build args)
.env
.env.*
!.env.example

# Documentation
*.md

# Tests
tests
coverage
*.test.js
*.spec.js

# OS files
.DS_Store
Thumbs.db

# Misc
.netlify
.cursorrules
```

### Frontend Nginx Configuration

Create `frontend/docker/nginx.conf`:

```nginx
# =============================================================================
# HRMS Frontend - Nginx SPA Configuration
# =============================================================================

server {
    listen 80;
    server_name _;

    root /usr/share/nginx/html;
    index index.html;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types
        text/plain
        text/css
        text/javascript
        application/javascript
        application/json
        application/xml
        image/svg+xml;

    # Static file caching
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # SPA routing - redirect all requests to index.html
    location / {
        try_files $uri $uri/ /index.html;
    }

    # Deny access to hidden files
    location ~ /\. {
        deny all;
    }

    # Health check endpoint
    location /health {
        access_log off;
        return 200 "OK";
        add_header Content-Type text/plain;
    }
}
```

---

## Docker Compose Configuration

### Production Configuration

Create `docker-compose.yml`:

```yaml
# =============================================================================
# HRMS Docker Compose - Production Configuration
# =============================================================================
# Usage:
#   docker compose up -d
#   docker compose logs -f
#   docker compose down
# =============================================================================

name: hrms

services:
  # ---------------------------------------------------------------------------
  # Reverse Proxy (Nginx)
  # ---------------------------------------------------------------------------
  nginx:
    image: nginx:1.25-alpine
    container_name: hrms-nginx
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./nginx/nginx.conf:/etc/nginx/nginx.conf:ro
      - ./nginx/ssl:/etc/nginx/ssl:ro
    depends_on:
      backend:
        condition: service_healthy
      frontend:
        condition: service_healthy
    networks:
      - hrms-network
    healthcheck:
      test: ["CMD", "nginx", "-t"]
      interval: 30s
      timeout: 10s
      retries: 3

  # ---------------------------------------------------------------------------
  # Backend API (Laravel)
  # ---------------------------------------------------------------------------
  backend:
    build:
      context: ./backend
      dockerfile: Dockerfile
      target: production
    container_name: hrms-backend
    restart: unless-stopped
    expose:
      - "9000"
    environment:
      APP_NAME: ${APP_NAME:-HRMS}
      APP_ENV: ${APP_ENV:-production}
      APP_DEBUG: ${APP_DEBUG:-false}
      APP_URL: ${APP_URL}
      APP_KEY: ${APP_KEY}
      APP_FRONTEND_URL: ${APP_FRONTEND_URL}

      DB_CONNECTION: sqlsrv
      DB_HOST: mssql
      DB_PORT: 1433
      DB_DATABASE: ${DB_DATABASE:-hrms}
      DB_USERNAME: ${DB_USERNAME:-sa}
      DB_PASSWORD: ${DB_PASSWORD}
      DB_TRUST_SERVER_CERTIFICATE: "true"

      REDIS_HOST: redis
      REDIS_PORT: 6379
      REDIS_PASSWORD: ${REDIS_PASSWORD:-null}

      CACHE_STORE: redis
      SESSION_DRIVER: redis
      QUEUE_CONNECTION: redis

      BROADCAST_CONNECTION: reverb
      REVERB_APP_ID: ${REVERB_APP_ID}
      REVERB_APP_KEY: ${REVERB_APP_KEY}
      REVERB_APP_SECRET: ${REVERB_APP_SECRET}

      MAIL_MAILER: ${MAIL_MAILER:-smtp}
      MAIL_HOST: ${MAIL_HOST}
      MAIL_PORT: ${MAIL_PORT:-587}
      MAIL_USERNAME: ${MAIL_USERNAME}
      MAIL_PASSWORD: ${MAIL_PASSWORD}
      MAIL_ENCRYPTION: ${MAIL_ENCRYPTION:-tls}
      MAIL_FROM_ADDRESS: ${MAIL_FROM_ADDRESS}
      MAIL_FROM_NAME: ${APP_NAME:-HRMS}
    volumes:
      - backend-storage:/var/www/html/storage/app
      - backend-logs:/var/www/html/storage/logs
    depends_on:
      mssql:
        condition: service_healthy
      redis:
        condition: service_healthy
    networks:
      - hrms-network
    healthcheck:
      test: ["CMD-SHELL", "php-fpm-healthcheck || exit 1"]
      interval: 30s
      timeout: 5s
      start_period: 60s
      retries: 3

  # ---------------------------------------------------------------------------
  # Frontend (Vue.js SPA)
  # ---------------------------------------------------------------------------
  frontend:
    build:
      context: ./frontend
      dockerfile: Dockerfile
      target: production
      args:
        VITE_API_BASE_URL: ${VITE_API_BASE_URL}
        VITE_PUBLIC_URL: ${VITE_PUBLIC_URL}
        VITE_ENV: production
        VITE_REVERB_APP_KEY: ${REVERB_APP_KEY}
        VITE_REVERB_HOST: ${VITE_REVERB_HOST}
        VITE_REVERB_PORT: ${VITE_REVERB_PORT:-6001}
        VITE_REVERB_SCHEME: ${VITE_REVERB_SCHEME:-wss}
        VITE_BROADCASTING_AUTH_ENDPOINT: ${VITE_BROADCASTING_AUTH_ENDPOINT}
    container_name: hrms-frontend
    restart: unless-stopped
    expose:
      - "80"
    networks:
      - hrms-network
    healthcheck:
      test: ["CMD", "wget", "--no-verbose", "--tries=1", "--spider", "http://localhost/health"]
      interval: 30s
      timeout: 5s
      start_period: 10s
      retries: 3

  # ---------------------------------------------------------------------------
  # Database (SQL Server)
  # ---------------------------------------------------------------------------
  mssql:
    image: mcr.microsoft.com/mssql/server:2022-latest
    container_name: hrms-mssql
    restart: unless-stopped
    environment:
      ACCEPT_EULA: "Y"
      MSSQL_SA_PASSWORD: ${DB_PASSWORD}
      MSSQL_PID: Express
      MSSQL_COLLATION: SQL_Latin1_General_CP1_CI_AS
    volumes:
      - mssql-data:/var/opt/mssql
      - ./database/init:/docker-entrypoint-initdb.d:ro
    networks:
      - hrms-network
    # SQL Server requires at least 2GB RAM
    deploy:
      resources:
        limits:
          memory: 2G
        reservations:
          memory: 2G
    healthcheck:
      test: /opt/mssql-tools18/bin/sqlcmd -S localhost -U sa -P "${DB_PASSWORD}" -C -Q "SELECT 1" -b -o /dev/null
      interval: 10s
      timeout: 5s
      start_period: 30s
      retries: 10

  # ---------------------------------------------------------------------------
  # Queue Worker
  # ---------------------------------------------------------------------------
  queue:
    build:
      context: ./backend
      dockerfile: Dockerfile
      target: production
    container_name: hrms-queue
    restart: unless-stopped
    command: php artisan queue:work redis --sleep=3 --tries=3 --timeout=90 --memory=128 --queue=default,notifications
    environment:
      APP_NAME: ${APP_NAME:-HRMS}
      APP_ENV: ${APP_ENV:-production}
      APP_DEBUG: ${APP_DEBUG:-false}
      APP_KEY: ${APP_KEY}

      DB_CONNECTION: sqlsrv
      DB_HOST: mssql
      DB_PORT: 1433
      DB_DATABASE: ${DB_DATABASE:-hrms}
      DB_USERNAME: ${DB_USERNAME:-sa}
      DB_PASSWORD: ${DB_PASSWORD}
      DB_TRUST_SERVER_CERTIFICATE: "true"

      REDIS_HOST: redis
      REDIS_PORT: 6379
      REDIS_PASSWORD: ${REDIS_PASSWORD:-null}

      QUEUE_CONNECTION: redis
    depends_on:
      backend:
        condition: service_healthy
    networks:
      - hrms-network
    deploy:
      replicas: 2
      resources:
        limits:
          memory: 256M

  # ---------------------------------------------------------------------------
  # WebSocket Server (Laravel Reverb)
  # ---------------------------------------------------------------------------
  reverb:
    build:
      context: ./backend
      dockerfile: Dockerfile
      target: production
    container_name: hrms-reverb
    restart: unless-stopped
    command: php artisan reverb:start --host=0.0.0.0 --port=6001
    ports:
      - "6001:6001"
    environment:
      APP_NAME: ${APP_NAME:-HRMS}
      APP_ENV: ${APP_ENV:-production}
      APP_DEBUG: ${APP_DEBUG:-false}
      APP_KEY: ${APP_KEY}

      DB_CONNECTION: sqlsrv
      DB_HOST: mssql
      DB_PORT: 1433
      DB_DATABASE: ${DB_DATABASE:-hrms}
      DB_USERNAME: ${DB_USERNAME:-sa}
      DB_PASSWORD: ${DB_PASSWORD}
      DB_TRUST_SERVER_CERTIFICATE: "true"

      REDIS_HOST: redis
      REDIS_PORT: 6379

      REVERB_SERVER: reverb
      REVERB_APP_ID: ${REVERB_APP_ID}
      REVERB_APP_KEY: ${REVERB_APP_KEY}
      REVERB_APP_SECRET: ${REVERB_APP_SECRET}
      REVERB_HOST: 0.0.0.0
      REVERB_PORT: 6001
      REVERB_SCHEME: http
    depends_on:
      backend:
        condition: service_healthy
    networks:
      - hrms-network
    healthcheck:
      test: ["CMD", "php", "-r", "exit(fsockopen('localhost', 6001) ? 0 : 1);"]
      interval: 30s
      timeout: 5s
      retries: 3

  # ---------------------------------------------------------------------------
  # Redis (Cache & Queue)
  # ---------------------------------------------------------------------------
  redis:
    image: redis:7-alpine
    container_name: hrms-redis
    restart: unless-stopped
    command: redis-server --appendonly yes --maxmemory 256mb --maxmemory-policy allkeys-lru
    volumes:
      - redis-data:/data
    networks:
      - hrms-network
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 10s
      timeout: 5s
      retries: 5

# =============================================================================
# Networks
# =============================================================================
networks:
  hrms-network:
    driver: bridge

# =============================================================================
# Volumes
# =============================================================================
volumes:
  mssql-data:
    driver: local
  backend-storage:
    driver: local
  backend-logs:
    driver: local
  redis-data:
    driver: local
```

### Development Overrides

Create `docker-compose.override.yml`:

```yaml
# =============================================================================
# HRMS Docker Compose - Development Overrides
# =============================================================================
# This file is automatically loaded with docker-compose.yml
# For production, rename this file or use: docker compose -f docker-compose.yml up
# =============================================================================

services:
  # ---------------------------------------------------------------------------
  # Backend - Development Mode
  # ---------------------------------------------------------------------------
  backend:
    build:
      target: production  # Can create a 'development' target with xdebug
    environment:
      APP_ENV: local
      APP_DEBUG: "true"
    volumes:
      # Mount source code for hot reload
      - ./backend:/var/www/html
      - /var/www/html/vendor  # Exclude vendor from mount
    # Disable health check for faster startup
    healthcheck:
      disable: true

  # ---------------------------------------------------------------------------
  # Frontend - Development Mode
  # ---------------------------------------------------------------------------
  frontend:
    build:
      args:
        VITE_ENV: development
        VITE_API_BASE_URL: http://localhost:8000/api/v1
        VITE_REVERB_HOST: localhost
        VITE_REVERB_PORT: "6001"
        VITE_REVERB_SCHEME: http
    healthcheck:
      disable: true

  # ---------------------------------------------------------------------------
  # Database - Expose port for tools
  # ---------------------------------------------------------------------------
  mssql:
    ports:
      - "1433:1433"

  # ---------------------------------------------------------------------------
  # Redis - Expose port for debugging
  # ---------------------------------------------------------------------------
  redis:
    ports:
      - "6379:6379"

  # ---------------------------------------------------------------------------
  # Queue - Single worker for development
  # ---------------------------------------------------------------------------
  queue:
    environment:
      APP_ENV: local
      APP_DEBUG: "true"
    deploy:
      replicas: 1
```

---

## Nginx Configuration

### Main Reverse Proxy

Create `nginx/nginx.conf`:

```nginx
# =============================================================================
# HRMS Main Nginx Configuration - Reverse Proxy
# =============================================================================

user nginx;
worker_processes auto;
error_log /var/log/nginx/error.log warn;
pid /var/run/nginx.pid;

events {
    worker_connections 1024;
    multi_accept on;
    use epoll;
}

http {
    include /etc/nginx/mime.types;
    default_type application/octet-stream;

    # Logging format
    log_format main '$remote_addr - $remote_user [$time_local] "$request" '
                    '$status $body_bytes_sent "$http_referer" '
                    '"$http_user_agent" "$http_x_forwarded_for"';

    access_log /var/log/nginx/access.log main;

    # Performance
    sendfile on;
    tcp_nopush on;
    tcp_nodelay on;
    keepalive_timeout 65;
    types_hash_max_size 2048;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types text/plain text/css text/xml application/json application/javascript
               application/xml application/rss+xml application/atom+xml image/svg+xml;

    # Security
    server_tokens off;

    # Rate limiting
    limit_req_zone $binary_remote_addr zone=api_limit:10m rate=30r/s;
    limit_req_zone $binary_remote_addr zone=general_limit:10m rate=50r/s;

    # Upstreams
    upstream backend {
        server backend:9000;
        keepalive 32;
    }

    upstream frontend {
        server frontend:80;
        keepalive 16;
    }

    upstream reverb {
        server reverb:6001;
        keepalive 16;
    }

    # ==========================================================================
    # HTTP Server (Redirect to HTTPS)
    # ==========================================================================
    server {
        listen 80;
        server_name _;

        # Allow Let's Encrypt challenge
        location /.well-known/acme-challenge/ {
            root /var/www/certbot;
        }

        # Redirect all other traffic to HTTPS
        location / {
            return 301 https://$host$request_uri;
        }
    }

    # ==========================================================================
    # HTTPS Server
    # ==========================================================================
    server {
        listen 443 ssl http2;
        server_name _;

        # SSL Configuration
        ssl_certificate /etc/nginx/ssl/fullchain.pem;
        ssl_certificate_key /etc/nginx/ssl/privkey.pem;
        ssl_session_timeout 1d;
        ssl_session_cache shared:SSL:50m;
        ssl_session_tickets off;

        # Modern SSL configuration
        ssl_protocols TLSv1.2 TLSv1.3;
        ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384;
        ssl_prefer_server_ciphers off;

        # Security headers
        add_header X-Frame-Options "SAMEORIGIN" always;
        add_header X-Content-Type-Options "nosniff" always;
        add_header X-XSS-Protection "1; mode=block" always;
        add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
        add_header Referrer-Policy "strict-origin-when-cross-origin" always;

        # Max upload size
        client_max_body_size 100M;

        # ======================================================================
        # API Routes → Backend (Laravel)
        # ======================================================================
        location /api/ {
            limit_req zone=api_limit burst=50 nodelay;

            proxy_pass http://backend;
            proxy_http_version 1.1;

            proxy_set_header Host $host;
            proxy_set_header X-Real-IP $remote_addr;
            proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
            proxy_set_header X-Forwarded-Proto $scheme;
            proxy_set_header X-Forwarded-Host $host;
            proxy_set_header X-Forwarded-Port $server_port;

            proxy_connect_timeout 60s;
            proxy_send_timeout 120s;
            proxy_read_timeout 120s;

            # CORS headers (if needed)
            add_header Access-Control-Allow-Origin $http_origin always;
            add_header Access-Control-Allow-Methods "GET, POST, PUT, PATCH, DELETE, OPTIONS" always;
            add_header Access-Control-Allow-Headers "Authorization, Content-Type, Accept, X-Requested-With" always;
            add_header Access-Control-Allow-Credentials "true" always;

            if ($request_method = OPTIONS) {
                return 204;
            }
        }

        # ======================================================================
        # Broadcasting Auth → Backend
        # ======================================================================
        location /broadcasting/auth {
            proxy_pass http://backend;
            proxy_http_version 1.1;

            proxy_set_header Host $host;
            proxy_set_header X-Real-IP $remote_addr;
            proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
            proxy_set_header X-Forwarded-Proto $scheme;
        }

        # ======================================================================
        # Sanctum CSRF Cookie → Backend
        # ======================================================================
        location /sanctum/csrf-cookie {
            proxy_pass http://backend;
            proxy_http_version 1.1;

            proxy_set_header Host $host;
            proxy_set_header X-Real-IP $remote_addr;
            proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
            proxy_set_header X-Forwarded-Proto $scheme;
        }

        # ======================================================================
        # WebSocket → Reverb
        # ======================================================================
        location /app/ {
            proxy_pass http://reverb;
            proxy_http_version 1.1;

            proxy_set_header Upgrade $http_upgrade;
            proxy_set_header Connection "upgrade";
            proxy_set_header Host $host;
            proxy_set_header X-Real-IP $remote_addr;
            proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
            proxy_set_header X-Forwarded-Proto $scheme;

            proxy_connect_timeout 7d;
            proxy_send_timeout 7d;
            proxy_read_timeout 7d;
        }

        # ======================================================================
        # Storage Files → Backend (if using public storage)
        # ======================================================================
        location /storage/ {
            proxy_pass http://backend;
            proxy_http_version 1.1;

            proxy_set_header Host $host;
            proxy_set_header X-Real-IP $remote_addr;
            proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
            proxy_set_header X-Forwarded-Proto $scheme;

            # Cache static files
            expires 7d;
            add_header Cache-Control "public, immutable";
        }

        # ======================================================================
        # Health Check
        # ======================================================================
        location /health {
            access_log off;
            return 200 "OK";
            add_header Content-Type text/plain;
        }

        # ======================================================================
        # Frontend (Vue.js SPA) - Default
        # ======================================================================
        location / {
            limit_req zone=general_limit burst=100 nodelay;

            proxy_pass http://frontend;
            proxy_http_version 1.1;

            proxy_set_header Host $host;
            proxy_set_header X-Real-IP $remote_addr;
            proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
            proxy_set_header X-Forwarded-Proto $scheme;
        }
    }
}
```

### Development Nginx (No SSL)

Create `nginx/nginx.dev.conf`:

```nginx
# =============================================================================
# HRMS Nginx Configuration - Development (No SSL)
# =============================================================================

user nginx;
worker_processes auto;
error_log /var/log/nginx/error.log warn;
pid /var/run/nginx.pid;

events {
    worker_connections 1024;
}

http {
    include /etc/nginx/mime.types;
    default_type application/octet-stream;

    log_format main '$remote_addr - $remote_user [$time_local] "$request" '
                    '$status $body_bytes_sent "$http_referer" '
                    '"$http_user_agent"';

    access_log /var/log/nginx/access.log main;

    sendfile on;
    keepalive_timeout 65;

    # Upstreams
    upstream backend {
        server backend:9000;
    }

    upstream frontend {
        server frontend:80;
    }

    upstream reverb {
        server reverb:6001;
    }

    server {
        listen 80;
        server_name localhost;

        client_max_body_size 100M;

        # API
        location /api/ {
            proxy_pass http://backend;
            proxy_http_version 1.1;
            proxy_set_header Host $host;
            proxy_set_header X-Real-IP $remote_addr;
            proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
            proxy_set_header X-Forwarded-Proto $scheme;
        }

        # Broadcasting auth
        location /broadcasting/auth {
            proxy_pass http://backend;
            proxy_http_version 1.1;
            proxy_set_header Host $host;
            proxy_set_header X-Real-IP $remote_addr;
        }

        # WebSocket
        location /app/ {
            proxy_pass http://reverb;
            proxy_http_version 1.1;
            proxy_set_header Upgrade $http_upgrade;
            proxy_set_header Connection "upgrade";
            proxy_set_header Host $host;
        }

        # Storage
        location /storage/ {
            proxy_pass http://backend;
            proxy_http_version 1.1;
            proxy_set_header Host $host;
        }

        # Frontend (default)
        location / {
            proxy_pass http://frontend;
            proxy_http_version 1.1;
            proxy_set_header Host $host;
            proxy_set_header X-Real-IP $remote_addr;
        }
    }
}
```

---

## Environment Configuration

Create `.env.example`:

```env
# =============================================================================
# HRMS Docker Environment Configuration
# =============================================================================
# Copy this file to .env and update the values for your environment
# =============================================================================

# -----------------------------------------------------------------------------
# Application Settings
# -----------------------------------------------------------------------------
APP_NAME=HRMS
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.yourdomain.com
APP_KEY=base64:GENERATE_WITH_php_artisan_key:generate
APP_FRONTEND_URL=https://yourdomain.com

# -----------------------------------------------------------------------------
# Database (SQL Server)
# -----------------------------------------------------------------------------
DB_CONNECTION=sqlsrv
DB_HOST=mssql
DB_PORT=1433
DB_DATABASE=hrms
DB_USERNAME=sa
# Password must be strong: 8+ chars, uppercase, lowercase, number/symbol
DB_PASSWORD=YourStrong!Password123

# -----------------------------------------------------------------------------
# Redis
# -----------------------------------------------------------------------------
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=null

# -----------------------------------------------------------------------------
# Queue
# -----------------------------------------------------------------------------
QUEUE_CONNECTION=redis

# -----------------------------------------------------------------------------
# WebSocket (Laravel Reverb)
# -----------------------------------------------------------------------------
REVERB_APP_ID=hrms-app-id
REVERB_APP_KEY=hrms-app-key-change-me
REVERB_APP_SECRET=hrms-app-secret-change-me

# Frontend WebSocket connection (public-facing)
VITE_REVERB_HOST=yourdomain.com
VITE_REVERB_PORT=6001
VITE_REVERB_SCHEME=wss

# -----------------------------------------------------------------------------
# Frontend Build Arguments
# -----------------------------------------------------------------------------
VITE_API_BASE_URL=https://api.yourdomain.com/api/v1
VITE_PUBLIC_URL=https://api.yourdomain.com
VITE_BROADCASTING_AUTH_ENDPOINT=https://api.yourdomain.com/broadcasting/auth

# -----------------------------------------------------------------------------
# Mail
# -----------------------------------------------------------------------------
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"

# -----------------------------------------------------------------------------
# Logging
# -----------------------------------------------------------------------------
LOG_CHANNEL=stack
LOG_LEVEL=warning
```

---

## Deployment Steps

### Prerequisites Checklist

```bash
# Verify Docker installation
docker --version
docker compose version

# Verify git
git --version

# Check available memory (SQL Server needs 2GB+)
free -h
```

### Step 1: Clone Repository

```bash
# Clone the deployment repository
git clone https://github.com/YOUR_ORG/hrms-deployment.git
cd hrms-deployment

# Initialize submodules (backend and frontend repos)
git submodule update --init --recursive
```

### Step 2: Configure Environment

```bash
# Copy environment template
cp .env.example .env

# Generate a secure Laravel app key
# (You can run this after containers are up, or use an online generator)
# php -r "echo 'base64:'.base64_encode(random_bytes(32));"

# Edit .env with your production values
nano .env
```

**Critical settings to update:**
- `APP_KEY` - Generate with `php artisan key:generate` or online
- `DB_PASSWORD` - Strong password (8+ chars, mixed case, numbers, symbols)
- `REVERB_APP_KEY` / `REVERB_APP_SECRET` - Generate unique values
- `VITE_*` - Update with your actual domain
- `MAIL_*` - Configure for your mail provider

### Step 3: SSL Certificates

```bash
# Option A: Use Let's Encrypt (recommended for production)
# See SSL/HTTPS Setup section below

# Option B: Self-signed for testing
mkdir -p nginx/ssl
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout nginx/ssl/privkey.pem \
  -out nginx/ssl/fullchain.pem \
  -subj "/CN=localhost"
```

### Step 4: Build and Start

```bash
# Build all images
docker compose build

# Start all services
docker compose up -d

# View logs
docker compose logs -f

# Check service status
docker compose ps
```

### Step 5: Initialize Database

```bash
# Wait for MSSQL to be ready (check logs)
docker compose logs mssql

# Run migrations (if not auto-run by entrypoint)
docker compose exec backend php artisan migrate --seed

# Generate app key (if not already set)
docker compose exec backend php artisan key:generate --force
```

### Step 6: Verify Deployment

```bash
# Check all services are running
docker compose ps

# Test frontend
curl -I https://yourdomain.com

# Test API
curl -I https://api.yourdomain.com/api/v1/health

# Check database connection
docker compose exec backend php artisan db:show

# Test queue
docker compose exec backend php artisan queue:work --once

# View application logs
docker compose exec backend tail -f storage/logs/laravel.log
```

---

## Development Setup

### Quick Start for Local Development

```bash
# Clone and setup
git clone https://github.com/YOUR_ORG/hrms-deployment.git
cd hrms-deployment
git submodule update --init --recursive

# Create development environment
cp .env.example .env
# Edit .env - set APP_ENV=local, APP_DEBUG=true

# Use development nginx config
cp nginx/nginx.dev.conf nginx/nginx.conf

# Start services
docker compose up -d

# Access the application
# Frontend: http://localhost
# API: http://localhost/api/v1
# Database: localhost:1433
```

### Useful Development Commands

```bash
# Rebuild a specific service
docker compose build backend
docker compose up -d backend

# View real-time logs
docker compose logs -f backend queue

# Run artisan commands
docker compose exec backend php artisan migrate
docker compose exec backend php artisan tinker
docker compose exec backend php artisan queue:work --once

# Run tests
docker compose exec backend php artisan test

# Clear caches
docker compose exec backend php artisan cache:clear
docker compose exec backend php artisan config:clear

# Access shell
docker compose exec backend bash
docker compose exec mssql /opt/mssql-tools18/bin/sqlcmd -S localhost -U sa -P "$DB_PASSWORD" -C

# Stop all services
docker compose down

# Stop and remove volumes (WARNING: deletes data)
docker compose down -v
```

---

## SSL/HTTPS Setup

### Option 1: Let's Encrypt with Certbot

```bash
# Install certbot (on host, not in Docker)
sudo apt-get update
sudo apt-get install certbot

# Stop nginx temporarily
docker compose stop nginx

# Obtain certificate
sudo certbot certonly --standalone -d yourdomain.com -d api.yourdomain.com

# Copy certificates to nginx/ssl
sudo cp /etc/letsencrypt/live/yourdomain.com/fullchain.pem nginx/ssl/
sudo cp /etc/letsencrypt/live/yourdomain.com/privkey.pem nginx/ssl/
sudo chmod 644 nginx/ssl/*.pem

# Restart nginx
docker compose up -d nginx
```

### Auto-Renewal Cron Job

```bash
# Add to crontab (sudo crontab -e)
0 0 1 * * certbot renew --quiet && docker compose -f /path/to/hrms-deployment/docker-compose.yml restart nginx
```

### Option 2: Cloudflare (Recommended for Simplicity)

1. Add your domain to Cloudflare
2. Enable "Full (strict)" SSL mode
3. Use Cloudflare's Origin Certificate in `nginx/ssl/`
4. Cloudflare handles SSL termination and DDoS protection

---

## Backup and Restore

### Database Backup

```bash
# Create backup directory
mkdir -p backups

# Backup database
docker compose exec mssql /opt/mssql-tools18/bin/sqlcmd \
  -S localhost -U sa -P "$DB_PASSWORD" -C \
  -Q "BACKUP DATABASE hrms TO DISK = '/var/opt/mssql/backup/hrms_$(date +%Y%m%d_%H%M%S).bak' WITH FORMAT, COMPRESSION;"

# Copy backup from container
docker cp hrms-mssql:/var/opt/mssql/backup/ ./backups/
```

### Database Restore

```bash
# Copy backup to container
docker cp ./backups/hrms_backup.bak hrms-mssql:/var/opt/mssql/backup/

# Restore database
docker compose exec mssql /opt/mssql-tools18/bin/sqlcmd \
  -S localhost -U sa -P "$DB_PASSWORD" -C \
  -Q "RESTORE DATABASE hrms FROM DISK = '/var/opt/mssql/backup/hrms_backup.bak' WITH REPLACE;"
```

### Full Backup Script

Create `scripts/backup.sh`:

```bash
#!/bin/bash
set -e

BACKUP_DIR="/path/to/backups"
DATE=$(date +%Y%m%d_%H%M%S)
RETENTION_DAYS=30

echo "Starting HRMS backup..."

# Database backup
docker compose exec -T mssql /opt/mssql-tools18/bin/sqlcmd \
  -S localhost -U sa -P "$DB_PASSWORD" -C \
  -Q "BACKUP DATABASE hrms TO DISK = '/var/opt/mssql/backup/hrms_${DATE}.bak' WITH FORMAT, COMPRESSION;"

docker cp hrms-mssql:/var/opt/mssql/backup/hrms_${DATE}.bak ${BACKUP_DIR}/

# Storage backup
tar -czf ${BACKUP_DIR}/storage_${DATE}.tar.gz \
  -C /var/lib/docker/volumes/hrms_backend-storage/_data .

# Cleanup old backups
find ${BACKUP_DIR} -name "*.bak" -mtime +${RETENTION_DAYS} -delete
find ${BACKUP_DIR} -name "*.tar.gz" -mtime +${RETENTION_DAYS} -delete

echo "Backup completed: ${DATE}"
```

---

## Maintenance and Updates

### Update Application Code

```bash
# Pull latest code
cd hrms-deployment
git pull
git submodule update --remote

# Rebuild images
docker compose build backend frontend

# Deploy with zero downtime
docker compose up -d --no-deps backend
docker compose up -d --no-deps frontend
docker compose up -d --no-deps queue
docker compose up -d --no-deps reverb

# Run any new migrations
docker compose exec backend php artisan migrate --force
```

### Update Docker Images

```bash
# Pull latest base images
docker compose pull

# Rebuild and restart
docker compose build --no-cache
docker compose up -d
```

### Clear All Caches

```bash
docker compose exec backend php artisan optimize:clear
docker compose exec backend php artisan cache:clear
docker compose exec backend php artisan config:clear
docker compose exec backend php artisan route:clear
docker compose exec backend php artisan view:clear

# Rebuild caches
docker compose exec backend php artisan config:cache
docker compose exec backend php artisan route:cache
docker compose exec backend php artisan view:cache
```

### View Logs

```bash
# All services
docker compose logs -f

# Specific service
docker compose logs -f backend
docker compose logs -f queue
docker compose logs -f mssql

# Laravel logs
docker compose exec backend tail -f storage/logs/laravel.log

# Nginx access logs
docker compose logs nginx | grep "GET\|POST"
```

---

## Troubleshooting

### Common Issues

#### 1. Database Connection Failed

```bash
# Check MSSQL is running
docker compose ps mssql
docker compose logs mssql

# Test connection manually
docker compose exec mssql /opt/mssql-tools18/bin/sqlcmd \
  -S localhost -U sa -P "$DB_PASSWORD" -C \
  -Q "SELECT @@VERSION;"

# Common causes:
# - Password doesn't meet complexity requirements
# - Not enough RAM (needs 2GB)
# - Container still starting (wait 30-60 seconds)
```

#### 2. Frontend Build Fails

```bash
# Check build logs
docker compose build frontend --no-cache

# Common causes:
# - Node memory issue: Increase NODE_OPTIONS in Dockerfile
# - Missing environment variables: Check VITE_* args are set
```

#### 3. WebSocket Not Connecting

```bash
# Check Reverb is running
docker compose ps reverb
docker compose logs reverb

# Verify WebSocket port is accessible
curl -I http://localhost:6001

# Check CORS configuration
# Ensure APP_FRONTEND_URL is set correctly
```

#### 4. Queue Jobs Not Processing

```bash
# Check queue worker status
docker compose ps queue
docker compose logs queue

# Process one job manually
docker compose exec backend php artisan queue:work --once --verbose

# Check Redis connection
docker compose exec redis redis-cli ping
```

#### 5. Permission Errors

```bash
# Fix storage permissions
docker compose exec backend chown -R www-data:www-data storage bootstrap/cache
docker compose exec backend chmod -R 775 storage bootstrap/cache
```

#### 6. Out of Memory

```bash
# Check memory usage
docker stats

# Increase MSSQL memory limit if needed (edit docker-compose.yml)
# Or reduce max memory in SQL Server:
docker compose exec mssql /opt/mssql-tools18/bin/sqlcmd \
  -S localhost -U sa -P "$DB_PASSWORD" -C \
  -Q "EXEC sys.sp_configure 'max server memory', 1024; RECONFIGURE;"
```

### Debug Mode

```bash
# Enable debug mode temporarily
docker compose exec backend php artisan down
docker compose exec backend sed -i 's/APP_DEBUG=false/APP_DEBUG=true/' .env
docker compose exec backend php artisan config:clear
docker compose exec backend php artisan up

# Check error details, then disable
docker compose exec backend sed -i 's/APP_DEBUG=true/APP_DEBUG=false/' .env
docker compose exec backend php artisan config:cache
```

---

## Scaling

### Horizontal Scaling

```yaml
# In docker-compose.yml, increase replicas:
services:
  backend:
    deploy:
      replicas: 3

  queue:
    deploy:
      replicas: 4
```

### Load Balancer Configuration

For multiple servers, use:
- **Nginx Plus** or **HAProxy** as external load balancer
- **Docker Swarm** or **Kubernetes** for orchestration
- **Managed services**: AWS ELB, Google Cloud Load Balancing, etc.

### Database Scaling

For high availability:
1. **SQL Server Always On** availability groups
2. **Read replicas** for query distribution
3. **Azure SQL Database** for managed scaling

---

## Quick Reference

### Essential Commands

| Command | Description |
|---------|-------------|
| `docker compose up -d` | Start all services |
| `docker compose down` | Stop all services |
| `docker compose ps` | List running services |
| `docker compose logs -f` | Follow all logs |
| `docker compose exec backend bash` | Shell into backend |
| `docker compose build` | Rebuild all images |
| `docker compose pull` | Pull latest images |

### Service URLs

| Service | Development | Production |
|---------|-------------|------------|
| Frontend | http://localhost | https://yourdomain.com |
| API | http://localhost/api/v1 | https://api.yourdomain.com/api/v1 |
| WebSocket | ws://localhost:6001 | wss://yourdomain.com:6001 |
| Database | localhost:1433 | Internal only |
| Redis | localhost:6379 | Internal only |

### Health Check Endpoints

| Service | Endpoint | Expected |
|---------|----------|----------|
| Frontend | `/health` | 200 OK |
| Backend | `/api/v1/health` | 200 OK (implement in Laravel) |
| Nginx | `/health` | 200 OK |

---

## Support

For issues or questions:
1. Check the [Troubleshooting](#troubleshooting) section
2. Review Docker logs: `docker compose logs [service]`
3. Check Laravel logs: `storage/logs/laravel.log`
4. Open an issue on GitHub

---

*Last updated: February 2026*
