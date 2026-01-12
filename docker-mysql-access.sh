#!/bin/bash
#
# MySQL Docker Container Access Management Script
# Manages LAN/localhost access permissions for the MySQL Docker container
#

set -euo pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
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

log_question() {
    echo -e "${CYAN}[?]${NC} $1"
}

check_docker() {
    if ! command -v docker &> /dev/null; then
        log_error "Docker is not installed."
        exit 1
    fi
    
    # Use docker compose or docker-compose
    if docker compose version &> /dev/null; then
        DOCKER_COMPOSE_CMD="docker compose"
    elif command -v docker-compose &> /dev/null; then
        DOCKER_COMPOSE_CMD="docker-compose"
    else
        log_error "Docker Compose is not installed."
        exit 1
    fi
}

check_container_running() {
    cd "$INSTALL_DIR"
    if ! $DOCKER_COMPOSE_CMD ps mysql 2>/dev/null | grep -q "Up"; then
        log_error "MySQL container is not running."
        log_info "Start it with: docker compose up -d mysql"
        exit 1
    fi
}

get_mysql_root_password() {
    # Try to get from environment or use default
    local root_pw="${MYSQL_ROOT_PASSWORD:-rootpassword}"
    echo "$root_pw"
}

show_current_access() {
    log_info "Current MySQL user access permissions:"
    echo
    
    local root_pw=$(get_mysql_root_password)
    
    cd "$INSTALL_DIR"
    $DOCKER_COMPOSE_CMD exec -T mysql mysql -uroot -p"$root_pw" -e "
        SELECT user, host, 
               CASE 
                   WHEN host = '%' THEN 'All hosts (LAN enabled)'
                   WHEN host = 'localhost' THEN 'Localhost only'
                   ELSE host
               END as access_type
        FROM mysql.user 
        WHERE user = 'opensips'
        ORDER BY host;
    " 2>/dev/null || {
        log_error "Failed to query MySQL. Is the container running?"
        return 1
    }
    
    echo
    log_info "Port binding status:"
    docker ps --filter "name=pbx3sbc-mysql" --format "table {{.Ports}}" 2>/dev/null || {
        log_warn "Could not determine port binding status"
    }
}

enable_lan_access() {
    local root_pw=$(get_mysql_root_password)
    local access_type="$1"  # 'all' or specific IP/subnet
    
    cd "$INSTALL_DIR"
    
    if [ "$access_type" = "all" ]; then
        log_info "Granting access from any host (opensips@%)..."
        $DOCKER_COMPOSE_CMD exec -T mysql mysql -uroot -p"$root_pw" <<EOF
GRANT ALL PRIVILEGES ON opensips.* TO 'opensips'@'%' IDENTIFIED BY 'opensips';
FLUSH PRIVILEGES;
EOF
        log_success "LAN access enabled for all hosts"
    else
        log_info "Granting access from $access_type..."
        $DOCKER_COMPOSE_CMD exec -T mysql mysql -uroot -p"$root_pw" <<EOF
GRANT ALL PRIVILEGES ON opensips.* TO 'opensips'@'$access_type' IDENTIFIED BY 'opensips';
FLUSH PRIVILEGES;
EOF
        log_success "Access granted for $access_type"
    fi
    
    log_warn "Security Note: Make sure your firewall is configured appropriately!"
}

disable_lan_access() {
    local root_pw=$(get_mysql_root_password)
    
    log_warn "This will revoke all remote access, keeping only localhost access."
    log_question "Continue? (y/N): "
    read -r response
    if [[ ! "$response" =~ ^[Yy]$ ]]; then
        log_info "Cancelled."
        return 0
    fi
    
    cd "$INSTALL_DIR"
    
    log_info "Revoking remote access (opensips@%)..."
    $DOCKER_COMPOSE_CMD exec -T mysql mysql -uroot -p"$root_pw" <<EOF
REVOKE ALL PRIVILEGES ON opensips.* FROM 'opensips'@'%';
DROP USER IF EXISTS 'opensips'@'%';
FLUSH PRIVILEGES;
EOF
    
    log_success "Remote access revoked. Only localhost access remains."
}

restrict_port_binding() {
    log_info "To restrict port binding to localhost only, edit docker-compose.yml:"
    echo
    echo -e "Change: ${YELLOW}ports:${NC}"
    echo -e "  ${YELLOW}- \"\${MYSQL_PORT:-3306}:3306\"${NC}"
    echo
    echo -e "To: ${GREEN}ports:${NC}"
    echo -e "  ${GREEN}- \"127.0.0.1:\${MYSQL_PORT:-3306}:3306\"${NC}"
    echo
    log_warn "After changing, run: docker compose down && docker compose up -d mysql"
}

show_help() {
    cat <<EOF
MySQL Docker Container Access Management

Usage: $0 [command]

Commands:
  status          Show current access permissions (default)
  enable [host]   Enable LAN access
                    - enable          Enable from any host (%)
                    - enable <ip>     Enable from specific IP
                    - enable <subnet> Enable from subnet (e.g., 192.168.1.%)
  disable         Disable LAN access (keep localhost only)
  help            Show this help message

Examples:
  $0                    # Show current status
  $0 status             # Show current status
  $0 enable             # Enable access from any host
  $0 enable 192.168.1.100          # Enable from specific IP
  $0 enable 192.168.1.%            # Enable from subnet
  $0 disable            # Disable remote access

Note: This script manages MySQL user permissions. For additional security,
      you may also want to restrict the Docker port binding to localhost
      by editing docker-compose.yml (see 'restrict-port' command).

Environment Variables:
  MYSQL_ROOT_PASSWORD  MySQL root password (default: rootpassword)

EOF
}

main() {
    local command="${1:-status}"
    
    case "$command" in
        status)
            check_docker
            check_container_running
            show_current_access
            ;;
        enable)
            check_docker
            check_container_running
            local host="${2:-all}"
            enable_lan_access "$host"
            echo
            show_current_access
            ;;
        disable)
            check_docker
            check_container_running
            disable_lan_access
            echo
            show_current_access
            ;;
        restrict-port)
            restrict_port_binding
            ;;
        help|--help|-h)
            show_help
            ;;
        *)
            log_error "Unknown command: $command"
            echo
            show_help
            exit 1
            ;;
    esac
}

# Run main function
main "$@"
