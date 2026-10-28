#!/usr/bin/env bash
#
# One-time preparation of an Oracle Cloud Always Free VM for OyaFind.
#
# Tested target: Ubuntu 24.04 on VM.Standard.A1.Flex (arm64).
# Idempotent: safe to re-run after a partial failure.
#
#   sudo APP_DOMAIN=oyafind.com.ng \
#        ACME_EMAIL=you@example.com \
#        OYAFIND_REPO=git@github.com:Danwayne0902/oyafind.git \
#        ./provision-oracle.sh
#
# It does NOT do the two things only you can do in the Oracle console:
#   - open TCP 80 and 443 in the subnet's security list
#   - point DNS at the instance
#
set -euo pipefail

APP_DOMAIN="${APP_DOMAIN:-oyafind.com.ng}"
ACME_EMAIL="${ACME_EMAIL:-admin@${APP_DOMAIN}}"
APP_DIR="${OYAFIND_DIR:-/opt/oyafind}"
REPO_URL="${OYAFIND_REPO:-}"

log() { printf '\n==> %s\n' "$*"; }
warn() { printf '[warn] %s\n' "$*"; }
die() { printf '[error] %s\n' "$*" >&2; exit 1; }

[ "$(id -u)" -eq 0 ] || die "run this with sudo"
command -v apt-get >/dev/null 2>&1 || die "this script targets Debian/Ubuntu"

###############################################################################
# 1. Base packages
###############################################################################
log "installing base packages"
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq --no-install-recommends \
    -o Dpkg::Options::="--force-confold" \
    ca-certificates \
    curl \
    git \
    gnupg \
    unzip \
    rsync \
    openssl \
    iptables-persistent \
    >/dev/null

###############################################################################
# 2. Docker
###############################################################################
if ! command -v docker >/dev/null 2>&1; then
    log "installing Docker"
    curl -fsSL https://get.docker.com | sh >/dev/null
else
    log "Docker already installed"
fi

systemctl enable --now docker >/dev/null 2>&1 || true
docker compose version >/dev/null 2>&1 || die "the docker compose plugin is missing"

###############################################################################
# 3. Host firewall
#
# Oracle's Ubuntu images ship an iptables ruleset that rejects everything
# except SSH. Inserting at position 1 puts the rule ahead of that reject, and
# leaves the SSH rule untouched.
###############################################################################
log "opening ports 80 and 443"
open_port() {
    local port="$1"
    if ! iptables -C INPUT -p tcp --dport "$port" -j ACCEPT 2>/dev/null; then
        iptables -I INPUT 1 -p tcp --dport "$port" -j ACCEPT
    fi
}

open_port 80
open_port 443
netfilter-persistent save >/dev/null

###############################################################################
# 4. Code
###############################################################################
mkdir -p "$APP_DIR"

if [ ! -f "$APP_DIR/artisan" ]; then
    if [ -n "$REPO_URL" ]; then
        log "cloning $REPO_URL into $APP_DIR"
        git clone --depth 1 "$REPO_URL" "$APP_DIR"
    else
        die "no application found at $APP_DIR. Clone the repository there first, or set OYAFIND_REPO."
    fi
else
    log "using existing checkout at $APP_DIR"
fi

###############################################################################
# 5. Environment file
###############################################################################
ENV_FILE="$APP_DIR/deploy/.env"
ADMIN_PASSWORD=""

if [ -f "$ENV_FILE" ]; then
    warn "keeping the existing $ENV_FILE"
else
    log "creating $ENV_FILE with generated secrets"

    APP_KEY_VALUE="base64:$(openssl rand -base64 32)"
    DB_PASSWORD_VALUE="$(openssl rand -hex 24)"
    DB_ROOT_PASSWORD_VALUE="$(openssl rand -hex 24)"
    ADMIN_PASSWORD="$(openssl rand -base64 18 | tr -d '/+=' | cut -c1-20)"

    cp "$APP_DIR/deploy/.env.production.example" "$ENV_FILE"

    sed -i \
        -e "s|^APP_KEY=.*|APP_KEY=${APP_KEY_VALUE}|" \
        -e "s|^APP_URL=.*|APP_URL=https://${APP_DOMAIN}|" \
        -e "s|^APP_DOMAIN=.*|APP_DOMAIN=${APP_DOMAIN}|" \
        -e "s|^ACME_EMAIL=.*|ACME_EMAIL=${ACME_EMAIL}|" \
        -e "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD_VALUE}|" \
        -e "s|^DB_ROOT_PASSWORD=.*|DB_ROOT_PASSWORD=${DB_ROOT_PASSWORD_VALUE}|" \
        -e "s|^OYAFIND_SEED_ADMIN_PASSWORD=.*|OYAFIND_SEED_ADMIN_PASSWORD=${ADMIN_PASSWORD}|" \
        -e "s|^MAIL_FROM_ADDRESS=.*|MAIL_FROM_ADDRESS=\"hello@${APP_DOMAIN}\"|" \
        "$ENV_FILE"

    chmod 600 "$ENV_FILE"
fi

###############################################################################
# 6. DNS sanity check
###############################################################################
PUBLIC_IP="$(curl -fsS --max-time 5 https://api.ipify.org 2>/dev/null || true)"
RESOLVED_IP="$(getent hosts "$APP_DOMAIN" 2>/dev/null | awk 'NR==1{print $1}' || true)"

if [ -n "$PUBLIC_IP" ] && [ "$RESOLVED_IP" != "$PUBLIC_IP" ]; then
    warn "DNS for $APP_DOMAIN points at '${RESOLVED_IP:-nothing}', this VM is $PUBLIC_IP"
    warn "Caddy will keep retrying the certificate until the A record is correct"
fi

###############################################################################
# 7. Build and start
###############################################################################
log "building images and starting the stack"
cd "$APP_DIR/deploy"
# The app container migrates on boot. Hand that job to this script for the
# first start instead, so two `php artisan migrate` runs never race to create
# the same table (which fails with "table already exists" and aborts the rest
# of the setup).
RUN_MIGRATIONS=false docker compose up -d --build

log "waiting for the application container"
for _ in $(seq 1 60); do
    if docker compose exec -T app php artisan migrate:status >/dev/null 2>&1; then
        break
    fi
    sleep 3
done

log "applying migrations and seeding reference data"
docker compose exec -T app php artisan migrate --force --no-interaction
docker compose exec -T app php artisan db:seed --force --no-interaction

###############################################################################
# 8. Nightly database backup
###############################################################################
log "installing the nightly backup job"
install -m 0755 "$APP_DIR/deploy/backup.sh" /usr/local/bin/oyafind-backup
printf '15 2 * * * root OYAFIND_DIR=%s /usr/local/bin/oyafind-backup >/var/log/oyafind-backup.log 2>&1\n' "$APP_DIR" \
    > /etc/cron.d/oyafind-backup
chmod 0644 /etc/cron.d/oyafind-backup

###############################################################################
# 9. What is left for a human
###############################################################################
cat <<SUMMARY

OyaFind is running on this VM.

  Application : $APP_DIR
  Environment : $ENV_FILE (mode 600)
  Domain      : https://$APP_DOMAIN

Four things still need you:

  1. DNS
     Add an A record:  $APP_DOMAIN  ->  ${PUBLIC_IP:-<this VM's public IP>}
     Optional: CNAME  www.$APP_DOMAIN  ->  $APP_DOMAIN

  2. Oracle console
     Networking -> VCN -> Security Lists -> Default Security List
     Add ingress rules:
       TCP 80   from 0.0.0.0/0
       TCP 443  from 0.0.0.0/0
     The firewall on this VM is already open; this is the other layer.

  3. Mail
     Set MAIL_USERNAME and MAIL_PASSWORD in $ENV_FILE, then:
       cd $APP_DIR/deploy && docker compose up -d --force-recreate

  4. Staff accounts
     Create a second staff user and put both ids in the publication policy
     before any listing can publish. See deploy/README.md.
$( if [ -n "$ADMIN_PASSWORD" ]; then
       printf '\n  Seeded admin password: %s\n  Change it after the first sign-in.\n' "$ADMIN_PASSWORD"
   else
       printf '\n  Admin password: keep using the one already in %s\n  (OYAFIND_SEED_ADMIN_PASSWORD). Re-running this script never rotates it.\n' "$ENV_FILE"
   fi )

Check status with:
  cd $APP_DIR/deploy && docker compose ps
  curl -I https://$APP_DOMAIN/up

SUMMARY
