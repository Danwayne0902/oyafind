#!/usr/bin/env bash
#
# Point a running OyaFind deployment at its public domain.
#
# Usage (on the server, from /opt/oyafind):
#   bash deploy/go-live.sh oyafind.com.ng
#
# Steps:
#   1. Rewrites APP_URL / APP_DOMAIN / MAIL_FROM_ADDRESS in deploy/.env.
#   2. Turns registration on in direct mode.
#   3. Rebuilds the web image (Caddy bakes the site address into the config)
#      and force-recreates the PHP containers so cached config is refreshed.
#   4. Seeds the admin and verifier accounts and allowlists the verifier.
#   5. Waits for the Let's Encrypt certificate, reverting the env on failure.
#
# The previous .env is backed up next to it before anything changes.

set -euo pipefail

DOMAIN="${1:-oyafind.com.ng}"

cd "$(dirname "$0")"

if [ ! -f .env ]; then
    echo "deploy/.env is missing; provision the server first." >&2
    exit 1
fi

backup=".env.backup.$(date +%Y%m%d%H%M%S)"
cp .env "$backup"
echo "backed up deploy/.env to deploy/${backup}"

set_env() {
    key="$1"
    value="$2"

    if grep -qE "^${key}=" .env; then
        sed -i "s|^${key}=.*|${key}=${value}|" .env
    else
        printf '%s=%s\n' "$key" "$value" >> .env
    fi
}

set_env APP_URL "https://${DOMAIN}"
set_env APP_DOMAIN "${DOMAIN}"
set_env MAIL_FROM_ADDRESS "hello@${DOMAIN}"
set_env OYAFIND_ACCOUNT_REGISTRATION "true"
set_env OYAFIND_REGISTRATION_MODE "direct"
set_env OYAFIND_LOCAL_REGISTRATION_WITHOUT_VERIFICATION "false"

echo "building images for ${DOMAIN}"
docker compose up -d --build web app scheduler

echo "waiting for the certificate on https://${DOMAIN}"
serving=0
for attempt in $(seq 1 40); do
    if curl -fsS -o /dev/null --max-time 5 "https://${DOMAIN}/" 2>/dev/null; then
        serving=1
        echo "https://${DOMAIN} is serving"
        break
    fi
    sleep 5
done

if [ "$serving" -ne 1 ]; then
    echo "certificate did not arrive; restoring the previous env" >&2
    cp "$backup" .env
    docker compose up -d --build web app scheduler
    echo "reverted. Inspect with: docker compose logs --tail=80 web" >&2
    exit 1
fi

echo "seeding accounts"
docker compose exec -T app php artisan db:seed --force

verifier_id="$(
    docker compose exec -T app \
        php artisan tinker --execute='echo (string) App\Models\User::query()->where("email", "verifier@oyafind.ng")->value("id");' 2>/dev/null \
        | tr -d '\r' | grep -E '^[0-9]+$' | tail -n 1
)"

if [ -n "${verifier_id}" ]; then
    echo "allowlisting verifier account ${verifier_id}"
    set_env OYAFIND_PUBLIC_VERIFIER_IDS "${verifier_id}"
    docker compose up -d --force-recreate app scheduler >/dev/null
else
    echo "no verifier account was seeded; set OYAFIND_SEED_VERIFIER_PASSWORD in deploy/.env" >&2
fi

docker compose ps
echo
echo "next: docker compose exec -T app php artisan opportunities:ingest"
