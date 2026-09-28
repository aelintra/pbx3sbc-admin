#!/bin/bash
# Greenfield SBC FO install — edge + admin from main
set -euo pipefail
exec > >(tee /tmp/sbcfo-install.log) 2>&1

ROLE="${1:?fo1|fo2}"
ADVERTISED_IP="${2:?eip}"
SERVER_NAME="${3:?fqdn}"
DB_PASS="${4:?}"
ADMIN_EMAIL="${5:?}"
ADMIN_PASS="${6:?}"
LE_EMAIL="${7:-}"
DO_LE="${8:-no}"

echo "=== ${ROLE} start $(date -u) ==="
sudo hostnamectl set-hostname "$ROLE"
sudo apt-get update -qq
sudo DEBIAN_FRONTEND=noninteractive apt-get install -y -qq git curl

cd ~
rm -rf pbx3sbc pbx3sbc-admin
git clone --depth 1 --branch main https://github.com/pbx3-oss/pbx3sbc.git
git clone --depth 1 --branch main https://github.com/pbx3-oss/pbx3sbc-admin.git

cd ~/pbx3sbc
# Non-interactive DB init: feed y to reinitialize prompt
printf 'y\n' | sudo ./install.sh --advertised-ip "$ADVERTISED_IP" --db-password "$DB_PASS"

cd ~/pbx3sbc-admin
ADMIN_ARGS=(
  --server-name "$SERVER_NAME"
  --db-host localhost
  --db-name opensips
  --db-user opensips
  --db-password "$DB_PASS"
  --opensips-mi-url http://127.0.0.1:8888/mi
  --admin-name Admin
  --admin-email "$ADMIN_EMAIL"
  --admin-password "$ADMIN_PASS"
)
if [[ "$DO_LE" == "yes" ]]; then
  ADMIN_ARGS+=(--letsencrypt --email "$LE_EMAIL")
fi
sudo ./install.sh "${ADMIN_ARGS[@]}"

echo "=== services ==="
systemctl is-active opensips mariadb nginx fail2ban || true
grep advertised_address /etc/opensips/opensips.cfg | head -3 || true
grep '^APP_URL=' ~/pbx3sbc-admin/.env || true
echo "=== ${ROLE} done $(date -u) ==="
