#!/bin/bash
#
# PBX3SBC Admin Panel Installation Script
# Installs and configures Laravel + Filament admin panel
#
# Usage: ./install.sh [--skip-deps] [--skip-migrations] [--db-host HOST] [--db-user USER] [--db-password PASSWORD] [--db-name NAME] [--no-admin-user]
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
DB_HOST=""
DB_USER=""
DB_PASSWORD=""
DB_NAME=""
DB_PORT="3306"
OPENSIPS_MI_URL=""

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --skip-deps)
            SKIP_DEPS=true
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

check_prerequisites() {
    log_info "Checking prerequisites..."
    
    # Check PHP
    if ! command -v php &> /dev/null; then
        log_error "PHP is not installed. Please install PHP 8.2 or higher."
        exit 1
    fi
    
    PHP_VERSION=$(php -r 'echo PHP_VERSION;' | cut -d. -f1,2)
    PHP_MAJOR=$(echo "$PHP_VERSION" | cut -d. -f1)
    PHP_MINOR=$(echo "$PHP_VERSION" | cut -d. -f2)
    
    if [[ $PHP_MAJOR -lt 8 ]] || [[ $PHP_MAJOR -eq 8 && $PHP_MINOR -lt 2 ]]; then
        log_error "PHP 8.2 or higher is required. Found: $PHP_VERSION"
        exit 1
    fi
    
    log_success "PHP version: $PHP_VERSION"
    
    # Check Composer
    if ! command -v composer &> /dev/null; then
        log_error "Composer is not installed. Please install Composer."
        exit 1
    fi
    
    COMPOSER_VERSION=$(composer --version | head -n1)
    log_success "Composer: $COMPOSER_VERSION"
    
    # Check required PHP extensions
    REQUIRED_EXTENSIONS=("pdo" "pdo_mysql" "mbstring" "xml" "curl" "zip" "bcmath" "intl")
    MISSING_EXTENSIONS=()
    
    for ext in "${REQUIRED_EXTENSIONS[@]}"; do
        if ! php -m | grep -q "^${ext}$"; then
            MISSING_EXTENSIONS+=("$ext")
        fi
    done
    
    if [[ ${#MISSING_EXTENSIONS[@]} -gt 0 ]]; then
        log_error "Missing required PHP extensions: ${MISSING_EXTENSIONS[*]}"
        log_info "Please install the missing extensions. Example for Ubuntu/Debian:"
        log_info "  sudo apt-get install php${PHP_MAJOR}.${PHP_MINOR}-mysql php${PHP_MAJOR}.${PHP_MINOR}-xml php${PHP_MAJOR}.${PHP_MINOR}-mbstring php${PHP_MAJOR}.${PHP_MINOR}-curl php${PHP_MAJOR}.${PHP_MINOR}-zip php${PHP_MAJOR}.${PHP_MINOR}-bcmath php${PHP_MAJOR}.${PHP_MINOR}-intl"
        exit 1
    fi
    
    log_success "All required PHP extensions are installed"
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
    
    check_prerequisites
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
