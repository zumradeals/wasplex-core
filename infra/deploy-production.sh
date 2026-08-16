#!/usr/bin/env bash

set -Eeuo pipefail
umask 0022

REPO="${WASPLEX_REPO:-/root/wasplex-core}"
APP="${WASPLEX_APP:-$REPO/apps/platform}"
EXPECTED_SHA="${1:-${EXPECTED_SHA:-}}"
PHP_BIN="${PHP_BIN:-php8.4}"
HEALTH_URL="${HEALTH_URL:-https://wasplex.com}"

site_was_put_down=0

restore_site() {
    if [[ "$site_was_put_down" -eq 1 && -d "$APP" ]]; then
        cd "$APP" || return 0
        "$PHP_BIN" artisan up >/dev/null 2>&1 || true
    fi
}

on_error() {
    local exit_code=$?
    echo
    echo "ERREUR : déploiement interrompu (code $exit_code)."
    if [[ -f "$APP/storage/logs/wasplex.json.log" ]]; then
        echo "Dernières erreurs Wasplex :"
        tail -n 20 "$APP/storage/logs/wasplex.json.log" || true
    fi
    exit "$exit_code"
}

trap restore_site EXIT
trap on_error ERR

echo "=== 1. Maintenance ==="
cd "$APP"
"$PHP_BIN" artisan down || true
site_was_put_down=1

echo "=== 2. Synchronisation Git ==="
cd "$REPO"
git fetch origin main
git checkout main
git merge --ff-only origin/main

CURRENT_SHA="$(git rev-parse HEAD)"
echo "Commit : $CURRENT_SHA"
if [[ -n "$EXPECTED_SHA" && "$CURRENT_SHA" != "$EXPECTED_SHA" ]]; then
    echo "Commit inattendu. Attendu : $EXPECTED_SHA"
    false
fi

echo "=== 3. Dépendances et build ==="
cd "$APP"
COMPOSER_ALLOW_SUPERUSER=1 composer install \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader \
    --no-interaction
npm ci
npm run build

echo "=== 4. Migration et nettoyage ==="
"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan optimize:clear

echo "=== 5. Permissions code PHP-FPM ==="
for path in app config database routes resources; do
    chgrp -R www-data "$path"
    find "$path" -type d -exec chmod 750 {} +
    find "$path" -type f -exec chmod 640 {} +
done

chgrp -R www-data vendor
find vendor -type d -exec chmod 750 {} +
find vendor -type f -exec chmod g+r,o-rwx {} +

chgrp www-data bootstrap
chmod 750 bootstrap
find bootstrap -maxdepth 1 -type f -exec chgrp www-data {} +
find bootstrap -maxdepth 1 -type f -exec chmod 640 {} +

chgrp -R www-data public
find public -type d -exec chmod 755 {} +
find public -type f -exec chmod 644 {} +

if [[ -f .env ]]; then
    chown root:www-data .env
    chmod 640 .env
fi

echo "=== 6. Caches Laravel ==="
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache

chown -R root:www-data storage bootstrap/cache
find storage bootstrap/cache -type d -exec chmod 2770 {} +
find storage bootstrap/cache -type f -exec chmod 660 {} +

echo "=== 7. Processus longue durée ==="
"$PHP_BIN" artisan queue:restart
if systemctl list-unit-files --type=service | grep -q '^wasplex-reverb\.service'; then
    systemctl restart wasplex-reverb.service
    systemctl is-active --quiet wasplex-reverb.service
fi

echo "=== 8. Remise en ligne ==="
"$PHP_BIN" artisan up
site_was_put_down=0

echo "=== 9. Contrôle HTTP ==="
http_code=""
for attempt in {1..10}; do
    http_code="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 10 "$HEALTH_URL" || true)"
    if [[ "$http_code" =~ ^(2|3)[0-9][0-9]$ ]]; then
        break
    fi
    sleep 2
done

if [[ ! "$http_code" =~ ^(2|3)[0-9][0-9]$ ]]; then
    echo "Health check en échec : HTTP ${http_code:-000}"
    false
fi

echo "HTTP $http_code"
echo "Déploiement terminé : $CURRENT_SHA"
