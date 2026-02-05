# MSSQL Server Docker Setup on Digital Ocean (Laravel Forge)

> Last verified: February 2026
>
> References:
> - [SQL Server Docker Images](https://mcr.microsoft.com/product/mssql/server/about)
> - [ODBC Driver for SQL Server (Linux)](https://learn.microsoft.com/en-us/sql/connect/odbc/linux-mac/installing-the-microsoft-odbc-driver-for-sql-server)
> - [PHP Drivers for SQL Server (PECL)](https://pecl.php.net/package/sqlsrv)

Complete guide to replace MySQL with MSSQL Server on your Digital Ocean droplet managed by Laravel Forge.

---

## Prerequisites

- Digital Ocean droplet with **minimum 4 GB RAM** (2 GB for MSSQL + headroom for app)
- Laravel Forge managing the droplet
- SSH access to the droplet
- Ubuntu 22.04 (recommended - best driver support; Ubuntu 24.04 only supported in beta PHP drivers)

---

## Step 1: SSH into Your Droplet

From Forge dashboard, go to your server and use the SSH terminal, or connect manually:

```bash
ssh forge@your-droplet-ip
```

---

## Step 2: Install Docker

```bash
# Update packages
sudo apt-get update

# Install dependencies
sudo apt-get install -y apt-transport-https ca-certificates curl software-properties-common

# Add Docker's official GPG key
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /usr/share/keyrings/docker-archive-keyring.gpg

# Add Docker repository
echo "deb [arch=amd64 signed-by=/usr/share/keyrings/docker-archive-keyring.gpg] https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

# Install Docker
sudo apt-get update
sudo apt-get install -y docker-ce docker-ce-cli containerd.io

# Add forge user to docker group (so you don't need sudo every time)
sudo usermod -aG docker forge

# Start and enable Docker
sudo systemctl start docker
sudo systemctl enable docker

# Verify installation
docker --version
```

> Log out and back in for the group change to take effect:
> `exit` then `ssh forge@your-droplet-ip`

---

## Step 3: Create a Directory for MSSQL Data

This ensures your database data persists even if the container is recreated.

```bash
sudo mkdir -p /opt/mssql/data
sudo chown -R 10001:0 /opt/mssql/data
```

---

## Step 4: Run MSSQL Server Container

**Available Docker image tags (as of Feb 2026):**

| Tag | Version | Notes |
|-----|---------|-------|
| `2022-latest` | SQL Server 2022 (CU16+) | **Recommended** - fully supported for production |
| `2025-latest` | SQL Server 2025 | Newest GA release |
| `2019-latest` | SQL Server 2019 | Older, still supported |

Use SQL Server 2022 unless you have a specific reason to use 2025:

```bash
docker run -d \
  --name mssql \
  -e "ACCEPT_EULA=Y" \
  -e "MSSQL_SA_PASSWORD=root@007" \
  -e "MSSQL_PID=Express" \
  -p 1433:1433 \
  -v /opt/mssql/data:/var/opt/mssql \
  --restart=always \
  --memory=2g \
  mcr.microsoft.com/mssql/server:2022-latest
```

**Parameter explanation:**

| Parameter | Purpose |
|-----------|---------|
| `--name mssql` | Container name for easy reference |
| `-e "ACCEPT_EULA=Y"` | Required to accept Microsoft's license |
| `-e "MSSQL_SA_PASSWORD=..."` | SA (admin) password - must be strong (8+ chars, uppercase, lowercase, number/symbol) |
| `-e "MSSQL_PID=Express"` | Edition: `Express` (free, production-legal, 10 GB DB limit), `Developer` (free, dev/test only), `Standard`/`Enterprise` (licensed) |
| `-p 1433:1433` | Map SQL Server port to host |
| `-v /opt/mssql/data:/var/opt/mssql` | Persist data outside container |
| `--restart=always` | Auto-restart on crash or server reboot |
| `--memory=2g` | Limit container to 2 GB RAM |

**Express edition** is free and licensed for production use. Limits: 10 GB max database size, 1 GB max RAM usage, 4 CPU cores. For an HRMS this is more than enough. If you outgrow it, upgrade to `Standard` or `Enterprise` (licensed).

---

## Step 5: Verify MSSQL is Running

```bash
# Check container status
docker ps

# You should see something like:
# CONTAINER ID  IMAGE                                       STATUS        PORTS                    NAMES
# abc123def     mcr.microsoft.com/mssql/server:2022-latest  Up 2 minutes  0.0.0.0:1433->1433/tcp   mssql

# Check logs for errors
docker logs mssql

# Look for: "SQL Server is now ready for client connections"
```

---

## Step 6: Install PHP SQL Server Extensions

Laravel needs `pdo_sqlsrv` to connect to MSSQL. Your Forge server runs **PHP 8.3.25**.

**Driver compatibility (as of Feb 2026):**

| PHP Version | sqlsrv / pdo_sqlsrv | Status |
|-------------|---------------------|--------|
| PHP 8.2 | 5.12.0 (stable) | Recommended |
| PHP 8.3 | 5.12.0 (stable) | Recommended |
| PHP 8.4 | 5.13.0-beta1 only | Not stable yet - avoid for production |

**ODBC Driver:** `msodbcsql18` (latest: 18.6.1.1)

```bash
# Check your PHP version first
php -v

# Install prerequisites
sudo apt-get install -y unixodbc-dev

# Add Microsoft ODBC driver repository
curl https://packages.microsoft.com/keys/microsoft.asc | sudo tee /etc/apt/trusted.gpg.d/microsoft.asc
sudo add-apt-repository "$(wget -qO- https://packages.microsoft.com/config/ubuntu/22.04/prod.list)"
sudo apt-get update

# Install ODBC driver (latest: 18.6.1.1)
sudo ACCEPT_EULA=Y apt-get install -y msodbcsql18

# Install PHP extensions - stable 5.12.0 (supports PHP 8.2 and 8.3)
# Adjust PHP version number below to match yours
sudo pecl install sqlsrv-5.12.0 pdo_sqlsrv-5.12.0

# If you're on PHP 8.4 (beta driver, not recommended for production):
# sudo pecl install sqlsrv-5.13.0beta1 pdo_sqlsrv-5.13.0beta1

# Enable the extensions
# Create config files for your PHP version (adjust 8.2 to your version)
echo "extension=sqlsrv.so" | sudo tee /etc/php/8.3/mods-available/sqlsrv.ini
echo "extension=pdo_sqlsrv.so" | sudo tee /etc/php/8.3/mods-available/pdo_sqlsrv.ini

# Enable for CLI and FPM
sudo phpenmod -v 8.3 sqlsrv pdo_sqlsrv

# Restart PHP-FPM
sudo systemctl restart php8.3-fpm

# Verify extensions are loaded
php -m | grep sqlsrv
# Should output:
# pdo_sqlsrv
# sqlsrv
```

---

## Step 7: Create the HRMS Database

```bash
# Install sqlcmd tool
docker exec -it mssql /opt/mssql-tools18/bin/sqlcmd \
  -S localhost -U sa -P "YourStrong!Pass123" \
  -C \
  -Q "CREATE DATABASE hrms;"

# Verify database was created
docker exec -it mssql /opt/mssql-tools18/bin/sqlcmd \
  -S localhost -U sa -P "YourStrong!Pass123" \
  -C \
  -Q "SELECT name FROM sys.databases;"
```

> The `-C` flag trusts the self-signed certificate. This is fine for local connections.

---

## Step 8: Update Laravel .env

On your Forge server, edit the `.env` file in your project:

```bash
nano /home/forge/your-site-name/.env
```

Update the database section:

```env
DB_CONNECTION=sqlsrv
DB_HOST=127.0.0.1
DB_PORT=1433
DB_DATABASE=hrms
DB_USERNAME=sa
DB_PASSWORD=YourStrong!Pass123
```

---

## Step 9: Run Migrations

```bash
cd /home/forge/your-site-name
php artisan migrate
```

---

## Step 10: Disable MySQL (Optional)

Since you no longer need MySQL, you can stop it to free up RAM:

```bash
sudo systemctl stop mysql
sudo systemctl disable mysql
```

This frees ~200-400 MB of RAM for your app.

---

## Firewall Security

**IMPORTANT:** Do NOT expose port 1433 to the internet. MSSQL should only be accessible locally.

```bash
# Verify UFW is active
sudo ufw status

# If port 1433 is listed as open, remove it
sudo ufw deny 1433

# MSSQL is only accessible via 127.0.0.1 (localhost) which is all Laravel needs
```

---

## Common Operations

### Restart MSSQL
```bash
docker restart mssql
```

### Stop MSSQL
```bash
docker stop mssql
```

### View Logs
```bash
docker logs mssql
docker logs mssql --tail 50    # Last 50 lines
docker logs mssql -f           # Follow/stream logs
```

### Connect to MSSQL Shell
```bash
docker exec -it mssql /opt/mssql-tools18/bin/sqlcmd \
  -S localhost -U sa -P "YourStrong!Pass123" -C
```

Then run SQL commands:
```sql
USE hrms;
GO
SELECT name FROM sys.tables;
GO
```

Type `exit` to leave.

### Browse Database & Tables

All commands below use one-liner format so you don't need to enter the interactive shell.

**List all databases:**
```bash
docker exec -it mssql /opt/mssql-tools18/bin/sqlcmd \
  -S localhost -U sa -P "root@007" -C \
  -Q "SELECT name FROM sys.databases;"
```

**List all tables in the hrms database:**
```bash
docker exec -it mssql /opt/mssql-tools18/bin/sqlcmd \
  -S localhost -U sa -P "root@007" -C \
  -Q "USE hrms; SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME;"
```

**View table columns and data types:**
```bash
# Replace 'employees' with your table name
docker exec -it mssql /opt/mssql-tools18/bin/sqlcmd \
  -S localhost -U sa -P "root@007" -C \
  -Q "USE hrms; SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'employees' ORDER BY ORDINAL_POSITION;"
```

**Count rows in a table:**
```bash
docker exec -it mssql /opt/mssql-tools18/bin/sqlcmd \
  -S localhost -U sa -P "root@007" -C \
  -Q "USE hrms; SELECT COUNT(*) AS total_rows FROM employees;"
```

**Preview data (first 10 rows):**
```bash
docker exec -it mssql /opt/mssql-tools18/bin/sqlcmd \
  -S localhost -U sa -P "root@007" -C \
  -Q "USE hrms; SELECT TOP 10 * FROM employees;"
```

**Check table indexes:**
```bash
docker exec -it mssql /opt/mssql-tools18/bin/sqlcmd \
  -S localhost -U sa -P "root@007" -C \
  -Q "USE hrms; SELECT i.name AS index_name, c.name AS column_name, i.type_desc FROM sys.indexes i JOIN sys.index_columns ic ON i.object_id = ic.object_id AND i.index_id = ic.index_id JOIN sys.columns c ON ic.object_id = c.object_id AND ic.column_id = c.column_id WHERE i.object_id = OBJECT_ID('employees');"
```

**Check database size:**
```bash
docker exec -it mssql /opt/mssql-tools18/bin/sqlcmd \
  -S localhost -U sa -P "root@007" -C \
  -Q "USE hrms; EXEC sp_spaceused;"
```

**List all tables with row counts (overview of entire database):**
```bash
docker exec -it mssql /opt/mssql-tools18/bin/sqlcmd \
  -S localhost -U sa -P "root@007" -C \
  -Q "USE hrms; SELECT t.name AS table_name, p.rows AS row_count FROM sys.tables t JOIN sys.partitions p ON t.object_id = p.object_id AND p.index_id IN (0, 1) ORDER BY t.name;"
```

**Check Laravel migrations status:**
```bash
docker exec -it mssql /opt/mssql-tools18/bin/sqlcmd \
  -S localhost -U sa -P "root@007" -C \
  -Q "USE hrms; SELECT * FROM migrations ORDER BY id DESC;"
```

### Update MSSQL to Latest Version
```bash
docker pull mcr.microsoft.com/mssql/server:2022-latest
docker stop mssql
docker rm mssql

# Re-run the container (Step 4) - data persists in /opt/mssql/data
docker run -d \
  --name mssql \
  -e "ACCEPT_EULA=Y" \
  -e "MSSQL_SA_PASSWORD=YourStrong!Pass123" \
  -e "MSSQL_PID=Express" \
  -p 1433:1433 \
  -v /opt/mssql/data:/var/opt/mssql \
  --restart=always \
  --memory=2g \
  mcr.microsoft.com/mssql/server:2022-latest
```

### Backup Database
```bash
# Create backup inside container
docker exec mssql /opt/mssql-tools18/bin/sqlcmd \
  -S localhost -U sa -P "YourStrong!Pass123" -C \
  -Q "BACKUP DATABASE hrms TO DISK = '/var/opt/mssql/backup/hrms.bak' WITH FORMAT;"

# Copy backup to host
sudo mkdir -p /opt/mssql/backups
sudo cp /opt/mssql/data/backup/hrms.bak /opt/mssql/backups/hrms_$(date +%Y%m%d).bak
```

### Restore Database
```bash
docker exec mssql /opt/mssql-tools18/bin/sqlcmd \
  -S localhost -U sa -P "YourStrong!Pass123" -C \
  -Q "RESTORE DATABASE hrms FROM DISK = '/var/opt/mssql/backup/hrms.bak' WITH REPLACE;"
```

---

## Troubleshooting

### Container exits immediately
```bash
docker logs mssql
```
Common causes:
- Password too weak (needs uppercase, lowercase, number, 8+ chars)
- Not enough RAM (need at least 2 GB)

### "Connection refused" from Laravel
1. Check container is running: `docker ps`
2. Check port is listening: `sudo ss -tlnp | grep 1433`
3. Check PHP extensions: `php -m | grep sqlsrv`
4. Check `.env` has `DB_HOST=127.0.0.1` (not `localhost` - PHP resolves these differently)

### PHP extension installation fails
```bash
# Make sure you have the right dev package
sudo apt-get install -y php8.3-dev

# Then retry pecl install
sudo pecl install sqlsrv-5.12.0 pdo_sqlsrv-5.12.0
```

### Permission denied on /opt/mssql/data
```bash
# MSSQL runs as UID 10001 inside the container
sudo chown -R 10001:0 /opt/mssql/data
sudo chmod -R 775 /opt/mssql/data
```

---

## Recommended Droplet Size

| Component | RAM Usage |
|-----------|-----------|
| MSSQL Server | ~1.5-2 GB |
| PHP-FPM | ~200-400 MB |
| Nginx | ~50 MB |
| Queue Worker | ~100 MB |
| Laravel Reverb | ~100 MB |
| OS + overhead | ~300 MB |
| **Total** | **~2.5-3 GB** |

**Recommended: 4 GB RAM droplet ($24/month on Digital Ocean)**
