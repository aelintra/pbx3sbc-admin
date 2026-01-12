# Remote Deployment Guide

**Date:** January 12, 2026  
**Purpose:** Guide for deploying the admin panel on a separate server/instance from the OpenSIPS server

## Overview

This guide outlines the requirements and configuration steps needed to deploy the pbx3sbc-admin panel on a different server than the OpenSIPS server. The admin panel can run independently and connect to the OpenSIPS MySQL database and Management Interface (MI) over the network.

## Architecture

```
┌─────────────────────────────────────┐
│   OpenSIPS Server                   │
│                                     │
│   ┌─────────────────────────────┐  │
│   │  OpenSIPS                   │  │
│   │  (SIP routing)              │  │
│   └─────────────────────────────┘  │
│                                     │
│   ┌─────────────────────────────┐  │
│   │  MySQL Database             │  │
│   │  - domain table             │  │
│   │  - dispatcher table         │  │
│   │  - endpoint_locations       │  │
│   └───────────┬─────────────────┘  │
│               │                     │
│   ┌───────────▼─────────────────┐  │
│   │  OpenSIPS MI HTTP           │  │
│   │  (Port 8888)                │  │
│   └───────────┬─────────────────┘  │
└───────────────┼─────────────────────┘
                │
                │ Network Connection
                │
┌───────────────┼─────────────────────┐
│   Admin Panel Server                │
│                                     │
│   ┌─────────────────────────────┐  │
│   │  Laravel + Filament         │  │
│   │  Admin Panel                │  │
│   └─────────────────────────────┘  │
│                                     │
│   Connections:                      │
│   - MySQL (3306/tcp) → OpenSIPS    │
│   - HTTP MI (8888/tcp) → OpenSIPS  │
│   - HTTP/HTTPS (80/443) → Users    │
└─────────────────────────────────────┘
```

## Requirements

### 1. Database Connectivity (Required)

The admin panel requires network access to the MySQL database on the OpenSIPS server.

#### On OpenSIPS Server (MySQL Server)

**1.1 Enable Remote MySQL Access**

Edit MySQL configuration file (location varies by distribution):
```bash
# Ubuntu/Debian
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf

# CentOS/RHEL
sudo nano /etc/my.cnf
```

Change or comment out the `bind-address` line:
```ini
# Change from:
bind-address = 127.0.0.1

# To:
bind-address = 0.0.0.0
# Or comment it out:
# bind-address = 127.0.0.1
```

Restart MySQL:
```bash
sudo systemctl restart mysql
```

**1.2 Grant Remote Access to MySQL User**

Connect to MySQL and grant permissions:
```sql
# Option 1: Allow from any IP (less secure, use with caution)
GRANT ALL PRIVILEGES ON opensips.* TO 'opensips'@'%' IDENTIFIED BY 'your-password';
FLUSH PRIVILEGES;

# Option 2: Restrict to specific admin panel server IP (recommended)
GRANT ALL PRIVILEGES ON opensips.* TO 'opensips'@'admin-panel-server-ip' IDENTIFIED BY 'your-password';
FLUSH PRIVILEGES;

# Option 3: Use hostname (if DNS is configured)
GRANT ALL PRIVILEGES ON opensips.* TO 'opensips'@'admin-panel-hostname' IDENTIFIED BY 'your-password';
FLUSH PRIVILEGES;
```

**1.3 Configure Firewall**

Allow MySQL connections from admin panel server:
```bash
# Ubuntu/Debian (UFW)
sudo ufw allow from admin-panel-server-ip to any port 3306

# Or allow from specific network
sudo ufw allow from 10.0.0.0/8 to any port 3306

# Cloud providers: Configure Security Groups/Firewall Rules
# - AWS: Security Groups (inbound rule: 3306/tcp from admin panel server)
# - Azure: Network Security Groups
# - GCP: Firewall Rules
```

**1.4 Verify MySQL Remote Access**

Test from admin panel server:
```bash
mysql -h opensips-server-ip -u opensips -p opensips
```

#### On Admin Panel Server

**1.5 Update Laravel .env Configuration**

Edit `.env` file:
```env
DB_CONNECTION=mysql
DB_HOST=opensips-server-ip-or-hostname    # Change from 127.0.0.1
DB_PORT=3306
DB_DATABASE=opensips
DB_USERNAME=opensips
DB_PASSWORD=your-password

# Optional: MySQL SSL Configuration (recommended for production)
# DB_SSLMODE=require
# MYSQL_ATTR_SSL_CA=/path/to/ca-cert.pem
```

**1.6 Test Database Connection**

```bash
# Test connection
php artisan db:show

# Expected output should show:
# Host: opensips-server-ip-or-hostname
# Database: opensips
# Username: opensips

# Alternative test
php artisan tinker --execute="DB::connection()->getPdo(); echo 'Connection successful!';"
```

### 2. OpenSIPS MI Connectivity (Optional)

If using the OpenSIPS Management Interface (MI) service for reload actions and real-time control:

#### On OpenSIPS Server

**2.1 Verify OpenSIPS MI HTTP Interface**

Check OpenSIPS configuration to ensure MI HTTP interface is enabled:
```bash
# Check OpenSIPS config
grep -A 5 "mi_http_module" /etc/opensips/opensips.cfg

# Should show something like:
# modparam("mi_http", "http_root", "/mi")
# modparam("mi_http", "http_port", 8888)
```

**2.2 Configure Firewall for MI Access**

```bash
# Ubuntu/Debian (UFW)
sudo ufw allow from admin-panel-server-ip to any port 8888

# Cloud providers: Configure Security Groups/Firewall Rules
# - AWS: Security Groups (inbound rule: 8888/tcp from admin panel server)
# - Azure: Network Security Groups
# - GCP: Firewall Rules
```

**2.3 Test MI Interface Access**

From admin panel server, test HTTP access:
```bash
curl http://opensips-server-ip:8888/mi
```

#### On Admin Panel Server

**2.4 Update .env Configuration**

```env
OPENSIPS_MI_URL=http://opensips-server-ip-or-hostname:8888/mi
```

**Note:** If MI is not available or not used, the admin panel will work in database-only mode. The MI service is designed for graceful degradation.

### 3. Network/Firewall Configuration Summary

#### OpenSIPS Server - Inbound Rules

| Port | Protocol | Source | Purpose |
|------|----------|--------|---------|
| 3306 | TCP | Admin Panel Server IP | MySQL database access |
| 8888 | TCP | Admin Panel Server IP | OpenSIPS MI HTTP (optional) |

#### Admin Panel Server - Outbound Rules

| Port | Protocol | Destination | Purpose |
|------|----------|-------------|---------|
| 3306 | TCP | OpenSIPS Server IP | MySQL database access |
| 8888 | TCP | OpenSIPS Server IP | OpenSIPS MI HTTP (optional) |
| 80 | TCP | Internet/Users | HTTP web access |
| 443 | TCP | Internet/Users | HTTPS web access |

### 4. Security Considerations

#### Best Practices

1. **Use Strong Passwords**
   - Use strong, unique passwords for MySQL users
   - Consider using MySQL's password validation plugin

2. **Restrict Network Access**
   - Prefer specific IP addresses over `%` wildcard in MySQL GRANT statements
   - Use firewall rules to restrict access to admin panel server only
   - Use private networks/VPNs when possible

3. **Use SSL/TLS for MySQL (Recommended for Production)**
   ```env
   # In .env file
   DB_SSLMODE=require
   MYSQL_ATTR_SSL_CA=/path/to/ca-cert.pem
   ```

4. **Network Isolation**
   - Use private networks/VPNs for database traffic
   - Avoid exposing MySQL port to public internet
   - Use SSH tunnels for additional security if needed

5. **Cloud-Specific Security**
   - Use security groups/firewall rules in addition to OS-level firewalls
   - Consider using VPC/private networking for database traffic
   - Use load balancers with SSL termination for web traffic

#### Security Checklist

- [ ] MySQL user has strong password
- [ ] MySQL GRANT statement uses specific IP (not `%`)
- [ ] Firewall rules restrict MySQL access to admin panel server only
- [ ] OpenSIPS MI access restricted to admin panel server (if used)
- [ ] SSL/TLS configured for MySQL (production)
- [ ] Database traffic uses private network/VPN (preferred)
- [ ] Regular security updates applied to both servers

### 5. Code Configuration

**Good News:** The admin panel code already supports remote deployment with no code changes needed:

- ✅ Database configuration uses environment variables (`DB_HOST` can be any IP/hostname)
- ✅ OpenSIPS MI URL is configurable via `OPENSIPS_MI_URL`
- ✅ All connectivity is configured through `.env` file
- ✅ MI service is designed for graceful degradation (works without MI)

### 6. Deployment Checklist

#### Pre-Deployment (OpenSIPS Server)

- [ ] MySQL configured to accept remote connections (`bind-address = 0.0.0.0`)
- [ ] MySQL user granted remote access permissions
- [ ] Firewall configured to allow MySQL port 3306 from admin panel server
- [ ] Firewall configured to allow OpenSIPS MI port 8888 from admin panel server (if using)
- [ ] MySQL remote access tested from admin panel server
- [ ] OpenSIPS MI HTTP interface tested (if using)

#### Deployment (Admin Panel Server)

- [ ] Admin panel application installed (Laravel + Filament)
- [ ] `.env` file configured with remote database host
- [ ] `.env` file configured with remote OpenSIPS MI URL (if using)
- [ ] Database connection tested: `php artisan db:show`
- [ ] Laravel migrations run: `php artisan migrate`
- [ ] Admin user created: `php artisan make:filament-user`
- [ ] Web server configured (nginx/apache)
- [ ] SSL certificate installed (production)

#### Post-Deployment Verification

- [ ] Admin panel accessible via web browser
- [ ] Can log in with admin credentials
- [ ] Domain management (CRUD operations) works
- [ ] Dispatcher management (CRUD operations) works
- [ ] Data persists correctly in database
- [ ] (Optional) OpenSIPS MI reload actions work (if MI service implemented)

### 7. Troubleshooting

#### Database Connection Issues

**Error: "SQLSTATE[HY000] [2002] Connection refused"**
- Check MySQL `bind-address` configuration
- Verify firewall allows port 3306
- Check MySQL service is running: `sudo systemctl status mysql`

**Error: "Access denied for user"**
- Verify MySQL user has remote access permissions
- Check GRANT statements are correct
- Verify password is correct in `.env` file

**Error: "Unknown MySQL server host"**
- Verify hostname/IP is correct in `.env`
- Check DNS resolution if using hostname
- Test network connectivity: `ping opensips-server-ip`

#### OpenSIPS MI Connection Issues

**Error: "Connection timeout" or "Connection refused"**
- Verify OpenSIPS MI HTTP interface is enabled
- Check firewall allows port 8888
- Test from command line: `curl http://opensips-server-ip:8888/mi`

**Note:** MI connection failures are non-fatal - the admin panel works in database-only mode without MI.

### 8. Alternative Deployment Options

#### Option 1: SSH Tunnel (Additional Security)

For enhanced security, use SSH tunnel for MySQL connection:

```bash
# On admin panel server, create SSH tunnel
ssh -L 3306:localhost:3306 user@opensips-server-ip

# Then in .env, use:
DB_HOST=127.0.0.1  # Tunnel forwards to remote MySQL
```

#### Option 2: VPN Connection

Use VPN/private network for all communication between servers:
- Both servers connected to same VPN/private network
- Use private IP addresses in configuration
- No public internet exposure required

#### Option 3: Database-Only Mode (No MI)

The admin panel can function without OpenSIPS MI:
- All CRUD operations work via database
- Configuration changes require manual OpenSIPS reload
- MI service can be added later when needed

### 9. References

- **Two-Repository Strategy:** `pbx3sbc/workingdocs/TWO-REPO-STRATEGY.md`
- **Installation Log:** `workingdocs/INSTALLATION-LOG.md`
- **Session Summary:** `workingdocs/SESSION-SUMMARY.md`
- **Laravel Database Configuration:** https://laravel.com/docs/database

---

**Last Updated:** January 12, 2026  
**Status:** Documentation Complete
