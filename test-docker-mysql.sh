#!/bin/bash
#
# Test Docker MySQL Connection Script
# Run this after starting Docker Desktop
#

set -euo pipefail

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}Testing Docker MySQL Setup${NC}"
echo "=================================="
echo

# Check if Docker is running
if ! docker info >/dev/null 2>&1; then
    echo -e "${RED}ERROR: Docker is not running!${NC}"
    echo "Please start Docker Desktop and try again."
    exit 1
fi

echo -e "${GREEN}✓${NC} Docker is running"
echo

cd "$(dirname "$0")"

# Check if container exists and is running
if docker compose ps mysql 2>/dev/null | grep -q "Up"; then
    echo -e "${GREEN}✓${NC} MySQL container is running"
elif docker compose ps mysql 2>/dev/null | grep -q "Exit"; then
    echo -e "${YELLOW}⚠${NC} MySQL container exists but is stopped. Starting..."
    docker compose up -d mysql
    echo "Waiting for MySQL to initialize..."
    sleep 15
else
    echo -e "${YELLOW}⚠${NC} MySQL container not found. Starting..."
    docker compose up -d mysql
    echo "Waiting for MySQL to initialize (this may take 30-60 seconds)..."
    sleep 20
fi

# Wait for MySQL to be ready
echo "Waiting for MySQL to be ready..."
for i in {1..30}; do
    if docker compose exec -T mysql mysqladmin ping -h localhost -uroot -prootpassword >/dev/null 2>&1; then
        echo -e "${GREEN}✓${NC} MySQL is ready!"
        break
    fi
    if [ $i -eq 30 ]; then
        echo -e "${RED}✗${NC} MySQL did not become ready in time"
        echo "Check logs with: docker compose logs mysql"
        exit 1
    fi
    sleep 2
done

echo
echo -e "${BLUE}Testing Connections:${NC}"
echo "-------------------"

# Test 1: Version and databases
echo -e "\n${BLUE}1. MySQL Version and Databases:${NC}"
docker compose exec -T mysql mysql -uroot -prootpassword -e "SELECT VERSION() as mysql_version;" 2>/dev/null || true
docker compose exec -T mysql mysql -uroot -prootpassword -e "SHOW DATABASES;" 2>/dev/null || true

# Test 2: Check opensips database and tables
echo -e "\n${BLUE}2. OpenSIPS Database Tables:${NC}"
TABLES=$(docker compose exec -T mysql mysql -uopensips -popensips opensips -e "SHOW TABLES;" 2>/dev/null | tail -n +2)
if [ -z "$TABLES" ]; then
    echo -e "${YELLOW}⚠${NC} No tables found. Database may not be initialized yet."
    echo "   Tables should be created automatically on first startup."
    echo "   If this persists, check: docker compose logs mysql"
else
    echo -e "${GREEN}✓${NC} Tables found:"
    echo "$TABLES" | sed 's/^/   - /'
fi

# Test 3: Check user permissions
echo -e "\n${BLUE}3. MySQL User Permissions:${NC}"
docker compose exec -T mysql mysql -uroot -prootpassword -e "SELECT user, host FROM mysql.user WHERE user='opensips';" 2>/dev/null || true

# Test 4: Test connection from container
echo -e "\n${BLUE}4. Connection Test (from container):${NC}"
if docker compose exec -T mysql mysql -uopensips -popensips opensips -e "SELECT 'Connection successful!' as status, DATABASE() as current_db;" 2>/dev/null; then
    echo -e "${GREEN}✓${NC} Connection successful from container"
else
    echo -e "${RED}✗${NC} Connection failed from container"
fi

# Test 5: Port binding
echo -e "\n${BLUE}5. Port Binding:${NC}"
PORT_BINDING=$(docker ps --filter "name=pbx3sbc-mysql" --format "{{.Ports}}" 2>/dev/null | grep -o "3306/tcp" || echo "Not found")
if echo "$PORT_BINDING" | grep -q "3306/tcp"; then
    echo -e "${GREEN}✓${NC} Port 3306 is bound"
    docker ps --filter "name=pbx3sbc-mysql" --format "   Ports: {{.Ports}}"
else
    echo -e "${YELLOW}⚠${NC} Could not determine port binding"
fi

echo
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}Test Summary${NC}"
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo
echo "To connect from host machine:"
echo "  mysql -h127.0.0.1 -P3306 -uopensips -popensips opensips"
echo
echo "To connect from container:"
echo "  docker compose exec mysql mysql -uopensips -popensips opensips"
echo
echo "To check logs:"
echo "  docker compose logs mysql"
echo
echo "To check access permissions:"
echo "  ./docker-mysql-access.sh status"
echo
