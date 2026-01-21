#!/bin/bash
#
# PBX3SBC Admin Panel Installation Script
# Installs and configures Laravel + Filament admin panel
#
# Usage: ./install.sh [--skip-deps] [--skip-prereqs] [--skip-migrations] [--db-host HOST] [--db-port PORT] [--db-user USER] [--db-password PASSWORD] [--db-name NAME] [--no-admin-user] [--admin-name NAME] [--admin-email EMAIL] [--admin-password PASSWORD] [--opensips-mi-url URL]
#

set -euo pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
INSTALL_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="${INSTALL_DIR}/.env"

# Flags
SKIP_DEPS=false
SKIP_MIGRATIONS=false
NO_ADMIN_USER=false
SKIP_PREREQS=false
DB_HOST=""
DB_USER=""
DB_PASSWORD=""
DB_NAME=""
DB_PORT=""  # Will prompt if not provided, defaults to 3306
OPENSIPS_MI_URL=""
ADMIN_NAME=""
ADMIN_EMAIL=""
ADMIN_PASSWORD=""

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --skip-deps)
            SKIP_DEPS=true
            shift
            ;;
        --skip-prereqs)
            SKIP_PREREQS=true
            shift
            ;;
        --skip-migrations)
            SKIP_MIGRATIONS=true
            shift
            ;;
        --no-admin-user)
            NO_ADMIN_USER=true
            shift
            ;;
        --db-host)
            if [[ -z "${2:-}" ]]; then
                echo -e "${RED}Error: --db-host requires a hostname/IP${NC}"
                exit 1
            fi
            DB_HOST="$2"
            shift 2
            ;;
        --db-user)
            if [[ -z "${2:-}" ]]; then
                echo -e "${RED}Error: --db-user requires a username${NC}"
                exit 1
            fi
            DB_USER="$2"
            shift 2
            ;;
        --db-password)
            if [[ -z "${2:-}" ]]; then
                echo -e "${RED}Error: --db-password requires a password${NC}"
                exit 1
            fi
            DB_PASSWORD="$2"
            shift 2
            ;;
        --db-name)
            if [[ -z "${2:-}" ]]; then
                echo -e "${RED}Error: --db-name requires a database name${NC}"
                exit 1
            fi
            DB_NAME="$2"
            shift 2
            ;;
        --db-port)
            if [[ -z "${2:-}" ]]; then
                echo -e "${RED}Error: --db-port requires a port number${NC}"
                exit 1
            fi
            DB_PORT="$2"
            shift 2
            ;;
        --opensips-mi-url)
            if [[ -z "${2:-}" ]]; then
                echo -e "${RED}Error: --opensips-mi-url requires a URL${NC}"
                exit 1
            fi
            OPENSIPS_MI_URL="$2"
            shift 2
            ;;
        --admin-name)
            if [[ -z "${2:-}" ]]; then
                echo -e "${RED}Error: --admin-name requires a name${NC}"
                exit 1
            fi
            ADMIN_NAME="$2"
            shift 2
            ;;
        --admin-email)
            if [[ -z "${2:-}" ]]; then
                echo -e "${RED}Error: --admin-email requires an email${NC}"
                exit 1
            fi
            ADMIN_EMAIL="$2"
            shift 2
            ;;
        --admin-password)
            if [[ -z "${2:-}" ]]; then
                echo -e "${RED}Error: --admin-password requires a password${NC}"
                exit 1
            fi
            ADMIN_PASSWORD="$2"
            shift 2
            ;;
        *)
            echo -e "${RED}Unknown option: $1${NC}"
            echo "Usage: $0 [--skip-deps] [--skip-prereqs] [--skip-migrations] [--db-host HOST] [--db-port PORT] [--db-user USER] [--db-password PASSWORD] [--db-name NAME] [--no-admin-user] [--opensips-mi-url URL] [--admin-name NAME] [--admin-email EMAIL] [--admin-password PASSWORD]"
            exit 1
            ;;
    esac
done

# Functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

detect_os() {
    if [[ -f /etc/os-release ]]; then
        . /etc/os-release
        OS_ID="$ID"
        OS_VERSION_ID="$VERSION_ID"
    elif [[ -f /etc/redhat-release ]]; then
        OS_ID="rhel"
        OS_VERSION_ID="unknown"
    else
        OS_ID="unknown"
        OS_VERSION_ID="unknown"
    fi
    
    # Determine package manager
    if command -v apt-get &> /dev/null; then
        PKG_MANAGER="apt"
        PKG_INSTALL="sudo apt-get install -y"
        PKG_UPDATE="sudo apt-get update"
    elif command -v yum &> /dev/null; then
        PKG_MANAGER="yum"
        PKG_INSTALL="sudo yum install -y"
        PKG_UPDATE="sudo yum check-update || true"
    elif command -v dnf &> /dev/null; then
        PKG_MANAGER="dnf"
        PKG_INSTALL="sudo dnf install -y"
        PKG_UPDATE="sudo dnf check-update || true"
    else
        PKG_MANAGER="unknown"
        PKG_INSTALL=""
        PKG_UPDATE=""
    fi
}

check_root_access() {
    if [[ $EUID -eq 0 ]]; then
        log_warn "Running as root. This is not recommended for security reasons."
        log_warn "The installer will use 'sudo' for privilege escalation when needed."
    fi
    
    # Check if sudo is available
    if ! command -v sudo &> /dev/null; then
        if [[ $EUID -ne 0 ]]; then
            log_error "sudo is not installed and you are not running as root."
            log_error "Please install sudo or run as root."
            exit 1
        fi
    fi
}

install_php() {
    log_info "Checking PHP installation..."
    
    if command -v php &> /dev/null; then
        PHP_VERSION=$(php -r 'echo PHP_VERSION;' | cut -d. -f1,2)
        PHP_MAJOR=$(echo "$PHP_VERSION" | cut -d. -f1)
        PHP_MINOR=$(echo "$PHP_VERSION" | cut -d. -f2)
        
        if [[ $PHP_MAJOR -lt 8 ]] || [[ $PHP_MAJOR -eq 8 && $PHP_MINOR -lt 2 ]]; then
            log_warn "PHP version $PHP_VERSION is too old. PHP 8.2+ required."
            log_info "Attempting to install PHP 8.2+..."
        else
            log_success "PHP $PHP_VERSION is installed"
            return 0
        fi
    else
        log_info "PHP is not installed. Installing PHP 8.2+..."
    fi
    
    if [[ "$PKG_MANAGER" == "apt" ]]; then
        # Ubuntu/Debian
        log_info "Updating package lists..."
        $PKG_UPDATE
        
        # Detect Ubuntu version to prioritize PHP version
        # Ubuntu 24.04 ships with PHP 8.3, Ubuntu 22.04 with PHP 8.1/8.2
        if [[ -f /etc/os-release ]]; then
            . /etc/os-release
            if [[ "$VERSION_ID" == "24.04" ]] || [[ "$VERSION_ID" == "24.10" ]]; then
                PHP_PRIORITY_VERSION="8.3"
            elif [[ "$VERSION_ID" == "22.04" ]]; then
                PHP_PRIORITY_VERSION="8.2"
            else
                PHP_PRIORITY_VERSION="8.2"
            fi
        else
            PHP_PRIORITY_VERSION="8.2"
        fi
        
        # Try to install priority version first, then fall back
        log_info "Attempting to install PHP ${PHP_PRIORITY_VERSION}..."
        if $PKG_INSTALL php${PHP_PRIORITY_VERSION}-cli php${PHP_PRIORITY_VERSION}-common 2>&1 >/dev/null; then
            log_success "PHP ${PHP_PRIORITY_VERSION} installed"
        else
            # Try PHP 8.2
            log_info "PHP ${PHP_PRIORITY_VERSION} not available, trying PHP 8.2..."
            if $PKG_INSTALL php8.2-cli php8.2-common 2>&1 >/dev/null; then
                log_success "PHP 8.2 installed"
            else
                # Try PHP 8.1
                log_info "PHP 8.2 not available, trying PHP 8.1..."
                if $PKG_INSTALL php8.1-cli php8.1-common 2>&1 >/dev/null; then
                    log_success "PHP 8.1 installed"
                else
                    # Install default PHP version
                    log_info "Installing default PHP version..."
                    $PKG_INSTALL php-cli php-common
                fi
            fi
        fi
    elif [[ "$PKG_MANAGER" == "yum" ]] || [[ "$PKG_MANAGER" == "dnf" ]]; then
        # RHEL/CentOS/Fedora
        log_info "Installing PHP..."
        $PKG_INSTALL php php-cli php-common
        log_success "PHP installed"
    else
        log_error "Unable to automatically install PHP. Unknown package manager."
        log_error "Please install PHP 8.2+ manually and run the installer again."
        exit 1
    fi
    
    # Verify installation
    if ! command -v php &> /dev/null; then
        log_error "PHP installation failed"
        exit 1
    fi
    
    PHP_VERSION=$(php -r 'echo PHP_VERSION;' | cut -d. -f1,2)
    PHP_MAJOR=$(echo "$PHP_VERSION" | cut -d. -f1)
    PHP_MINOR=$(echo "$PHP_VERSION" | cut -d. -f2)
    
    if [[ $PHP_MAJOR -lt 8 ]] || [[ $PHP_MAJOR -eq 8 && $PHP_MINOR -lt 2 ]]; then
        log_error "Installed PHP version $PHP_VERSION is too old. PHP 8.2+ required."
        log_error "Please install PHP 8.2+ manually and run the installer again."
        exit 1
    fi
    
    log_success "PHP $PHP_VERSION is ready"
}

install_php_extensions() {
    log_info "Checking PHP extensions..."
    
    PHP_VERSION=$(php -r 'echo PHP_VERSION;' | cut -d. -f1,2)
    PHP_MAJOR=$(echo "$PHP_VERSION" | cut -d. -f1)
    PHP_MINOR=$(echo "$PHP_VERSION" | cut -d. -f2)
    
    REQUIRED_EXTENSIONS=("pdo" "pdo_mysql" "mbstring" "xml" "curl" "zip" "bcmath" "intl")
    MISSING_EXTENSIONS=()
    
    # Get list of loaded extensions
    LOADED_EXTENSIONS=$(php -m 2>/dev/null)
    
    for ext in "${REQUIRED_EXTENSIONS[@]}"; do
        # Special handling for PDO - check if PDO class exists
        if [[ "$ext" == "pdo" ]]; then
            if ! php -r "exit(class_exists('PDO') ? 0 : 1);" 2>/dev/null; then
                MISSING_EXTENSIONS+=("$ext")
            fi
        # Special handling for pdo_mysql - check if PDO MySQL driver is available
        elif [[ "$ext" == "pdo_mysql" ]]; then
            if ! php -r "exit(class_exists('PDO') && in_array('mysql', PDO::getAvailableDrivers()) ? 0 : 1);" 2>/dev/null; then
                MISSING_EXTENSIONS+=("$ext")
            fi
        # For other extensions, check if they're in php -m output
        elif ! echo "$LOADED_EXTENSIONS" | grep -q "^${ext}$"; then
            MISSING_EXTENSIONS+=("$ext")
        fi
    done
    
    if [[ ${#MISSING_EXTENSIONS[@]} -eq 0 ]]; then
        log_success "All required PHP extensions are installed"
        return 0
    fi
    
    log_info "Installing missing PHP extensions: ${MISSING_EXTENSIONS[*]}"
    
    if [[ "$PKG_MANAGER" == "apt" ]]; then
        # Ubuntu/Debian - try version-specific packages first
        log_info "Detected Ubuntu/Debian. Installing PHP extensions..."
        
        # Update package lists first
        log_info "Updating package lists..."
        $PKG_UPDATE
        
        PACKAGES_TO_INSTALL=()
        NEED_MYSQL=false
        
        for ext in "${MISSING_EXTENSIONS[@]}"; do
            case $ext in
                pdo_mysql) 
                    NEED_MYSQL=true 
                    ;;
                pdo) 
                    # PDO is included in php-common, but we check if it's available
                    # If pdo_mysql is needed, mysql package will include PDO
                    ;;
                mbstring) 
                    PACKAGES_TO_INSTALL+=("php${PHP_MAJOR}.${PHP_MINOR}-mbstring") 
                    ;;
                xml) 
                    PACKAGES_TO_INSTALL+=("php${PHP_MAJOR}.${PHP_MINOR}-xml") 
                    ;;
                curl) 
                    PACKAGES_TO_INSTALL+=("php${PHP_MAJOR}.${PHP_MINOR}-curl") 
                    ;;
                zip) 
                    PACKAGES_TO_INSTALL+=("php${PHP_MAJOR}.${PHP_MINOR}-zip") 
                    ;;
                bcmath) 
                    PACKAGES_TO_INSTALL+=("php${PHP_MAJOR}.${PHP_MINOR}-bcmath") 
                    ;;
                intl) 
                    PACKAGES_TO_INSTALL+=("php${PHP_MAJOR}.${PHP_MINOR}-intl") 
                    ;;
            esac
        done
        
        # Add mysql package if pdo_mysql is missing
        if [[ "$NEED_MYSQL" == "true" ]]; then
            PACKAGES_TO_INSTALL+=("php${PHP_MAJOR}.${PHP_MINOR}-mysql")
        fi
        
        # Remove duplicates and install
        if [[ ${#PACKAGES_TO_INSTALL[@]} -gt 0 ]]; then
            UNIQUE_PACKAGES=($(printf '%s\n' "${PACKAGES_TO_INSTALL[@]}" | sort -u))
            log_info "Installing PHP extension packages: ${UNIQUE_PACKAGES[*]}"
            if ! $PKG_INSTALL "${UNIQUE_PACKAGES[@]}"; then
                log_error "Failed to install PHP extension packages"
                log_error ""
                log_error "Please install manually on Ubuntu 24.04:"
                log_error "  sudo apt-get update"
                log_error "  sudo apt-get install -y ${UNIQUE_PACKAGES[*]}"
                log_error ""
                log_error "Or if the version-specific packages don't exist, try:"
                log_error "  sudo apt-get install -y php-mysql php-xml php-mbstring php-curl php-zip php-bcmath php-intl"
                exit 1
            fi
        else
            log_info "All required extensions are available (PDO is core)"
        fi
        
    elif [[ "$PKG_MANAGER" == "yum" ]] || [[ "$PKG_MANAGER" == "dnf" ]]; then
        # RHEL/CentOS/Fedora
        log_info "Installing PHP extensions via $PKG_MANAGER..."
        if ! $PKG_INSTALL php-mysqlnd php-xml php-mbstring php-curl php-zip php-bcmath php-intl; then
            log_error "Failed to install PHP extensions via $PKG_MANAGER"
            log_error "Please install manually: sudo $PKG_INSTALL php-mysqlnd php-xml php-mbstring php-curl php-zip php-bcmath php-intl"
            exit 1
        fi
    elif [[ "$(uname)" == "Darwin" ]]; then
        # macOS - Check if using Homebrew
        if command -v brew &> /dev/null; then
            log_info "Detected macOS with Homebrew. Checking PHP installation method..."
            PHP_BREW_PATH=$(brew --prefix php@${PHP_MAJOR}.${PHP_MINOR} 2>/dev/null || brew --prefix php 2>/dev/null || echo "")
            
            if [[ -n "$PHP_BREW_PATH" ]]; then
                log_info "PHP installed via Homebrew. Attempting to install extensions..."
                # Try to install extensions via Homebrew
                if brew list php@${PHP_MAJOR}.${PHP_MINOR} &>/dev/null; then
                    log_info "Installing PHP extensions via Homebrew..."
                    brew install php@${PHP_MAJOR}.${PHP_MINOR} || log_warn "Some extensions may already be installed"
                else
                    log_warn "PHP ${PHP_MAJOR}.${PHP_MINOR} not found via Homebrew"
                    log_info "If extensions are missing, install via: brew install php@${PHP_MAJOR}.${PHP_MINOR}"
                fi
            else
                log_info "PHP may be installed via Laravel Herd or other method."
                log_info "Extensions should already be available. If not, check Herd settings."
            fi
            
            # On macOS with Herd, extensions are usually pre-installed
            # Just verify they're available, don't try to install
            log_info "Verifying extensions are available..."
        else
            log_warn "macOS detected but Homebrew not found."
            log_warn "PHP extensions should be available if using Laravel Herd or system PHP."
            log_warn "If missing, install via: brew install php@${PHP_MAJOR}.${PHP_MINOR}"
        fi
    else
        log_error "Unable to automatically install PHP extensions. Unknown package manager: $PKG_MANAGER"
        log_error "Detected OS: $(uname -s)"
        log_error ""
        log_error "Please install the missing extensions manually:"
        log_error "  Missing: ${MISSING_EXTENSIONS[*]}"
        log_error ""
        log_error "For Ubuntu/Debian: sudo apt-get install php${PHP_MAJOR}.${PHP_MINOR}-mysql php${PHP_MAJOR}.${PHP_MINOR}-xml php${PHP_MAJOR}.${PHP_MINOR}-mbstring php${PHP_MAJOR}.${PHP_MINOR}-curl php${PHP_MAJOR}.${PHP_MINOR}-zip php${PHP_MAJOR}.${PHP_MINOR}-bcmath php${PHP_MAJOR}.${PHP_MINOR}-intl"
        log_error "For RHEL/CentOS/Fedora: sudo $PKG_MANAGER install php-mysqlnd php-xml php-mbstring php-curl php-zip php-bcmath php-intl"
        exit 1
    fi
    
    # Verify extensions are now available
    # Wait a moment for package manager to finish
    sleep 1
    
    # Get fresh list of loaded extensions
    LOADED_EXTENSIONS=$(php -m 2>/dev/null)
    
    STILL_MISSING=()
    for ext in "${MISSING_EXTENSIONS[@]}"; do
        # Special handling for PDO - check if PDO class exists
        if [[ "$ext" == "pdo" ]]; then
            if ! php -r "exit(class_exists('PDO') ? 0 : 1);" 2>/dev/null; then
                STILL_MISSING+=("$ext")
            fi
        # For pdo_mysql, check if PDO MySQL driver is available
        elif [[ "$ext" == "pdo_mysql" ]]; then
            if ! php -r "exit(class_exists('PDO') && in_array('mysql', PDO::getAvailableDrivers()) ? 0 : 1);" 2>/dev/null; then
                STILL_MISSING+=("$ext")
            fi
        # For other extensions, check if they're in php -m output
        elif ! echo "$LOADED_EXTENSIONS" | grep -q "^${ext}$"; then
            STILL_MISSING+=("$ext")
        fi
    done
    
    if [[ ${#STILL_MISSING[@]} -gt 0 ]]; then
        log_error "Failed to verify PHP extensions: ${STILL_MISSING[*]}"
        log_error ""
        log_error "The packages were installed, but PHP cannot load them."
        log_error "This may require a PHP-FPM or web server restart."
        log_error ""
        log_error "To verify extensions manually:"
        log_error "  php -m | grep -E '(pdo|pdo_mysql|mbstring|xml|curl|zip|bcmath|intl)'"
        log_error ""
        log_error "If extensions show in 'php -m', you can continue with --skip-prereqs"
        exit 1
    fi
    
    log_success "All required PHP extensions are installed"
}

install_composer() {
    log_info "Checking Composer installation..."
    
    if command -v composer &> /dev/null; then
        COMPOSER_VERSION=$(composer --version | head -n1)
        log_success "Composer is installed: $COMPOSER_VERSION"
        return 0
    fi
    
    log_info "Composer is not installed. Installing Composer..."
    
    # Download and install Composer
    EXPECTED_CHECKSUM="$(php -r 'copy("https://composer.github.io/installer.sig", "php://stdout");')"
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"
    
    if [ "$EXPECTED_CHECKSUM" != "$ACTUAL_CHECKSUM" ]; then
        rm composer-setup.php
        log_error "Composer installer checksum verification failed!"
        exit 1
    fi
    
    php composer-setup.php --quiet
    RESULT=$?
    rm composer-setup.php
    
    if [ $RESULT -ne 0 ]; then
        log_error "Composer installation failed"
        exit 1
    fi
    
    # Move to a system-wide location if we have permissions
    if [[ -w /usr/local/bin ]]; then
        sudo mv composer.phar /usr/local/bin/composer
        log_success "Composer installed to /usr/local/bin/composer"
    elif [[ $EUID -eq 0 ]]; then
        mv composer.phar /usr/local/bin/composer
        log_success "Composer installed to /usr/local/bin/composer"
    else
        # Use local composer.phar
        chmod +x composer.phar
        log_success "Composer installed as composer.phar (run with: php composer.phar)"
        log_warn "Consider moving composer.phar to /usr/local/bin/composer manually"
        # Update PATH for this script
        export PATH="$INSTALL_DIR:$PATH"
    fi
    
    # Verify installation
    if command -v composer &> /dev/null; then
        COMPOSER_VERSION=$(composer --version | head -n1)
        log_success "Composer installed: $COMPOSER_VERSION"
    else
        log_error "Composer installation verification failed"
        exit 1
    fi
}

check_mysql() {
    log_info "Checking MySQL/MariaDB installation..."
    
    if command -v mysql &> /dev/null || command -v mariadb &> /dev/null; then
        log_success "MySQL/MariaDB client is installed"
    else
        log_warn "MySQL/MariaDB client is not installed"
        log_warn "You may be using Docker MySQL or a remote MySQL server"
        log_warn "If you need local MySQL, install it manually:"
        if [[ "$PKG_MANAGER" == "apt" ]]; then
            log_warn "  sudo apt-get install mysql-server"
        elif [[ "$PKG_MANAGER" == "yum" ]] || [[ "$PKG_MANAGER" == "dnf" ]]; then
            log_warn "  sudo $PKG_MANAGER install mysql-server"
        fi
    fi
}

install_php_fpm() {
    log_info "Checking PHP-FPM installation..."
    
    PHP_VERSION=$(php -r 'echo PHP_VERSION;' | cut -d. -f1,2)
    PHP_MAJOR=$(echo "$PHP_VERSION" | cut -d. -f1)
    PHP_MINOR=$(echo "$PHP_VERSION" | cut -d. -f2)
    
    # Check if PHP-FPM service actually exists (more reliable check)
    PHP_FPM_INSTALLED=false
    if systemctl list-unit-files | grep -q "php${PHP_MAJOR}.${PHP_MINOR}-fpm.service" 2>/dev/null; then
        PHP_FPM_INSTALLED=true
    elif systemctl list-unit-files | grep -q "php${PHP_MAJOR}-fpm.service" 2>/dev/null; then
        PHP_FPM_INSTALLED=true
    elif systemctl list-unit-files | grep -q "php-fpm.service" 2>/dev/null; then
        PHP_FPM_INSTALLED=true
    elif [ -f "/etc/init.d/php${PHP_MAJOR}.${PHP_MINOR}-fpm" ] || [ -f "/etc/init.d/php-fpm" ]; then
        PHP_FPM_INSTALLED=true
    fi
    
    if [[ "$PHP_FPM_INSTALLED" == "true" ]]; then
        log_success "PHP-FPM is installed"
        return 0
    fi
    
    log_info "PHP-FPM is not installed. Installing PHP-FPM..."
    
    if [[ "$PKG_MANAGER" == "apt" ]]; then
        # Try to install matching PHP version
        if $PKG_INSTALL php${PHP_MAJOR}.${PHP_MINOR}-fpm 2>&1 >/dev/null; then
            log_success "PHP-FPM ${PHP_MAJOR}.${PHP_MINOR} installed"
        else
            # Fallback to php-fpm
            $PKG_INSTALL php-fpm
            log_success "PHP-FPM installed"
        fi
    elif [[ "$PKG_MANAGER" == "yum" ]] || [[ "$PKG_MANAGER" == "dnf" ]]; then
        $PKG_INSTALL php-fpm
        log_success "PHP-FPM installed"
    else
        log_error "Unable to automatically install PHP-FPM. Unknown package manager."
        log_error "Please install PHP-FPM manually and run the installer again."
        exit 1
    fi
    
    # Enable and start PHP-FPM service
    if systemctl is-enabled php*-fpm &>/dev/null || systemctl is-enabled php-fpm &>/dev/null; then
        log_info "PHP-FPM service is already enabled"
    else
        # Try to enable the service (version-specific or generic)
        if systemctl enable php${PHP_MAJOR}.${PHP_MINOR}-fpm &>/dev/null || \
           systemctl enable php-fpm &>/dev/null || \
           systemctl enable php${PHP_MAJOR}-fpm &>/dev/null; then
            log_success "PHP-FPM service enabled"
        else
            log_warn "Could not enable PHP-FPM service automatically"
        fi
    fi
    
    # Start PHP-FPM if not running
    if systemctl is-active --quiet php*-fpm 2>/dev/null || systemctl is-active --quiet php-fpm 2>/dev/null; then
        log_info "PHP-FPM service is running"
    else
        if systemctl start php${PHP_MAJOR}.${PHP_MINOR}-fpm &>/dev/null || \
           systemctl start php-fpm &>/dev/null || \
           systemctl start php${PHP_MAJOR}-fpm &>/dev/null; then
            log_success "PHP-FPM service started"
        else
            log_warn "Could not start PHP-FPM service automatically"
            log_warn "You may need to start it manually: systemctl start php-fpm"
        fi
    fi
}

install_nginx() {
    log_info "Checking nginx installation..."
    
    if command -v nginx &> /dev/null; then
        NGINX_VERSION=$(nginx -v 2>&1 | grep -oP '\d+\.\d+\.\d+' | head -n1 || echo "unknown")
        log_success "nginx is installed (version: $NGINX_VERSION)"
        return 0
    fi
    
    log_info "nginx is not installed. Installing nginx..."
    
    if [[ "$PKG_MANAGER" == "apt" ]]; then
        $PKG_UPDATE
        $PKG_INSTALL nginx
    elif [[ "$PKG_MANAGER" == "yum" ]] || [[ "$PKG_MANAGER" == "dnf" ]]; then
        $PKG_INSTALL nginx
    else
        log_error "Unable to automatically install nginx. Unknown package manager."
        log_error "Please install nginx manually and run the installer again."
        exit 1
    fi
    
    # Verify installation
    if ! command -v nginx &> /dev/null; then
        log_error "nginx installation failed"
        exit 1
    fi
    
    log_success "nginx installed"
}

configure_nginx() {
    # Check if nginx is installed
    if ! command -v nginx &> /dev/null; then
        log_warn "nginx is not installed. Skipping nginx configuration."
        log_warn "Install nginx manually or run without --skip-prereqs to auto-install."
        return 0
    fi
    
    log_info "Configuring nginx for Laravel..."
    
    # Detect PHP-FPM socket path
    PHP_VERSION=$(php -r 'echo PHP_VERSION;' | cut -d. -f1,2)
    PHP_MAJOR=$(echo "$PHP_VERSION" | cut -d. -f1)
    PHP_MINOR=$(echo "$PHP_VERSION" | cut -d. -f2)
    
    # Try to find PHP-FPM socket
    PHP_FPM_SOCKET=""
    if [[ -S "/var/run/php/php${PHP_MAJOR}.${PHP_MINOR}-fpm.sock" ]]; then
        PHP_FPM_SOCKET="/var/run/php/php${PHP_MAJOR}.${PHP_MINOR}-fpm.sock"
    elif [[ -S "/var/run/php/php${PHP_MAJOR}-fpm.sock" ]]; then
        PHP_FPM_SOCKET="/var/run/php/php${PHP_MAJOR}-fpm.sock"
    elif [[ -S "/var/run/php-fpm/php-fpm.sock" ]]; then
        PHP_FPM_SOCKET="/var/run/php-fpm/php-fpm.sock"
    elif [[ -S "/run/php/php${PHP_MAJOR}.${PHP_MINOR}-fpm.sock" ]]; then
        PHP_FPM_SOCKET="/run/php/php${PHP_MAJOR}.${PHP_MINOR}-fpm.sock"
    else
        # Default to common Ubuntu/Debian path
        PHP_FPM_SOCKET="/var/run/php/php${PHP_MAJOR}.${PHP_MINOR}-fpm.sock"
        log_warn "Could not detect PHP-FPM socket, using default: $PHP_FPM_SOCKET"
        log_warn "You may need to update the nginx config if this is incorrect"
    fi
    
    # Determine server name (try to get hostname or use localhost)
    SERVER_NAME=$(hostname -f 2>/dev/null || hostname 2>/dev/null || echo "localhost")
    
    # Create nginx config file
    NGINX_CONFIG="/etc/nginx/sites-available/pbx3sbc-admin"
    NGINX_ENABLED="/etc/nginx/sites-enabled/pbx3sbc-admin"
    
    # Check if config already exists
    if [[ -f "$NGINX_CONFIG" ]]; then
        log_info "nginx config already exists at $NGINX_CONFIG"
        log_info "Skipping nginx configuration (use --reconfigure-nginx to override)"
        # Still enable the site if not already enabled
        if [[ ! -L "$NGINX_ENABLED" ]]; then
            sudo ln -sf "$NGINX_CONFIG" "$NGINX_ENABLED"
            log_success "Enabled nginx site"
        fi
        return 0
    fi
    
    log_info "Creating nginx configuration..."
    
    # Create nginx config
    sudo tee "$NGINX_CONFIG" > /dev/null <<EOF
server {
    listen 80;
    listen [::]:80;
    server_name ${SERVER_NAME};
    root ${INSTALL_DIR}/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:${PHP_FPM_SOCKET};
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF
    
    log_success "nginx configuration created at $NGINX_CONFIG"
    
    # Enable the site
    if [[ -d "/etc/nginx/sites-enabled" ]]; then
        sudo ln -sf "$NGINX_CONFIG" "$NGINX_ENABLED"
        log_success "Enabled nginx site"
        
        # Remove default site if it exists
        if [[ -L "/etc/nginx/sites-enabled/default" ]]; then
            sudo rm -f /etc/nginx/sites-enabled/default
            log_info "Removed default nginx site"
        fi
    fi
    
    # Test nginx configuration
    if sudo nginx -t &>/dev/null; then
        log_success "nginx configuration test passed"
    else
        log_error "nginx configuration test failed"
        log_error "Please check the configuration manually: sudo nginx -t"
        exit 1
    fi
    
    # Reload nginx
    if systemctl is-active --quiet nginx; then
        if sudo systemctl reload nginx &>/dev/null; then
            log_success "nginx reloaded"
        else
            log_warn "Could not reload nginx. You may need to restart it manually: sudo systemctl restart nginx"
        fi
    else
        # Start nginx if not running
        if sudo systemctl enable nginx &>/dev/null && sudo systemctl start nginx &>/dev/null; then
            log_success "nginx started and enabled"
        else
            log_warn "Could not start nginx. You may need to start it manually: sudo systemctl start nginx"
        fi
    fi
}

check_prerequisites() {
    log_info "Checking and installing prerequisites..."
    
    detect_os
    check_root_access
    install_php
    install_php_extensions
    install_php_fpm
    install_nginx
    install_composer
    check_mysql  # Optional - just warn if missing
    
    log_success "Prerequisites check complete"
}

install_dependencies() {
    if [[ "$SKIP_DEPS" == "true" ]]; then
        log_info "Skipping dependency installation (--skip-deps)"
        return
    fi
    
    log_info "Installing PHP dependencies..."
    cd "$INSTALL_DIR"
    
    # Set COMPOSER_ALLOW_SUPERUSER to avoid warnings when running as root
    export COMPOSER_ALLOW_SUPERUSER=1
    # Disable plugins for non-interactive mode
    export COMPOSER_DISABLE_XDEBUG_WARN=1
    
    if [[ -f "composer.lock" ]]; then
        # Try to install from lock file first
        log_info "Attempting to install from composer.lock..."
        
        # Check if timeout command is available
        if command -v timeout &> /dev/null; then
            TIMEOUT_CMD="timeout 300"
        else
            TIMEOUT_CMD=""
            log_warn "timeout command not available, composer may hang if there are issues"
        fi
        
        # Run composer install and capture both stdout and stderr
        # Use a temporary file to capture output since command substitution might hang
        TEMP_OUTPUT=$(mktemp)
        if $TIMEOUT_CMD composer install --no-interaction --no-plugins --prefer-dist --optimize-autoloader > "$TEMP_OUTPUT" 2>&1; then
            INSTALL_EXIT=0
        else
            INSTALL_EXIT=$?
        fi
        
        # Show the output
        cat "$TEMP_OUTPUT"
        COMPOSER_OUTPUT=$(cat "$TEMP_OUTPUT")
        rm -f "$TEMP_OUTPUT"
        
        # Check if lock file is incompatible
        if echo "$COMPOSER_OUTPUT" | grep -q "Your lock file does not contain a compatible set of packages"; then
            log_warn "Lock file is incompatible with current PHP version."
            log_info "Updating dependencies to match current PHP version..."
            if ! $TIMEOUT_CMD composer update --no-interaction --no-plugins --prefer-dist --optimize-autoloader; then
                log_error "Failed to install dependencies"
                log_error "Please check your PHP version and composer.json requirements"
                exit 1
            fi
        elif [[ $INSTALL_EXIT -eq 124 ]]; then
            log_error "Composer install timed out after 5 minutes"
            exit 1
        elif [[ $INSTALL_EXIT -ne 0 ]]; then
            log_warn "Composer install failed (exit code: $INSTALL_EXIT). Trying update..."
            if ! $TIMEOUT_CMD composer update --no-interaction --no-plugins --prefer-dist --optimize-autoloader; then
                log_error "Failed to install dependencies"
                log_error "Please check your PHP version and composer.json requirements"
                exit 1
            fi
        fi
    else
        log_info "No composer.lock found. Installing dependencies..."
        if ! timeout 600 composer update --no-interaction --no-plugins --prefer-dist --optimize-autoloader; then
            log_error "Failed to install dependencies"
            exit 1
        fi
    fi
    
    log_success "Dependencies installed"
}

setup_environment() {
    log_info "Setting up environment configuration..."
    cd "$INSTALL_DIR"
    
    # Copy .env.example to .env if .env doesn't exist
    if [[ ! -f ".env" ]]; then
        if [[ -f ".env.example" ]]; then
            cp .env.example .env
            log_success "Created .env file from .env.example"
        else
            log_error ".env.example file not found"
            exit 1
        fi
    else
        log_warn ".env file already exists, will update database configuration"
    fi
    
    # Generate application key if not set
    if ! grep -q "^APP_KEY=base64:" "$ENV_FILE" 2>/dev/null; then
        log_info "Generating application key..."
        php artisan key:generate --force
        log_success "Application key generated"
    else
        log_info "Application key already exists"
    fi
    
    # Configure database connection
    log_info "Configuring database connection..."
    
    # Check if database config already exists in .env
    DB_CONFIG_EXISTS=false
    if grep -q "^DB_HOST=" "$ENV_FILE" 2>/dev/null && \
       grep -q "^DB_USERNAME=" "$ENV_FILE" 2>/dev/null && \
       grep -q "^DB_PASSWORD=" "$ENV_FILE" 2>/dev/null && \
       grep -q "^DB_DATABASE=" "$ENV_FILE" 2>/dev/null; then
        DB_CONFIG_EXISTS=true
    fi
    
    # Only prompt/update if config doesn't exist or if explicitly provided via arguments
    if [[ "$DB_CONFIG_EXISTS" == "true" ]] && [[ -z "$DB_HOST" ]] && [[ -z "$DB_USER" ]] && [[ -z "$DB_PASSWORD" ]] && [[ -z "$DB_NAME" ]]; then
        log_info "Database configuration already exists in .env file"
        log_info "Skipping database configuration (use --db-host, --db-user, etc. to override)"
    else
        # Prompt for database details if not provided via arguments
        if [[ -z "$DB_HOST" ]]; then
            if [[ "$DB_CONFIG_EXISTS" == "true" ]]; then
                CURRENT_HOST=$(grep "^DB_HOST=" "$ENV_FILE" | cut -d'=' -f2)
                echo -n "Database host [$CURRENT_HOST]: "
            else
                echo -n "Database host [127.0.0.1]: "
            fi
            read -r input_db_host
            if [[ -n "$input_db_host" ]]; then
                DB_HOST="$input_db_host"
            elif [[ "$DB_CONFIG_EXISTS" == "true" ]]; then
                DB_HOST="$CURRENT_HOST"
            else
                DB_HOST="127.0.0.1"
            fi
        fi
        
        if [[ -z "$DB_USER" ]]; then
            if [[ "$DB_CONFIG_EXISTS" == "true" ]]; then
                CURRENT_USER=$(grep "^DB_USERNAME=" "$ENV_FILE" | cut -d'=' -f2)
                echo -n "Database username [$CURRENT_USER]: "
            else
                echo -n "Database username [opensips]: "
            fi
            read -r input_db_user
            if [[ -n "$input_db_user" ]]; then
                DB_USER="$input_db_user"
            elif [[ "$DB_CONFIG_EXISTS" == "true" ]]; then
                DB_USER="$CURRENT_USER"
            else
                DB_USER="opensips"
            fi
        fi
        
        if [[ -z "$DB_PASSWORD" ]]; then
            if [[ "$DB_CONFIG_EXISTS" == "true" ]]; then
                CURRENT_PASSWORD=$(grep "^DB_PASSWORD=" "$ENV_FILE" | cut -d'=' -f2)
                echo -n "Database password [keep existing]: "
            else
                echo -n "Database password: "
            fi
            read -rs input_db_password
            echo
            if [[ -n "$input_db_password" ]]; then
                DB_PASSWORD="$input_db_password"
            elif [[ "$DB_CONFIG_EXISTS" == "true" ]]; then
                DB_PASSWORD="$CURRENT_PASSWORD"
            else
                DB_PASSWORD=""
            fi
        fi
        
        if [[ -z "$DB_NAME" ]]; then
            if [[ "$DB_CONFIG_EXISTS" == "true" ]]; then
                CURRENT_NAME=$(grep "^DB_DATABASE=" "$ENV_FILE" | cut -d'=' -f2)
                echo -n "Database name [$CURRENT_NAME]: "
            else
                echo -n "Database name [opensips]: "
            fi
            read -r input_db_name
            if [[ -n "$input_db_name" ]]; then
                DB_NAME="$input_db_name"
            elif [[ "$DB_CONFIG_EXISTS" == "true" ]]; then
                DB_NAME="$CURRENT_NAME"
            else
                DB_NAME="opensips"
            fi
        fi
        
        if [[ -z "$DB_PORT" ]]; then
            if [[ "$DB_CONFIG_EXISTS" == "true" ]]; then
                CURRENT_PORT=$(grep "^DB_PORT=" "$ENV_FILE" | cut -d'=' -f2)
                echo -n "Database port [$CURRENT_PORT]: "
            else
                echo -n "Database port [3306]: "
            fi
            read -r input_db_port
            if [[ -n "$input_db_port" ]]; then
                DB_PORT="$input_db_port"
            elif [[ "$DB_CONFIG_EXISTS" == "true" ]]; then
                DB_PORT="$CURRENT_PORT"
            else
                DB_PORT="3306"
            fi
        fi
        
        # Update .env file
        if [[ "$(uname)" == "Darwin" ]]; then
            # macOS
            sed -i '' "s/^DB_CONNECTION=.*/DB_CONNECTION=mysql/" "$ENV_FILE"
            sed -i '' "s|^# DB_HOST=.*|DB_HOST=${DB_HOST}|" "$ENV_FILE"
            sed -i '' "s|^DB_HOST=.*|DB_HOST=${DB_HOST}|" "$ENV_FILE"
            sed -i '' "s|^# DB_PORT=.*|DB_PORT=${DB_PORT}|" "$ENV_FILE"
            sed -i '' "s|^DB_PORT=.*|DB_PORT=${DB_PORT}|" "$ENV_FILE"
            sed -i '' "s|^# DB_DATABASE=.*|DB_DATABASE=${DB_NAME}|" "$ENV_FILE"
            sed -i '' "s|^DB_DATABASE=.*|DB_DATABASE=${DB_NAME}|" "$ENV_FILE"
            sed -i '' "s|^# DB_USERNAME=.*|DB_USERNAME=${DB_USER}|" "$ENV_FILE"
            sed -i '' "s|^DB_USERNAME=.*|DB_USERNAME=${DB_USER}|" "$ENV_FILE"
            sed -i '' "s|^# DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD}|" "$ENV_FILE"
            sed -i '' "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD}|" "$ENV_FILE"
        else
            # Linux
            sed -i "s/^DB_CONNECTION=.*/DB_CONNECTION=mysql/" "$ENV_FILE"
            sed -i "s|^# DB_HOST=.*|DB_HOST=${DB_HOST}|" "$ENV_FILE"
            sed -i "s|^DB_HOST=.*|DB_HOST=${DB_HOST}|" "$ENV_FILE"
            sed -i "s|^# DB_PORT=.*|DB_PORT=${DB_PORT}|" "$ENV_FILE"
            sed -i "s|^DB_PORT=.*|DB_PORT=${DB_PORT}|" "$ENV_FILE"
            sed -i "s|^# DB_DATABASE=.*|DB_DATABASE=${DB_NAME}|" "$ENV_FILE"
            sed -i "s|^DB_DATABASE=.*|DB_DATABASE=${DB_NAME}|" "$ENV_FILE"
            sed -i "s|^# DB_USERNAME=.*|DB_USERNAME=${DB_USER}|" "$ENV_FILE"
            sed -i "s|^DB_USERNAME=.*|DB_USERNAME=${DB_USER}|" "$ENV_FILE"
            sed -i "s|^# DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD}|" "$ENV_FILE"
            sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD}|" "$ENV_FILE"
        fi
        
        log_success "Database configuration updated"
    fi
    
    # Configure OpenSIPS MI URL if provided
    if [[ -n "$OPENSIPS_MI_URL" ]]; then
        log_info "Configuring OpenSIPS MI URL..."
        if [[ "$(uname)" == "Darwin" ]]; then
            sed -i '' "s|^OPENSIPS_MI_URL=.*|OPENSIPS_MI_URL=${OPENSIPS_MI_URL}|" "$ENV_FILE" 2>/dev/null || echo "OPENSIPS_MI_URL=${OPENSIPS_MI_URL}" >> "$ENV_FILE"
        else
            sed -i "s|^OPENSIPS_MI_URL=.*|OPENSIPS_MI_URL=${OPENSIPS_MI_URL}|" "$ENV_FILE" 2>/dev/null || echo "OPENSIPS_MI_URL=${OPENSIPS_MI_URL}" >> "$ENV_FILE"
        fi
        log_success "OpenSIPS MI URL configured"
    fi
}

test_database_connection() {
    log_info "Testing database connection..."
    cd "$INSTALL_DIR"
    
    # Test connection and capture output
    DB_TEST_OUTPUT=$(php artisan db:show 2>&1)
    DB_TEST_EXIT=$?
    
    if [[ $DB_TEST_EXIT -eq 0 ]]; then
        log_success "Database connection successful"
        echo "$DB_TEST_OUTPUT"
    else
        log_error "Database connection failed!"
        echo "$DB_TEST_OUTPUT"
        log_error ""
        
        # Get DB_HOST from .env to provide specific instructions
        DB_HOST_FROM_ENV=$(grep "^DB_HOST=" "$ENV_FILE" 2>/dev/null | cut -d'=' -f2 | tr -d '"' | tr -d "'" || echo "")
        DB_USER_FROM_ENV=$(grep "^DB_USERNAME=" "$ENV_FILE" 2>/dev/null | cut -d'=' -f2 | tr -d '"' | tr -d "'" || echo "opensips")
        DB_NAME_FROM_ENV=$(grep "^DB_DATABASE=" "$ENV_FILE" 2>/dev/null | cut -d'=' -f2 | tr -d '"' | tr -d "'" || echo "opensips")
        
        # Check for specific error messages
        if echo "$DB_TEST_OUTPUT" | grep -q "Host.*is not allowed to connect"; then
            log_error "ERROR: Database user is not allowed to connect from this host."
            log_error ""
            log_error "This is a MySQL/MariaDB access control issue."
            log_error "The database user needs permission to connect from your host."
            log_error ""
            
            if [[ "$DB_HOST_FROM_ENV" == "127.0.0.1" ]] || [[ "$DB_HOST_FROM_ENV" == "localhost" ]] || [[ -z "$DB_HOST_FROM_ENV" ]]; then
                log_info "You are connecting to localhost. Run these commands on THIS server:"
                log_info ""
                log_info "  mysql -u root -p"
                log_info "  GRANT ALL PRIVILEGES ON ${DB_NAME_FROM_ENV}.* TO '${DB_USER_FROM_ENV}'@'localhost' IDENTIFIED BY 'your_password';"
                log_info "  GRANT ALL PRIVILEGES ON ${DB_NAME_FROM_ENV}.* TO '${DB_USER_FROM_ENV}'@'127.0.0.1' IDENTIFIED BY 'your_password';"
                log_info "  FLUSH PRIVILEGES;"
                log_info "  exit;"
                log_info ""
                log_info "Replace 'your_password' with the actual password from your .env file."
            else
                log_info "You are connecting to remote host: $DB_HOST_FROM_ENV"
                log_info "Run these commands on the database server ($DB_HOST_FROM_ENV):"
                log_info ""
                log_info "  mysql -u root -p"
                log_info "  GRANT ALL PRIVILEGES ON ${DB_NAME_FROM_ENV}.* TO '${DB_USER_FROM_ENV}'@'%' IDENTIFIED BY 'your_password';"
                log_info "  FLUSH PRIVILEGES;"
                log_info "  exit;"
                log_info ""
                log_info "Or if you want to restrict to specific host:"
                log_info "  GRANT ALL PRIVILEGES ON ${DB_NAME_FROM_ENV}.* TO '${DB_USER_FROM_ENV}'@'$(hostname -I | awk '{print $1}')' IDENTIFIED BY 'your_password';"
            fi
        elif echo "$DB_TEST_OUTPUT" | grep -q "Operation timed out\|Connection timed out\|Can't connect to MySQL server"; then
            log_error "ERROR: Connection timeout - Cannot reach MySQL server."
            log_error ""
            log_error "This usually means:"
            log_error "  1. MySQL server is not running"
            log_error "  2. MySQL is not listening on the network interface"
            log_error "  3. Firewall is blocking port 3306"
            log_error "  4. MySQL bind-address is set to 127.0.0.1 only"
            log_error ""
            
            # Check if connecting to own IP address
            SERVER_IP=$(hostname -I | awk '{print $1}' 2>/dev/null || echo "")
            if [[ "$DB_HOST_FROM_ENV" == "$SERVER_IP" ]] || [[ -n "$SERVER_IP" && "$DB_HOST_FROM_ENV" =~ $SERVER_IP ]]; then
                log_info "You are trying to connect to this server's IP address ($DB_HOST_FROM_ENV)."
                log_info "Try using '127.0.0.1' or 'localhost' instead:"
                log_info ""
                log_info "  Edit .env file:"
                log_info "    DB_HOST=127.0.0.1"
                log_info ""
                log_info "Or configure MySQL to listen on all interfaces:"
                log_info "  1. Edit MySQL config: sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf"
                log_info "  2. Change: bind-address = 0.0.0.0  (or comment it out)"
                log_info "  3. Restart MySQL: sudo systemctl restart mysql"
            else
                log_info "Troubleshooting steps:"
                log_info ""
                log_info "1. Check if MySQL is running:"
                log_info "   sudo systemctl status mysql"
                log_info ""
                log_info "2. Check if MySQL is listening on port 3306:"
                log_info "   sudo netstat -tlnp | grep 3306"
                log_info "   or: sudo ss -tlnp | grep 3306"
                log_info ""
                log_info "3. Check firewall rules:"
                log_info "   sudo ufw status"
                log_info "   sudo iptables -L -n | grep 3306"
                log_info ""
                log_info "4. Test connection from command line:"
                log_info "   mysql -h $DB_HOST_FROM_ENV -P 3306 -u $DB_USER_FROM_ENV -p"
                log_info ""
                log_info "5. If connecting to localhost, try using 127.0.0.1 instead of IP:"
                log_info "   Edit .env: DB_HOST=127.0.0.1"
            fi
        fi
        
        log_error ""
        log_error "Please check your database configuration in .env file:"
        log_error "  DB_CONNECTION=mysql"
        log_error "  DB_HOST=..."
        log_error "  DB_PORT=..."
        log_error "  DB_DATABASE=..."
        log_error "  DB_USERNAME=..."
        log_error "  DB_PASSWORD=..."
        log_error ""
        log_info "Common issues:"
        log_info "  - Database server is not running"
        log_info "  - Database credentials are incorrect"
        log_info "  - Database user doesn't have access to the database"
        log_info "  - Database user not allowed to connect from this host (see above)"
        log_info "  - Network connectivity issues (if remote database)"
        log_info "  - Firewall blocking connection"
        log_info ""
        log_info "You can test the connection manually:"
        log_info "  mysql -h <DB_HOST> -P <DB_PORT> -u <DB_USERNAME> -p <DB_DATABASE>"
        exit 1
    fi
}

run_migrations() {
    if [[ "$SKIP_MIGRATIONS" == "true" ]]; then
        log_info "Skipping migrations (--skip-migrations)"
        return
    fi
    
    log_info "Running database migrations..."
    cd "$INSTALL_DIR"
    
    # Run migrations and capture output
    if php artisan migrate --force 2>&1; then
        log_success "Migrations completed"
    else
        MIGRATE_EXIT=$?
        log_error "Migrations failed with exit code: $MIGRATE_EXIT"
        log_error "Please check the error messages above and fix any issues"
        log_info "You can try running migrations manually:"
        log_info "  cd $INSTALL_DIR"
        log_info "  php artisan migrate --force"
        exit 1
    fi
}

create_admin_user() {
    if [[ "$NO_ADMIN_USER" == "true" ]]; then
        log_info "Skipping admin user creation (--no-admin-user)"
        return
    fi
    
    log_info "Checking for existing admin users..."
    cd "$INSTALL_DIR"
    
    # Check if any users exist
    USER_COUNT=$(php artisan tinker --execute="echo App\Models\User::count();" 2>/dev/null || echo "0")
    
    if [[ "$USER_COUNT" -gt 0 ]]; then
        log_info "Found $USER_COUNT existing user(s). Skipping admin user creation."
        log_info "To create additional users, run: php artisan make:filament-user"
        return
    fi
    
    log_info "No users found. Creating admin user..."
    
    # Use provided values or defaults
    if [[ -z "$ADMIN_NAME" ]]; then
        ADMIN_NAME="Admin"
    fi
    
    if [[ -z "$ADMIN_EMAIL" ]]; then
        ADMIN_EMAIL="admin@example.com"
        log_warn "Using default admin email: $ADMIN_EMAIL"
        log_warn "You can change it later or use --admin-email to set it now"
    fi
    
    if [[ -z "$ADMIN_PASSWORD" ]]; then
        # Generate a random password
        ADMIN_PASSWORD=$(openssl rand -base64 12 | tr -d "=+/" | cut -c1-12)
        log_info "Generated random password for admin user"
    fi
    
    # Create user non-interactively using tinker
    log_info "Creating admin user: $ADMIN_NAME ($ADMIN_EMAIL)"
    
    php artisan tinker --execute="
        \$user = new App\Models\User();
        \$user->name = '$ADMIN_NAME';
        \$user->email = '$ADMIN_EMAIL';
        \$user->password = Hash::make('$ADMIN_PASSWORD');
        \$user->save();
        echo 'User created successfully';
    " 2>&1
    
    if [[ $? -eq 0 ]]; then
        log_success "Admin user created successfully"
        echo
        echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
        echo -e "${YELLOW}Admin Credentials:${NC}"
        echo -e "  Email:    ${GREEN}$ADMIN_EMAIL${NC}"
        echo -e "  Password: ${GREEN}$ADMIN_PASSWORD${NC}"
        echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
        echo
        log_warn "Please save these credentials securely!"
        log_warn "You can change the password after logging in"
    else
        log_error "Failed to create admin user"
        log_info "You can create one manually: php artisan make:filament-user"
    fi
}

set_permissions() {
    log_info "Setting directory permissions..."
    cd "$INSTALL_DIR"
    
    # Create storage directories if they don't exist
    mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
    
    # Try to detect web server user
    WEB_USER="www-data"  # Default for nginx/apache
    if command -v nginx &> /dev/null; then
        WEB_USER="www-data"
    elif command -v apache2 &> /dev/null || command -v httpd &> /dev/null; then
        WEB_USER="www-data"  # or apache/httpd depending on distro
    fi
    
    # Ensure parent directories are accessible (critical for nginx to access files)
    INSTALL_PARENT=$(dirname "$INSTALL_DIR")
    if [[ -n "$INSTALL_PARENT" ]] && [[ "$INSTALL_PARENT" != "/" ]]; then
        # Make parent directory readable/executable (needed for nginx to traverse)
        if [[ $EUID -eq 0 ]]; then
            chmod 755 "$INSTALL_PARENT" 2>/dev/null || true
            # Also ensure the install directory itself is accessible
            chmod 755 "$INSTALL_DIR" 2>/dev/null || true
        else
            # If not root, try with sudo
            sudo chmod 755 "$INSTALL_PARENT" 2>/dev/null || true
            sudo chmod 755 "$INSTALL_DIR" 2>/dev/null || true
        fi
    fi
    
    # Set storage and bootstrap/cache permissions
    chmod -R 775 storage bootstrap/cache 2>/dev/null || true
    
    # Ensure storage/logs is writable
    chmod -R 775 storage/logs 2>/dev/null || true
    
    # Set public directory permissions (nginx needs to read these)
    chmod -R 755 public 2>/dev/null || true
    
    # Try to set ownership to web server user if running as root
    if [[ $EUID -eq 0 ]]; then
        if [[ -n "$WEB_USER" ]] && id "$WEB_USER" &> /dev/null; then
            # Set ownership for storage and cache (needs write access)
            chown -R "$WEB_USER:$WEB_USER" storage bootstrap/cache 2>/dev/null || true
            
            # Set ownership for public directory (nginx needs read access)
            chown -R "$WEB_USER:$WEB_USER" public 2>/dev/null || true
            
            log_success "Set ownership to $WEB_USER"
        else
            # If no web server user, make storage world-writable (less secure but works)
            chmod -R 777 storage bootstrap/cache 2>/dev/null || true
            log_warn "No web server user detected. Set storage permissions to 777 (less secure)"
        fi
    else
        # Not running as root - try with sudo for web server user
        if [[ -n "$WEB_USER" ]] && id "$WEB_USER" &> /dev/null 2>/dev/null; then
            sudo chown -R "$WEB_USER:$WEB_USER" storage bootstrap/cache 2>/dev/null || true
            sudo chown -R "$WEB_USER:$WEB_USER" public 2>/dev/null || true
            log_success "Set ownership to $WEB_USER"
        else
            # Make sure current user owns storage
            CURRENT_USER=$(whoami)
            chown -R "$CURRENT_USER:$CURRENT_USER" storage bootstrap/cache 2>/dev/null || true
            # Make public readable by all
            chmod -R 755 public 2>/dev/null || true
        fi
    fi
    
    # Verify www-data can access the public directory (critical check)
    if [[ -n "$WEB_USER" ]] && id "$WEB_USER" &> /dev/null 2>/dev/null; then
        if sudo -u "$WEB_USER" test -r "$INSTALL_DIR/public/index.php" 2>/dev/null; then
            log_success "Verified $WEB_USER can access public directory"
        else
            log_warn "Warning: $WEB_USER may not be able to access public directory"
            log_warn "You may need to check parent directory permissions manually"
        fi
    fi
    
    log_success "Permissions configured"
}

display_summary() {
    log_success "Installation completed successfully!"
    echo
    echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BLUE}Installation Summary${NC}"
    echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo
    echo -e "Admin panel is ready to use!"
    echo
    echo -e "${YELLOW}Next steps:${NC}"
    echo -e "  1. Access the admin panel:"
    if command -v nginx &> /dev/null && systemctl is-active --quiet nginx 2>/dev/null; then
        SERVER_NAME=$(hostname -f 2>/dev/null || hostname 2>/dev/null || echo "localhost")
        echo -e "     ${GREEN}http://${SERVER_NAME}/admin${NC}"
        echo -e "     (nginx is configured and running)"
    else
        echo -e "     Start the development server:"
        echo -e "     ${GREEN}php artisan serve${NC}"
        echo -e "     Then visit: ${GREEN}http://localhost:8000/admin${NC}"
    fi
    echo
    echo -e "  2. If nginx is installed, the application is configured at:"
    echo -e "     ${GREEN}/etc/nginx/sites-available/pbx3sbc-admin${NC}"
    echo
    echo -e "  3. Log in with the admin credentials you just created"
    echo
    echo -e "${YELLOW}Configuration file:${NC} ${ENV_FILE}"
    echo -e "${YELLOW}Documentation:${NC} See README.md and workingdocs/ directory"
    echo
    echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
}

# Main installation flow
main() {
    echo -e "${BLUE}"
    echo "╔══════════════════════════════════════════════════════════╗"
    echo "║     PBX3SBC Admin Panel Installation Script              ║"
    echo "╚══════════════════════════════════════════════════════════╝"
    echo -e "${NC}"
    echo
    
    if [[ "$SKIP_PREREQS" != "true" ]]; then
        check_prerequisites
    else
        log_info "Skipping prerequisite installation (--skip-prereqs)"
        # Still do basic checks without installing
        if ! command -v php &> /dev/null; then
            log_error "PHP is not installed. Remove --skip-prereqs to auto-install."
            exit 1
        fi
        if ! command -v composer &> /dev/null; then
            log_error "Composer is not installed. Remove --skip-prereqs to auto-install."
            exit 1
        fi
    fi
    
    install_dependencies
    setup_environment
    set_permissions  # Set permissions before testing DB connection (needed for logging)
    configure_nginx  # Configure nginx for Laravel
    test_database_connection
    run_migrations
    create_admin_user
    display_summary
}

# Run main function
main
