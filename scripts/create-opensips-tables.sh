#!/bin/bash

# OpenSIPS Database Tables Creation Script
# This script creates only the database tables needed for the admin panel
# Usage: ./scripts/create-opensips-tables.sh [mysql-user] [mysql-password]
#
# If no credentials provided, it will prompt for them
# Default user: opensips

set -e

# Get script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
SQL_FILE="$SCRIPT_DIR/create-opensips-tables.sql"

# Default values
DB_USER="${1:-opensips}"
DB_PASS="${2:-}"
DB_NAME="opensips"

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}OpenSIPS Database Tables Creation Script${NC}"
echo "=========================================="
echo "Database: $DB_NAME"
echo "User: $DB_USER"
echo ""

# Check if SQL file exists
if [ ! -f "$SQL_FILE" ]; then
    echo "Error: SQL file not found at $SQL_FILE"
    exit 1
fi

# Build mysql command
if [ -z "$DB_PASS" ]; then
    # Prompt for password
    MYSQL_CMD="mysql -u $DB_USER -p $DB_NAME"
else
    # Use password from command line
    MYSQL_CMD="mysql -u $DB_USER -p$DB_PASS $DB_NAME"
fi

# Execute SQL file
echo -e "${BLUE}Creating OpenSIPS tables...${NC}"
if $MYSQL_CMD < "$SQL_FILE"; then
    echo -e "${GREEN}✓ Successfully created OpenSIPS tables${NC}"
    echo ""
    echo "Created tables:"
    echo "  - domain"
    echo "  - dispatcher"
    echo "  - endpoint_locations"
    echo ""
    echo "You can now run Laravel migrations:"
    echo "  php artisan migrate"
else
    echo "Error: Failed to create tables"
    exit 1
fi
