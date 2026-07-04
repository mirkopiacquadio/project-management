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
echo "🐳 Build"

cd "$STACK_ROOT"
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