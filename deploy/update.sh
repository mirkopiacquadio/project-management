#!/bin/bash

set -e

STACK_ROOT=$(cd "$(dirname "$0")/../.." && pwd)
APP_ROOT="$STACK_ROOT/app"

echo "====================================="
echo " PM-GEST UPDATE"
echo "====================================="

echo ""
echo "📥 Git Pull"

cd "$APP_ROOT"
git pull

echo ""
echo "📄 Versione installata"

git log -1 --oneline

echo ""
echo "📄 Sincronizzo docker-compose.yml"

cd "$STACK_ROOT"
cp "$APP_ROOT/deploy/docker-compose.prod.yml" docker-compose.yml

echo ""
echo "🐳 Build immagini"

docker compose build

echo ""
echo "🚀 Aggiornamento stack"

docker compose up -d --remove-orphans

echo ""
echo "⏳ Attendo avvio servizi..."

sleep 5

echo ""
echo "🗄️ Migrazioni database"

docker compose exec -T app php artisan migrate --force

echo ""
echo "⚡ Ottimizzazione Laravel"

docker compose exec -T app php artisan optimize

echo ""
echo "🔄 Riavvio Queue"

docker compose exec -T app php artisan queue:restart

echo ""
echo "🧹 Pulizia Docker"

docker image prune -af
docker builder prune -af

echo ""
echo "💾 Utilizzo disco Docker"

docker system df

echo ""
echo "📋 Stato servizi"

docker compose ps

echo ""
echo "✅ Deploy completato con successo"