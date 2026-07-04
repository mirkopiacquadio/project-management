# RUNBOOK — manutenzione stack `pmvvf` (VPS PM-GEST)

Comandi da eseguire **sulla VPS** (`ssh root@5.249.150.209`).
Cartella di lavoro dello stack: `/opt/docker/stacks/pmvvf`.

```bash
STACK=/opt/docker/stacks/pmvvf
cd "$STACK"
```

> `app/` è il repo git; `docker-compose.yml` è una copia di
> `app/deploy/docker-compose.prod.yml`; `.env` contiene i segreti.

---

## Deploy / aggiornamento

Metodo consigliato (fa tutto: pull, sync compose, build, up, migrate, optimize):
```bash
bash /opt/docker/stacks/pmvvf/app/deploy/update.sh
```

Manuale, se preferisci passo-passo:
```bash
cd "$STACK/app" && git pull
cd "$STACK"
cp app/deploy/docker-compose.prod.yml docker-compose.yml   # i Dockerfile possono cambiare
docker compose build
docker compose up -d --remove-orphans
```

## Stato, log, riavvii

```bash
docker compose ps                       # stato dei container
docker compose logs -f app              # log applicazione (Ctrl-C per uscire)
docker compose logs -f nginx            # log web server
docker compose logs --tail=100 queue    # log worker code
docker compose restart app              # riavvia un servizio
docker compose up -d --force-recreate app   # ricrea app (rilegge .env, rifà optimize)
docker compose down                     # ferma lo stack (i volumi restano)
docker compose up -d                    # riavvia lo stack
```

## Artisan / Laravel

```bash
docker compose exec app php artisan about
docker compose exec app php artisan migrate --force
docker compose exec app php artisan optimize          # ri-cache config/route/view
docker compose exec app php artisan optimize:clear    # svuota le cache
docker compose exec app php artisan queue:restart
docker compose exec app php artisan tinker
```

Reset completo ai dati iniziali (ATTENZIONE: cancella e riseeda il DB):
```bash
docker compose exec app php artisan app:reset --force
```

## Database — backup e restore

```bash
# leggi le credenziali dal .env dello stack
source <(grep -E '^(MYSQL_DATABASE|MYSQL_USER|MYSQL_PASSWORD)=' .env)

# BACKUP (con timestamp)
docker compose exec -T db mysqldump -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" \
  > "/opt/backups/pmvvf-$(date +%F-%H%M).sql"

# RESTORE da un dump
docker compose exec -T db mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" \
  < /opt/backups/pmvvf-XXXX.sql
```

## Variabili d'ambiente

```bash
# cambiare una variabile del .env e applicarla (serve ricreare il container)
sed -i 's|^APP_URL=.*|APP_URL=https://pm-gest.it|' .env
docker compose up -d --force-recreate app
```

## Spazio disco / pulizia

```bash
docker system df                        # quanto occupa Docker
docker image prune -af                  # rimuove immagini dangling/inutilizzate
docker builder prune -af                # svuota la cache di build
```

---

## Troubleshooting — errori 500

Ordine di controllo (cause già viste su questo stack):

1. **`MissingAppKeyException` / "No application encryption key"** → `APP_KEY` vuoto nel `.env`.
   ```bash
   grep -q '^APP_KEY=base64:' .env || \
     sed -i "s|^APP_KEY=.*|APP_KEY=base64:$(openssl rand -base64 32)|" .env
   docker compose up -d --force-recreate app
   ```

2. **500 solo sulle pagine (login/admin), la home reindirizza** → **manifest Vite mancante**
   (asset non compilati). Verifica e, se manca, ribuilda:
   ```bash
   docker compose exec app ls -l public/build/manifest.json   # deve esistere
   docker compose build && docker compose up -d --force-recreate
   ```

3. **`SQLSTATE[HY000] [2002] Connection refused`** → l'app ha colpito il DB durante un
   riavvio. Verifica che `db` sia healthy; il compose corretto ha
   `depends_on: db: condition: service_healthy`.
   ```bash
   docker compose ps
   docker compose exec app php artisan migrate:status | tail
   ```

4. **Diagnosi generica dell'errore reale** (attiva debug temporaneo, poi RIPRISTINA):
   ```bash
   sed -i 's/^APP_DEBUG=.*/APP_DEBUG=true/' .env
   docker compose up -d --force-recreate app
   # riproduci l'errore, poi leggi il messaggio:
   docker compose exec app sh -c "grep -a 'production.ERROR' storage/logs/laravel.log | tail -1"
   # RIPRISTINO OBBLIGATORIO:
   sed -i 's/^APP_DEBUG=.*/APP_DEBUG=false/' .env
   docker compose up -d --force-recreate app
   ```

5. **Test HTTP interno** (bypassa NPM/DNS, verifica app+nginx da soli):
   ```bash
   docker compose exec nginx wget -S -q -O /dev/null http://127.0.0.1/admin/login 2>&1 | grep HTTP
   ```

## Dominio / NPM

- Proxy Host: `pm-gest.it`, `www.pm-gest.it` → Forward `pmvvf-nginx:80`, scheme `http`,
  SSL Let's Encrypt + Force SSL + HTTP/2.
- DNS: record **A** `pm-gest.it` → `5.249.150.209` (registrar Aruba).
- Verifica propagazione: `dig +short pm-gest.it` (deve dare `5.249.150.209`).
- Verifica esterna finale: `curl -I https://pm-gest.it/admin/login` → `HTTP/2 200`.
