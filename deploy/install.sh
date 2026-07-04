#!/bin/bash

set -e

echo "====================================="
echo " PM-GEST INSTALL"
echo "====================================="

ROOT=$(cd "$(dirname "$0")/../.." && pwd)

cd "$ROOT"

if [ ! -f .env ]; then
    echo ""
    echo "❌ .env mancante"
    exit 1
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