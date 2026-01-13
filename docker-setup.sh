#!/bin/bash
#
# Docker MySQL Setup Script for PBX3SBC
# Sets up and initializes MySQL Docker container with OpenSIPS tables
#

set -euo pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

INSTALL_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

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

check_docker() {
    if ! command -v docker &> /dev/null; then
        log_error "Docker is not installed. Please install Docker first."
        exit 1
    fi
    
    if ! command -v docker-compose &> /dev/null && ! docker compose version &> /dev/null; then
        log_error "Docker Compose is not installed. Please install Docker Compose."
        exit 1
    fi
    
    log_success "Docker is installed"
}

start_mysql() {
    log_info "Starting MySQL container..."
    cd "$INSTALL_DIR"
    
    # Use docker compose or docker-compose
    if docker compose version &> /dev/null; then
        DOCKER_COMPOSE_CMD="docker compose"
    else
        DOCKER_COMPOSE_CMD="docker-compose"
    fi
    
    $DOCKER_COMPOSE_CMD up -d mysql
    
    log_info "Waiting for MySQL to be ready..."
    sleep 5
    
    # Wait for MySQL to be healthy
    local max_attempts=30
    local attempt=0
    while [ $attempt -lt $max_attempts ]; do
        if $DOCKER_COMPOSE_CMD exec -T mysql mysqladmin ping -h localhost -u root -prootpassword &> /dev/null; then
            log_success "MySQL is ready"
            return 0
        fi
        attempt=$((attempt + 1))
        sleep 2
    done
    
    log_error "MySQL did not become ready in time"
    return 1
}

initialize_database() {
    log_info "Checking database status..."
    cd "$INSTALL_DIR"
    
    # Use docker compose or docker-compose
    if docker compose version &> /dev/null; then
        DOCKER_COMPOSE_CMD="docker compose"
    else
        DOCKER_COMPOSE_CMD="docker-compose"
    fi
    
    # Check if OpenSIPS tables already exist
    if $DOCKER_COMPOSE_CMD exec -T mysql mysql -u opensips -popensips opensips -e "SHOW TABLES LIKE 'domain';" 2>/dev/null | grep -q "domain"; then
        log_success "OpenSIPS tables already exist in database"
        return 0
    fi
    
    log_warn "OpenSIPS tables (domain, dispatcher, endpoint_locations) not found in database"
    echo
    echo -e "${YELLOW}IMPORTANT:${NC} OpenSIPS database tables must be created using the pbx3sbc repository."
    echo -e "This admin panel repository does not create OpenSIPS tables."
    echo
    echo -e "To create OpenSIPS tables:"
    echo -e "  1. Use pbx3sbc repository: ${GREEN}cd pbx3sbc && sudo ./scripts/init-database.sh${NC}"
    echo -e "  2. Or manually run the SQL from pbx3sbc/scripts/init-database.sh"
    echo
    echo -e "The admin panel only creates application tables (users, etc.) via Laravel migrations."
    echo
}

display_info() {
    log_success "Docker MySQL setup complete!"
    echo
    echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BLUE}Docker MySQL Information${NC}"
    echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo
    echo -e "Container: ${GREEN}pbx3sbc-mysql${NC}"
    echo -e "Database: ${GREEN}opensips${NC}"
    echo -e "User: ${GREEN}opensips${NC}"
    echo -e "Password: ${GREEN}opensips${NC}"
    echo -e "Port: ${GREEN}3306${NC}"
    echo
    echo -e "${YELLOW}Connection Details:${NC}"
    echo -e "  Host: ${GREEN}127.0.0.1${NC} (from host)"
    echo -e "  Host: ${GREEN}mysql${NC} (from other Docker containers)"
    echo -e "  Port: ${GREEN}3306${NC}"
    echo
    echo -e "${YELLOW}Useful Commands:${NC}"
    echo -e "  Start: ${GREEN}docker compose up -d mysql${NC}"
    echo -e "  Stop: ${GREEN}docker compose down${NC}"
    echo -e "  Logs: ${GREEN}docker compose logs -f mysql${NC}"
    echo -e "  Connect: ${GREEN}docker compose exec mysql mysql -u opensips -popensips opensips${NC}"
    echo
    echo -e "${YELLOW}For Admin Panel .env file:${NC}"
    echo -e "  ${GREEN}DB_HOST=127.0.0.1${NC}"
    echo -e "  ${GREEN}DB_PORT=3306${NC}"
    echo -e "  ${GREEN}DB_DATABASE=opensips${NC}"
    echo -e "  ${GREEN}DB_USERNAME=opensips${NC}"
    echo -e "  ${GREEN}DB_PASSWORD=opensips${NC}"
    echo
    echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
}

main() {
    echo -e "${BLUE}"
    echo "╔══════════════════════════════════════════════════════════╗"
    echo "║     PBX3SBC Docker MySQL Setup Script                    ║"
    echo "╚══════════════════════════════════════════════════════════╝"
    echo -e "${NC}"
    echo
    
    check_docker
    start_mysql
    initialize_database
    display_info
}

main
