#!/bin/sh
set -e

# Chave gerada na primeira subida e guardada no volume; nunca entra na imagem.
KEY_FILE=storage/app/.app_key
mkdir -p storage/app storage/logs storage/framework/cache/data storage/framework/sessions storage/framework/views
if [ -z "$APP_KEY" ]; then
    [ -f "$KEY_FILE" ] || (umask 077; echo "base64:$(openssl rand -base64 32)" > "$KEY_FILE")
    APP_KEY=$(cat "$KEY_FILE")
    export APP_KEY
fi
# Deixa a chave disponível para "docker compose exec".
(umask 077; echo "APP_KEY=$APP_KEY" > .env)
chown -R www-data:www-data storage bootstrap/cache .env

php artisan package:discover --ansi
php artisan migrate --seed --force
[ "$DEMO_SEED" = "true" ] && php artisan db:seed --class=DemoSeeder --force
chown -R www-data:www-data storage

exec "$@"
