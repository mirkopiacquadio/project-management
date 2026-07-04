#!/bin/bash

set -e

echo "====================================="
echo " PM-GEST INSTALL"
echo "====================================="

STACK_ROOT=$(cd "$(dirname "$0")/../.." && pwd)
APP_ROOT="$STACK_ROOT/app"

cd "$STACK_ROOT"

if [ ! -f .env ]; then
    echo ""
    echo "❌ .env mancante"
    exit 1
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    echo ""
    echo "🔑 Genero APP_KEY"
    KEY="base64:$(openssl rand -base64 32)"
    if grep -q '^APP_KEY=' .env; then
        sed -i "s|^APP_KEY=.*|APP_KEY=${KEY}|" .env
    else
        echo "APP_KEY=${KEY}" >> .env
    fi
fi

if [ ! -f docker-compose.yml ]; then
    echo ""
    echo "📄 Creo docker-compose.yml"
    cp deploy/docker-compose.prod.yml docker-compose.yml
fi

echo ""
echo "🐳 Build"

docker compose build --no-cache

echo ""
echo "🚀 Avvio"

docker compose up -d

echo ""
echo "📋 Stato"

docker compose ps

echo ""
echo "✅ Installazione completata"