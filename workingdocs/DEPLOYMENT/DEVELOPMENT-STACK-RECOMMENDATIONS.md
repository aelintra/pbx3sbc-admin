# Development Stack Recommendations

**Date:** January 2026  
**Context:** Laravel + Filament admin panel development

## Current Situation

- **OpenSIPS:** Already installed on Ubuntu image
- **OpenSIPS Database:** MySQL (accessible)
- **Development Machine:** Mac (based on workspace path)
- **Question:** Develop on same Ubuntu machine or separate stack?

---

## Option 1: Develop on Same Ubuntu Machine (Recommended)

### Setup

Develop the Laravel/Filament application directly on the Ubuntu machine where OpenSIPS is running.

### Advantages

✅ **Direct Database Access**
- MySQL database is local
- No network latency for database queries
- Easy to test with real OpenSIPS data

✅ **Integrated Testing**
- Can test OpenSIPS MI integration directly
- Can test service management (systemctl) directly
- Can test S3/Minio if running locally
- Everything runs in one environment

✅ **Single Environment**
- No environment sync issues
- No network configuration needed
- Simpler deployment (same machine)

✅ **Real-World Testing**
- Test against actual OpenSIPS instance
- Test against actual database
- Closer to production environment

### Requirements

Install on Ubuntu:
- PHP 8.2+ (with extensions: mbstring, xml, curl, mysql, zip)
- Composer
- Node.js/npm (optional, for asset compilation - Filament uses precompiled Tailwind)
- Git

### Setup Steps

```bash
# On Ubuntu machine

# Install PHP 8.2
sudo apt update
sudo apt install php8.2 php8.2-cli php8.2-fpm php8.2-mysql php8.2-xml php8.2-mbstring php8.2-curl php8.2-zip

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install Git (if not already installed)
sudo apt install git

# Optional: Install Node.js (for future asset compilation if needed)
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

# Create Laravel project
cd /var/www  # or wherever you want
composer create-project laravel/laravel:^12.0 admin-panel
cd admin-panel

# Install Filament
composer require filament/filament:"^3.0"
php artisan filament:install --panels

# Configure database connection to OpenSIPS MySQL
# Edit .env file
```

### Development Workflow

```bash
# SSH into Ubuntu machine
ssh user@ubuntu-machine

# Navigate to project
cd /var/www/admin-panel

# Run development server
php artisan serve --host=0.0.0.0 --port=8000

# Access from Mac browser
# http://ubuntu-machine-ip:8000/admin
```

### File Editing Options

**Option A: SSH + Local Editor (VS Code Remote)**
- Use VS Code Remote-SSH extension
- Edit files on Ubuntu from Mac
- Best of both worlds

**Option B: SSH + Terminal Editor (vim/nano)**
- Edit directly on Ubuntu via SSH
- Simple but less convenient

**Option C: Git Workflow**
- Edit on Mac locally
- Commit and push
- Pull on Ubuntu
- More steps but familiar workflow

---

## Option 2: Develop on Mac, Deploy to Ubuntu (Alternative)

### Setup

Develop Laravel/Filament on your Mac, then deploy/test on Ubuntu.

### Advantages

✅ **Mac Development Experience**
- Use your preferred Mac tools (VS Code, PHPStorm, etc.)
- Native file system performance
- Familiar environment

✅ **Isolated Development**
- Don't affect OpenSIPS installation
- Can experiment freely
- Easy to reset/start over

### Disadvantages

❌ **Database Connection Required**
- Need to configure MySQL remote access
- Network latency for database queries
- More complex setup

❌ **OpenSIPS Integration Testing**
- Need to configure OpenSIPS MI access (if remote)
- Service management (systemctl) won't work from Mac
- Some features can't be tested locally

❌ **Environment Differences**
- Mac vs Ubuntu differences
- Need to sync environment variables
- Deployment step required for testing

### Setup Steps

```bash
# On Mac

# Install PHP 8.2 (via Homebrew)
brew install php@8.2

# Install Composer
brew install composer

# Create Laravel project
composer create-project laravel/laravel:^12.0 admin-panel
cd admin-panel

# Install Filament
composer require filament/filament:"^3.0"
php artisan filament:install --panels

# Configure database connection (remote MySQL)
# Edit .env file to point to Ubuntu MySQL
DB_HOST=ubuntu-machine-ip
DB_DATABASE=opensips
DB_USERNAME=opensips
DB_PASSWORD=...
```

### Development Workflow

```bash
# Develop on Mac
# Run development server
php artisan serve

# Access locally
# http://localhost:8000/admin

# Test with remote database
# Deploy to Ubuntu for OpenSIPS integration testing
```

---

## Option 3: Docker Development (Advanced)

### Setup

Use Docker Compose to run Laravel + MySQL + OpenSIPS in containers.

### Advantages

✅ **Consistent Environment**
- Same environment across Mac/Ubuntu
- Reproducible setup

✅ **Isolated**
- Doesn't affect system installations
- Easy to clean/reset

### Disadvantages

❌ **More Complex Setup**
- Docker configuration needed
- Need to handle OpenSIPS in Docker (if desired)
- Network configuration between containers

❌ **May Not Match Production**
- Container environment vs real Ubuntu
- Service management (systemctl) in containers is complex

---

## Recommendation: Option 1 (Develop on Ubuntu)

### Why This is Best for Your Use Case

1. **OpenSIPS Integration**
   - Test OpenSIPS MI integration directly
   - Test service management (systemctl) directly
   - Access to real OpenSIPS logs

2. **Database Access**
   - Local MySQL = fast queries
   - Real OpenSIPS data for testing
   - No network configuration

3. **Simpler Workflow**
   - Single environment
   - No deployment step for testing
   - Closer to production

4. **File Editing**
   - Use VS Code Remote-SSH for best experience
   - Edit files on Ubuntu from Mac
   - Get Mac editor experience with Ubuntu execution

### Recommended Setup

**Development Environment:**
- **Server:** Ubuntu machine (where OpenSIPS is installed)
- **File Editing:** VS Code with Remote-SSH extension (from Mac)
- **Database:** Local MySQL (OpenSIPS database)
- **Development Server:** `php artisan serve` on Ubuntu
- **Access:** Browser on Mac → `http://ubuntu-ip:8000/admin`

**Benefits:**
- ✅ Best development experience (VS Code Remote-SSH)
- ✅ Direct OpenSIPS integration
- ✅ Real database access
- ✅ Single environment (simpler)

---

## Required Software on Ubuntu

### Minimum Requirements

```bash
# PHP 8.2 with extensions
sudo apt install php8.2 php8.2-cli php8.2-fpm php8.2-mysql php8.2-xml php8.2-mbstring php8.2-curl php8.2-zip

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Git
sudo apt install git
```

### Optional (for future needs)

```bash
# Node.js (if needed for custom asset compilation)
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

**Note:** Filament uses precompiled Tailwind CSS, so Node.js is NOT required for basic development. Only needed if you want to customize Tailwind or compile custom assets.

---

## Development Workflow (Recommended)

### Setup Phase

1. **SSH into Ubuntu machine**
   ```bash
   ssh user@ubuntu-machine
   ```

2. **Install PHP/Composer** (if not already installed)

3. **Create Laravel project**
   ```bash
   cd /var/www  # or preferred location
   composer create-project laravel/laravel:^12.0 admin-panel
   cd admin-panel
   ```

4. **Install Filament**
   ```bash
   composer require filament/filament:"^3.0"
   php artisan filament:install --panels
   php artisan make:filament-user
   ```

5. **Configure database** (connect to OpenSIPS MySQL)

6. **Start development server**
   ```bash
   php artisan serve --host=0.0.0.0 --port=8000
   ```

### Daily Development

1. **Open VS Code on Mac**
2. **Connect via Remote-SSH** to Ubuntu machine
3. **Edit files** in VS Code (files are on Ubuntu)
4. **Access in browser** on Mac: `http://ubuntu-ip:8000/admin`
5. **See changes immediately** (Livewire hot reload works automatically)

### Testing

- Test with real OpenSIPS database
- Test OpenSIPS MI integration (localhost)
- Test service management (systemctl on Ubuntu)
- Test S3/Minio (if running locally)

---

## Alternative: Hybrid Approach

If you prefer Mac development but want easy testing:

1. **Develop on Mac** (for coding comfort)
2. **Use Git workflow** (commit, push, pull on Ubuntu)
3. **Test on Ubuntu** (for OpenSIPS integration)

This works but adds a deployment step for testing.

---

## Final Recommendation

**Develop on Ubuntu machine using VS Code Remote-SSH**

**Why:**
- ✅ Best integration with OpenSIPS
- ✅ Real database access
- ✅ Can test all features (MI, systemctl, etc.)
- ✅ VS Code Remote-SSH gives you Mac editor experience
- ✅ Single environment (simpler)
- ✅ Closer to production setup

**Setup Time:** ~15-30 minutes  
**Daily Workflow:** Very smooth with Remote-SSH

Would you like me to create a detailed setup guide for this approach?
