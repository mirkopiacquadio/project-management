#!/bin/bash

set -e

echo "====================================="
echo " PM-GEST UPDATE"
echo "====================================="

ROOT=$(cd "$(dirname "$0")/.." && pwd)

cd "$ROOT"

echo ""
echo "📥 Git Pull"

git pull

if [ ! -f docker-compose.yml ]; then
    echo ""
    echo "❌ docker-compose.yml mancante"
    exit 1
fi

echo ""
echo "🐳 Build"

docker compose build

echo ""
echo "🚀 Update"

docker compose up -d

echo ""
echo "🧹 Pulizia"

docker image prune -f
docker builder prune -f

echo ""
echo "📋 Stato"

docker compose ps

echo ""
echo "✅ Aggiornamento completato"