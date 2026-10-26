#!/bin/sh
#
# Container startup for OyaFind.
#
# The same image runs PHP-FPM and the scheduler. Only the web-facing role runs
# migrations and warms the caches; the scheduler just waits for the database.
#
# Knobs (all read from the environment):
#   WAIT_FOR_DATABASE   block until MySQL accepts connections   (default true)
#   RUN_MIGRATIONS      apply pending migrations on boot        (default true)
#   CACHE_APP_CONFIG    cache config/views and discover packages (default true)

set -eu

log() {
    printf '[oyafind] %s\n' "$*"
}

wait_for_database() {
    php -r '
        $host = getenv("DB_HOST") ?: "127.0.0.1";
        $port = getenv("DB_PORT") ?: "3306";
        $name = getenv("DB_DATABASE") ?: "oyafind";
        $user = getenv("DB_USERNAME") ?: "root";
        $pass = getenv("DB_PASSWORD") ?: "";
        $dsn = sprintf("mysql:host=%s;port=%s;dbname=%s", $host, $port, $name);

        for ($attempt = 1; $attempt <= 60; $attempt++) {
            try {
                new PDO($dsn, $user, $pass, [PDO::ATTR_TIMEOUT => 3]);
                exit(0);
            } catch (Throwable $error) {
                fwrite(STDERR, sprintf("waiting for database (%d/60)\n", $attempt));
                sleep(2);
            }
        }

        fwrite(STDERR, "database unreachable after 120 seconds\n");
        exit(1);
    '
}

if [ "${WAIT_FOR_DATABASE:-true}" = "true" ]; then
    log "waiting for the database"
    wait_for_database
fi

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    log "applying migrations"
    php artisan migrate --force --no-interaction
fi

if [ "${CACHE_APP_CONFIG:-true}" = "true" ]; then
    log "caching configuration and views"
    php artisan package:discover --ansi
    php artisan config:cache
    php artisan view:cache
fi

# Cache warming runs as root, so hand the writable trees back to the worker
# user before PHP-FPM drops privileges.
if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
fi

log "starting: $*"
exec "$@"
