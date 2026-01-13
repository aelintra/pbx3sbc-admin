#!/bin/bash
#
# PBX3SBC Admin Panel Installation Script
# Installs and configures Laravel + Filament admin panel
#
# Usage: ./install.sh [--skip-deps] [--skip-prereqs] [--skip-migrations] [--db-host HOST] [--db-port PORT] [--db-user USER] [--db-password PASSWORD] [--db-name NAME] [--no-admin-user]
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
        *)
            echo -e "${RED}Unknown option: $1${NC}"
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
        
        # Try to install PHP 8.2 first, fall back to available version
        if $PKG_INSTALL php8.2-cli php8.2-common 2>&1 >/dev/null; then
            log_success "PHP 8.2 installed"
        else
            # PHP 8.2 not available, try 8.1
            log_info "PHP 8.2 not available, trying PHP 8.1..."
            if $PKG_INSTALL php8.1-cli php8.1-common 2>&1 >/dev/null; then
                log_success "PHP 8.1 installed"
            else
                # Install default PHP version
                log_info "Installing default PHP version..."
                $PKG_INSTALL php-cli php-common
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
    
    for ext in "${REQUIRED_EXTENSIONS[@]}"; do
        if ! php -m | grep -q "^${ext}$"; then
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
        PACKAGES_TO_INSTALL=()
        NEED_MYSQL=false
        for ext in "${MISSING_EXTENSIONS[@]}"; do
            case $ext in
                pdo_mysql) NEED_MYSQL=true ;;
                pdo) ;;  # pdo is included in php-common, skip
                mbstring) PACKAGES_TO_INSTALL+=("php${PHP_MAJOR}.${PHP_MINOR}-mbstring") ;;
                xml) PACKAGES_TO_INSTALL+=("php${PHP_MAJOR}.${PHP_MINOR}-xml") ;;
                curl) PACKAGES_TO_INSTALL+=("php${PHP_MAJOR}.${PHP_MINOR}-curl") ;;
                zip) PACKAGES_TO_INSTALL+=("php${PHP_MAJOR}.${PHP_MINOR}-zip") ;;
                bcmath) PACKAGES_TO_INSTALL+=("php${PHP_MAJOR}.${PHP_MINOR}-bcmath") ;;
                intl) PACKAGES_TO_INSTALL+=("php${PHP_MAJOR}.${PHP_MINOR}-intl") ;;
            esac
        done
        
        # Add mysql package if pdo_mysql is missing
        if [[ "$NEED_MYSQL" == "true" ]]; then
            PACKAGES_TO_INSTALL+=("php${PHP_MAJOR}.${PHP_MINOR}-mysql")
        fi
        
        # Remove duplicates and install
        UNIQUE_PACKAGES=($(printf '%s\n' "${PACKAGES_TO_INSTALL[@]}" | sort -u))
        $PKG_INSTALL "${UNIQUE_PACKAGES[@]}"
        
    elif [[ "$PKG_MANAGER" == "yum" ]] || [[ "$PKG_MANAGER" == "dnf" ]]; then
        # RHEL/CentOS/Fedora
        $PKG_INSTALL php-mysqlnd php-xml php-mbstring php-curl php-zip php-bcmath php-intl
    else
        log_error "Unable to automatically install PHP extensions. Unknown package manager."
        log_error "Please install the missing extensions manually and run the installer again."
        exit 1
    fi
    
    # Verify extensions are now available
    STILL_MISSING=()
    for ext in "${MISSING_EXTENSIONS[@]}"; do
        if ! php -m | grep -q "^${ext}$"; then
            STILL_MISSING+=("$ext")
        fi
    done
    
    if [[ ${#STILL_MISSING[@]} -gt 0 ]]; then
        log_error "Failed to install PHP extensions: ${STILL_MISSING[*]}"
        log_error "Please install them manually and run the installer again."
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

check_prerequisites() {
    log_info "Checking and installing prerequisites..."
    
    detect_os
    check_root_access
    install_php
    install_php_extensions
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
    
    if [[ -f "composer.lock" ]]; then
        composer install --no-interaction --prefer-dist --optimize-autoloader
    else
        composer update --no-interaction --prefer-dist --optimize-autoloader
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
    
    # Prompt for database details if not provided via arguments
    if [[ -z "$DB_HOST" ]]; then
        echo -n "Database host [127.0.0.1]: "
        read -r input_db_host
        DB_HOST="${input_db_host:-127.0.0.1}"
    fi
    
    if [[ -z "$DB_USER" ]]; then
        echo -n "Database username [opensips]: "
        read -r input_db_user
        DB_USER="${input_db_user:-opensips}"
    fi
    
    if [[ -z "$DB_PASSWORD" ]]; then
        echo -n "Database password: "
        read -rs input_db_password
        echo
        DB_PASSWORD="$input_db_password"
    fi
    
    if [[ -z "$DB_NAME" ]]; then
        echo -n "Database name [opensips]: "
        read -r input_db_name
        DB_NAME="${input_db_name:-opensips}"
    fi
    
    if [[ -z "$DB_PORT" ]]; then
        echo -n "Database port [3306]: "
        read -r input_db_port
        DB_PORT="${input_db_port:-3306}"
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
    
    if php artisan db:show &> /dev/null; then
        log_success "Database connection successful"
    else
        log_error "Database connection failed. Please check your database configuration."
        log_info "Verify:"
        log_info "  - Database server is running"
        log_info "  - Database credentials are correct"
        log_info "  - Database user has access to the database"
        log_info "  - Network connectivity to database server (if remote)"
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
    
    php artisan migrate --force
    
    log_success "Migrations completed"
}

create_admin_user() {
    if [[ "$NO_ADMIN_USER" == "true" ]]; then
        log_info "Skipping admin user creation (--no-admin-user)"
        return
    fi
    
    log_info "Creating admin user..."
    log_warn "You will be prompted to enter admin user details"
    cd "$INSTALL_DIR"
    
    php artisan make:filament-user
    
    log_success "Admin user creation completed"
}

set_permissions() {
    log_info "Setting directory permissions..."
    cd "$INSTALL_DIR"
    
    # Set storage and bootstrap/cache permissions
    chmod -R 775 storage bootstrap/cache 2>/dev/null || true
    
    # Try to set ownership to web server user if running as root
    if [[ $EUID -eq 0 ]]; then
        # Try to detect web server user
        if command -v nginx &> /dev/null; then
            WEB_USER="www-data"
        elif command -v apache2 &> /dev/null || command -v httpd &> /dev/null; then
            WEB_USER="www-data"  # or apache/httpd depending on distro
        else
            WEB_USER=""
        fi
        
        if [[ -n "$WEB_USER" ]] && id "$WEB_USER" &> /dev/null; then
            chown -R "$WEB_USER:$WEB_USER" storage bootstrap/cache 2>/dev/null || true
            log_success "Set ownership to $WEB_USER"
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
    echo -e "  1. Start the development server:"
    echo -e "     ${GREEN}php artisan serve${NC}"
    echo -e "     Then visit: ${GREEN}http://localhost:8000/admin${NC}"
    echo
    echo -e "  2. Or configure your web server (nginx/apache) to serve"
    echo -e "     the application from the ${GREEN}public${NC} directory"
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
    test_database_connection
    run_migrations
    create_admin_user
    set_permissions
    display_summary
}

# Run main function
main
