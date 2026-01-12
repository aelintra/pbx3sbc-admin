# Docker MySQL Setup Guide

This guide explains how to set up a MySQL Docker container that can be shared between the admin panel and OpenSIPS instances.

## Quick Start

**Prerequisites:** Make sure Docker Desktop is running on your system.

```bash
# Start MySQL container and initialize database
./docker-setup.sh

# Or test the setup
./test-docker-mysql.sh
```

## Manual Setup

### 1. Start MySQL Container

```bash
docker compose up -d mysql
```

### 2. Initialize Database

Wait for MySQL to be ready (about 10-15 seconds), then run:

```bash
# Create OpenSIPS tables
docker compose exec -T mysql mysql -u opensips -popensips opensips < scripts/create-opensips-tables.sql
```

## Configuration

### Environment Variables

The `docker-compose.yml` file uses environment variables. You can either:

**Option 1: Set environment variables in your shell:**
```bash
export MYSQL_ROOT_PASSWORD=rootpassword
export MYSQL_DATABASE=opensips
export MYSQL_USER=opensips
export MYSQL_PASSWORD=opensips
export MYSQL_PORT=3307
```

**Option 2: Create a `.env` file in the project root:**
```env
MYSQL_ROOT_PASSWORD=rootpassword
MYSQL_DATABASE=opensips
MYSQL_USER=opensips
MYSQL_PASSWORD=opensips
MYSQL_PORT=3307  # Default: 3307 (to avoid conflict with Herd/local MySQL on 3306)
```

**Option 3: Edit docker-compose.yml directly** (defaults are shown in the file)

### Default Values

- **Container Name:** `pbx3sbc-mysql`
- **Database:** `opensips`
- **User:** `opensips`
- **Password:** `opensips`
- **Port:** `3307` (default, to avoid conflict with Herd/local MySQL on 3306)
- **Root Password:** `rootpassword`

## Connection Details

### From Host Machine (Localhost)

- **Host:** `127.0.0.1` or `localhost`
- **Port:** `3307` (default, changed from 3306 to avoid conflict with Herd/local MySQL)
- **Database:** `opensips`
- **Username:** `opensips`
- **Password:** `opensips`

**Note:** The default port is 3307 to avoid conflicts with Laravel Herd's MySQL (which uses 3306). You can change this by setting `MYSQL_PORT=3306` in your environment if you want to use the standard port (after stopping Herd's MySQL).

### From Other Docker Containers

If you run other services (like OpenSIPS) in Docker containers on the same network:

- **Host:** `mysql` (container name)
- **Port:** `3307` (default, to avoid conflict with Herd/local MySQL on 3306)
- **Database:** `opensips`
- **Username:** `opensips`
- **Password:** `opensips`

## LAN Access (Remote Connections)

### Current Behavior

**By default, Docker binds MySQL port to `0.0.0.0:3306`, making it accessible from your LAN.** However, MySQL user permissions must be configured to allow remote connections.

The MySQL user created by Docker (`opensips`) is created with host `%` by default, which allows connections from any host. However, if you need to restrict or change this, you can manage it using the helper script.

### Enable LAN Access

**Option 1: Using the helper script (Recommended)**

```bash
# Enable access from any host
./docker-mysql-access.sh enable

# Enable access from specific IP
./docker-mysql-access.sh enable 192.168.1.100

# Enable access from subnet
./docker-mysql-access.sh enable 192.168.1.%

# Check current status
./docker-mysql-access.sh status
```

**Option 2: Manual MySQL commands**

```bash
# Connect to MySQL
docker compose exec mysql mysql -uroot -prootpassword

# Grant access from any IP (less secure, for development only)
GRANT ALL PRIVILEGES ON opensips.* TO 'opensips'@'%' IDENTIFIED BY 'opensips';
FLUSH PRIVILEGES;
EXIT;
```

Or grant access from a specific IP/subnet:
```sql
-- Allow from specific IP
GRANT ALL PRIVILEGES ON opensips.* TO 'opensips'@'192.168.1.100' IDENTIFIED BY 'opensips';

-- Allow from subnet
GRANT ALL PRIVILEGES ON opensips.* TO 'opensips'@'192.168.1.%' IDENTIFIED BY 'opensips';

FLUSH PRIVILEGES;
```

2. **Configure firewall** (if needed):

```bash
# macOS (allow incoming connections on port 3306)
# Note: macOS firewall is usually permissive by default

# Linux (UFW)
sudo ufw allow 3306/tcp

# Linux (firewalld)
sudo firewall-cmd --add-port=3306/tcp --permanent
sudo firewall-cmd --reload
```

3. **Connect from remote machine:**

Use the Docker host's IP address:
```bash
mysql -h <docker-host-ip> -u opensips -popensips opensips
```

### Restrict to Localhost Only

**Option 1: Using the helper script**

```bash
# Disable remote access (revokes opensips@%)
./docker-mysql-access.sh disable
```

**Option 2: Manual MySQL commands**

```bash
docker compose exec mysql mysql -uroot -prootpassword

REVOKE ALL PRIVILEGES ON opensips.* FROM 'opensips'@'%';
DROP USER IF EXISTS 'opensips'@'%';
FLUSH PRIVILEGES;
EXIT;
```

**Option 3: Restrict port binding**

If you want to also restrict the Docker port binding to localhost only, modify the port mapping in `docker-compose.yml`:

```yaml
ports:
  - "127.0.0.1:${MYSQL_PORT:-3306}:3306"  # Only bind to localhost
```

Then restart the container:
```bash
docker compose down && docker compose up -d mysql
```

This prevents external connections even if MySQL user permissions allow them.

## Admin Panel Configuration

Update your `.env` file:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=opensips
DB_USERNAME=opensips
DB_PASSWORD=opensips
```

## OpenSIPS Configuration

If OpenSIPS is running on the host (not in Docker), configure it to connect to `127.0.0.1:3307` (or the port you've configured).

If OpenSIPS is also in Docker, connect to the `mysql` hostname on the `pbx3sbc-network` network.

## Useful Commands

```bash
# Start MySQL container
docker compose up -d mysql

# Stop MySQL container
docker compose down

# View logs
docker compose logs -f mysql

# Connect to MySQL
docker compose exec mysql mysql -u opensips -popensips opensips

# Run SQL file
docker compose exec -T mysql mysql -u opensips -popensips opensips < script.sql

# Check container status
docker compose ps

# Remove container and volumes (WARNING: deletes all data)
docker compose down -v

# Manage LAN access
./docker-mysql-access.sh status   # Show current access
./docker-mysql-access.sh enable   # Enable LAN access
./docker-mysql-access.sh disable  # Disable LAN access
./docker-mysql-access.sh help     # Show help
```

## Data Persistence

Data is persisted in a Docker volume named `mysql_data`. This means:

- Data survives container restarts
- Data survives `docker compose down`
- Data is **deleted** by `docker compose down -v`

## Network Configuration

The MySQL container is on a Docker bridge network named `pbx3sbc-network`. To connect other containers:

1. Add them to the same network in their `docker-compose.yml`:
   ```yaml
   networks:
     - pbx3sbc-network
   
   networks:
     pbx3sbc-network:
       external: true
   ```

2. Or use the same `docker-compose.yml` file with multiple services.

## Troubleshooting

### MySQL won't start

Check logs:
```bash
docker compose logs mysql
```

### Can't connect from host

1. Verify container is running: `docker compose ps`
2. Check port is not already in use: `lsof -i :3306` or `netstat -an | grep 3306`
3. Try different port by setting `MYSQL_PORT=3307` in environment

### Tables not created

1. Check if script was mounted: `docker compose exec mysql ls -la /docker-entrypoint-initdb.d/`
2. Manually run: `docker compose exec -T mysql mysql -u opensips -popensips opensips < scripts/create-opensips-tables.sql`

### Connection refused from other containers

1. Ensure containers are on the same network
2. Use hostname `mysql` (not `127.0.0.1`) from within Docker network
3. Verify network: `docker network inspect pbx3sbc-network`

### Can't connect from LAN/remote machine

1. **Check MySQL user permissions:**
   ```bash
   docker compose exec mysql mysql -uroot -prootpassword -e "SELECT user, host FROM mysql.user WHERE user='opensips';"
   ```
   If you only see `opensips@localhost`, you need to grant remote access (see "LAN Access" section above).

2. **Verify port binding:**
   ```bash
   # Should show 0.0.0.0:3306 (accessible from LAN) or 127.0.0.1:3306 (localhost only)
   docker compose ps
   netstat -an | grep 3306
   ```

3. **Check firewall:**
   - macOS: System Preferences → Security & Privacy → Firewall
   - Linux: `sudo ufw status` or `sudo firewall-cmd --list-all`

4. **Test connectivity:**
   ```bash
   # From remote machine
   telnet <docker-host-ip> 3306
   # Or
   nc -zv <docker-host-ip> 3306
   ```
