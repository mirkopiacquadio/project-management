# RUNBOOK — pmvvf in produzione (VPS PM-GEST)

Comandi operativi per lo stack **pmvvf** in produzione. Dominio: **https://pm-gest.it/admin**

## Coordinate

| Cosa | Valore |
|------|--------|
| VPS IP | `5.249.150.209` |
| Cartella stack | `/opt/docker/stacks/pmvvf` |
| PROJECT_NAME | `pmvvf` (⚠️ NON `pm-gest`: quello è solo il dominio) |
| Container | `pmvvf-app` (php-fpm), `pmvvf-nginx` (web), `pmvvf-db` (mysql8), `pmvvf-queue` |
| Reti | `pmvvf-network` (privata) + `proxy` (esterna, condivisa con NPM) |
| Volume | `pmvvf-storage` → `/var/www/storage` |
| Reverse proxy | Nginx Proxy Manager · UI `http://5.249.150.209:81` |
| Portainer | `https://5.249.150.209:9443` |

> Tutti i comandi `docker compose` vanno lanciati da `cd /opt/docker/stacks/pmvvf`.
> `artisan` gira **dentro** il container `pmvvf-app` (l'host non ha PHP 8.3).

---

## Reverse proxy / SSL (Nginx Proxy Manager)

Proxy Host per `pm-gest.it` (tab **Details**):

- **Scheme:** `http`  ← non https
- **Forward Hostname / IP:** `pmvvf-nginx`
- **Forward Port:** `80`
- ✅ Block Common Exploits · ✅ Websockets Support

Tab **SSL**: *Request a new SSL Certificate* + ✅ Force SSL + ✅ HTTP/2 + accetta i ToS Let's Encrypt.

**502 Bad Gateway** dopo il save → nel 99% dei casi: scheme messo su `https`, oppure forward-host errato (deve essere `pmvvf-nginx`). Verifica anche che sia NPM sia `pmvvf-nginx` siano sulla rete `proxy` (Portainer → container → *Connected Networks*).

---

## Lingua / nome app (locale)

Sintomo: UI in inglese e chiavi grezze tipo `app.or_continue_with`. Causa: `APP_LOCALE` non impostato → default `en`.

```bash
cd /opt/docker/stacks/pmvvf

# nome app (cambia il valore a piacere)
sed -i 's/^APP_NAME=.*/APP_NAME="Project Management"/' .env

# forza italiano (aggiunge le righe se mancano)
grep -q '^APP_LOCALE=' .env && sed -i 's/^APP_LOCALE=.*/APP_LOCALE=it/' .env || echo 'APP_LOCALE=it' >> .env
grep -q '^APP_FALLBACK_LOCALE=' .env && sed -i 's/^APP_FALLBACK_LOCALE=.*/APP_FALLBACK_LOCALE=it/' .env || echo 'APP_FALLBACK_LOCALE=it' >> .env

# applica (l'entrypoint rifà "artisan optimize")
docker compose up -d --force-recreate app queue
```

---

## Ciclo di vita dello stack

```bash
cd /opt/docker/stacks/pmvvf

docker compose ps                     # stato container
docker compose up -d                  # avvia / ricrea ciò che è cambiato
docker compose restart app queue      # riavvio soft
docker compose down                   # ferma (mantiene i volumi)
docker compose up -d --force-recreate app queue   # ricrea con nuove env
```

Dopo aver modificato il `.env`: serve **--force-recreate** (un semplice restart NON rilegge le variabili).

---

## Deploy / aggiornamento da git

```bash
cd /opt/docker/stacks/pmvvf
bash deploy/update.sh          # pull + rebuild + migrate + optimize (vedi lo script)

# oppure manuale:
git -C app pull
cp app/deploy/docker-compose.prod.yml docker-compose.yml
docker compose build app nginx
docker compose up -d
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan optimize
```

---

## Artisan (dentro il container)

```bash
docker compose exec app php artisan <comando>
# esempi:
docker compose exec app php artisan about
docker compose exec app php artisan migrate --force
docker compose exec app php artisan optimize          # config+route+view cache
docker compose exec app php artisan optimize:clear    # svuota tutte le cache
docker compose exec app php artisan queue:restart     # dopo un deploy, riavvia i worker
docker compose exec app php artisan shield:generate --all --option=policies
docker compose exec app php artisan app:reset --force  # ⚠️ WIPE totale + reseed + super admin
```

## Log e diagnosi

```bash
docker compose logs -f app            # log php-fpm/laravel
docker compose logs -f nginx          # log web
docker compose logs --tail=200 queue  # worker code (email)
docker compose exec app tail -f storage/logs/laravel.log

# 500 su /admin ma home che redirige → sospetta il manifest Vite:
docker compose exec app ls -la public/build/manifest.json
```

## Database

```bash
# shell mysql
docker compose exec db mysql -uroot -p"$MYSQL_ROOT_PASSWORD" dewakoding_project_management

# backup
docker compose exec -T db mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" dewakoding_project_management > /opt/backups/pmvvf-$(date +%F).sql

# restore
docker compose exec -T db mysql -uroot -p"$MYSQL_ROOT_PASSWORD" dewakoding_project_management < /opt/backups/pmvvf-DATA.sql
```

## Storage / permessi

```bash
docker compose exec app php artisan storage:link          # se manca il symlink public/storage
docker compose exec app chown -R www-data:www-data storage bootstrap/cache
```

---

## Checklist "sito non va"

1. `docker compose ps` → tutti i container `running` / `db` `healthy`?
2. NPM raggiungibile e Proxy Host presente? Scheme `http`, host `pmvvf-nginx`, porta `80`?
3. `pmvvf-nginx` e NPM entrambi sulla rete `proxy`?
4. `docker compose logs --tail=100 app` → eccezioni Laravel?
5. `public/build/manifest.json` esiste? Altrimenti rebuild dell'immagine.
6. `.env`: `APP_KEY` valorizzato, `APP_URL=https://pm-gest.it`, `APP_LOCALE=it`?
