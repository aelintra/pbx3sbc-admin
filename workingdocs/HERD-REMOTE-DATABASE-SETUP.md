# Herd + Remote Database Setup Guide

**Date:** January 2026  
**Purpose:** Configure Laravel Herd development environment to use remote MySQL database

## Overview

This guide explains how to configure your local Laravel Herd environment (`http://pbx3sbc-admin.test/`) to connect to a remote MySQL database server for development and testing.

## Prerequisites

- ✅ Laravel Herd installed and running
- ✅ Project linked to Herd: `herd link pbx3sbc-admin`
- ✅ Remote MySQL server accessible (e.g., `3.93.26.82`)
- ✅ MySQL user credentials with appropriate permissions
- ✅ Network access to MySQL port (3306) from your local machine

## Step 1: Update .env File

Edit your `.env` file in the project root and update the database configuration:

```env
# Database Connection
DB_CONNECTION=mysql
DB_HOST=3.93.26.82
DB_PORT=3306
DB_DATABASE=opensips
DB_USERNAME=opensips
DB_PASSWORD=your_password_here
```

**Important Notes:**
- Replace `your_password_here` with the actual MySQL password
- Use the actual IP address or hostname of your remote MySQL server
- Default port is `3306` (change if your MySQL uses a different port)

## Step 2: Verify Remote MySQL Access

### 2.1 Test Connection from Command Line

Test if you can connect to the remote MySQL server:

```bash
mysql -h 3.93.26.82 -u opensips -p opensips
```

If this fails, you may need to configure MySQL to allow remote connections (see Step 3).

### 2.2 Test Connection from Laravel

Test the Laravel database connection:

```bash
php artisan db:show
```

This should display your database connection details and confirm connectivity.

## Step 3: Configure MySQL for Remote Access (If Needed)

If you cannot connect, you may need to configure MySQL on the remote server to allow connections from your IP address.

### 3.1 On the Remote MySQL Server (3.93.26.82)

**Option A: Allow from Any IP (Development Only - Less Secure)**

```sql
-- Connect to MySQL as root
mysql -u root -p

-- Grant access from any IP
GRANT ALL PRIVILEGES ON opensips.* TO 'opensips'@'%' IDENTIFIED BY 'your_password';
FLUSH PRIVILEGES;
EXIT;
```

**Option B: Allow from Specific IP (More Secure)**

First, find your local IP address:
```bash
# On Mac
ifconfig | grep "inet " | grep -v 127.0.0.1

# Or use a service
curl ifconfig.me
```

Then on the remote MySQL server:
```sql
-- Replace YOUR_LOCAL_IP with your actual IP (e.g., 192.168.1.100)
GRANT ALL PRIVILEGES ON opensips.* TO 'opensips'@'YOUR_LOCAL_IP' IDENTIFIED BY 'your_password';
FLUSH PRIVILEGES;
EXIT;
```

**Option C: Allow from Subnet (For VPN/Network Access)**

```sql
-- Replace with your subnet (e.g., 192.168.1.%)
GRANT ALL PRIVILEGES ON opensips.* TO 'opensips'@'192.168.1.%' IDENTIFIED BY 'your_password';
FLUSH PRIVILEGES;
EXIT;
```

### 3.2 Configure MySQL to Listen on Network Interface

On the remote server, ensure MySQL is configured to accept remote connections:

**Edit MySQL configuration:**
```bash
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf
```

**Find and update:**
```ini
# Change from:
bind-address = 127.0.0.1

# To:
bind-address = 0.0.0.0
```

**Restart MySQL:**
```bash
sudo systemctl restart mysql
```

### 3.3 Configure Firewall (If Applicable)

If the remote server has a firewall, allow MySQL port:

**UFW (Ubuntu):**
```bash
sudo ufw allow 3306/tcp
```

**firewalld (CentOS/RHEL):**
```bash
sudo firewall-cmd --add-port=3306/tcp --permanent
sudo firewall-cmd --reload
```

## Step 4: Verify Connection

### 4.1 Test Database Connection

```bash
php artisan db:show
```

Expected output should show:
- Connection: `mysql`
- Host: `3.93.26.82`
- Database: `opensips`
- Username: `opensips`

### 4.2 Test with a Query

```bash
php artisan tinker
```

Then in tinker:
```php
DB::connection()->getPdo();
// Should return PDO object without errors

DB::table('domain')->count();
// Should return count of domains (if table exists)
```

### 4.3 Access Admin Panel

1. Open browser: `http://pbx3sbc-admin.test/admin`
2. Log in with your admin credentials
3. Navigate to resources (CDR, Call Routes, etc.)
4. Verify data loads from remote database

## Step 5: Run Migrations (If Needed)

If you need to create application tables (users, etc.) on the remote database:

```bash
php artisan migrate
```

**Note:** Only run migrations for application tables. OpenSIPS tables (domain, dispatcher, acc, dialog, etc.) should already exist on the remote database.

## Troubleshooting

### Issue: "SQLSTATE[HY000] [2002] Connection refused"

**Possible Causes:**
1. MySQL not listening on network interface (check `bind-address`)
2. Firewall blocking port 3306
3. MySQL user doesn't have permission from your IP
4. Wrong host/port in `.env`

**Solutions:**
- Verify MySQL is listening: `sudo netstat -tlnp | grep 3306`
- Check firewall rules
- Verify user permissions: `SELECT user, host FROM mysql.user WHERE user='opensips';`
- Double-check `.env` file values

### Issue: "Access denied for user 'opensips'@'YOUR_IP'"

**Solution:**
- Grant permissions from your IP (see Step 3.1)
- Verify password is correct in `.env`
- Check MySQL user exists: `SELECT user, host FROM mysql.user;`

### Issue: "Unknown database 'opensips'"

**Solution:**
- Verify database exists on remote server: `SHOW DATABASES;`
- Check database name in `.env` matches exactly
- Create database if needed (but usually should already exist from OpenSIPS setup)

### Issue: Connection Works but Data Doesn't Load

**Possible Causes:**
1. Tables don't exist on remote database
2. Different database name
3. Permissions issue (read-only access)

**Solutions:**
- Verify tables exist: `SHOW TABLES;`
- Check user has SELECT permissions
- Verify you're connecting to the correct database

## Security Considerations

### Development Environment
- ✅ Using remote database for development is acceptable
- ⚠️ Use strong passwords
- ⚠️ Consider using VPN for additional security
- ⚠️ Limit MySQL user permissions if possible (read/write only to `opensips` database)

### Production Environment
- ❌ **Never** use remote database connection in production
- ✅ Admin panel should be on same server as MySQL (or use private network)
- ✅ Use SSL/TLS for database connections in production

## Quick Reference

### Update .env
```env
DB_CONNECTION=mysql
DB_HOST=3.93.26.82
DB_PORT=3306
DB_DATABASE=opensips
DB_USERNAME=opensips
DB_PASSWORD=your_password
```

### Test Connection
```bash
php artisan db:show
```

### Clear Config Cache (After .env Changes)
```bash
php artisan config:clear
php artisan cache:clear
```

### Check MySQL User Permissions (On Remote Server)
```sql
SELECT user, host FROM mysql.user WHERE user='opensips';
SHOW GRANTS FOR 'opensips'@'%';
```

## Related Documentation

- `PROJECT-CONTEXT.md` - Project overview and database connection details
- `DEPLOYMENT.md` - Production deployment guide
- `DEVELOPMENT-STACK-RECOMMENDATIONS.md` - Development environment recommendations
