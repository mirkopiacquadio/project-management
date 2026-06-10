# Deploy & istanza Demo

Guida per mettere online il progetto su un **VPS** (con SSH, scenario consigliato)
e per gestire due istanze separate: **produzione** ("tua") e **demo**.

> Per l'hosting condiviso senza SSH vedi [DEPLOY_CONDIVISO.md](DEPLOY_CONDIVISO.md).
> Per azzerare/ripristinare un'istanza vedi [RESET.md](RESET.md).

---

## Requisiti del progetto

- **PHP 8.3+** (il lock delle dipendenze lo impone) con estensioni: `pdo_mysql, mbstring, exif, pcntl, bcmath, gd, intl, zip`
- **MySQL 8** (o MariaDB compatibile)
- **Composer** + **Node.js/npm** (solo per buildare gli asset con Vite)
- Un processo per la **queue** (email asincrone) — supervisor o `QUEUE_CONNECTION=sync`

Sessioni, cache e code usano il **database** (vedi `.env`).

---

## A) Deploy su VPS con Docker (consigliato)

Il modo più semplice: replica l'ambiente locale.

```bash
# Sul server
git clone <repo> pmvvfsw && cd pmvvfsw
cp .env.example .env
# imposta in .env: APP_ENV=production, APP_DEBUG=false, APP_URL=https://tuodominio.it,
#                  DB_*, MAIL_*  (vedi sezione .env sotto)

docker compose up -d --build

# Dentro il container app:
docker exec laravel_app composer install --no-dev --optimize-autoloader
docker exec laravel_app php artisan key:generate
docker exec laravel_app php artisan migrate --seed --force
docker exec laravel_app php artisan shield:generate --all --option=permissions --panel=admin --no-interaction
docker exec laravel_app php artisan storage:link
docker exec laravel_app php artisan app:reset --force   # opz.: crea subito un super admin

# Build asset (Node sul server, oppure builda in locale e carica public/build)
npm ci && npm run build
```

> ⚠️ `artisan` va eseguito **dentro** il container: `docker exec laravel_app php artisan ...`
> (il PHP della macchina host potrebbe non essere 8.3).

Davanti a tutto metti un reverse proxy (Nginx host / Traefik / Caddy) con HTTPS che
inoltra al container nginx sulla porta pubblicata.

### Cache di produzione
```bash
docker exec laravel_app php artisan config:cache
docker exec laravel_app php artisan route:cache
docker exec laravel_app php artisan view:cache   # vedi nota sotto
```
> Nota: `view:cache` attualmente fallisce su un file **vendor**
> (`vendor/tab-layout-plugin/...`). Se ti serve, va sistemato a parte; in produzione
> puoi ometterlo (le view si compilano a runtime).

---

## B) Deploy su VPS "nativo" (senza Docker)

Nginx + PHP-FPM 8.3 + MySQL. Document root → cartella **`public/`**.

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --seed --force
php artisan shield:generate --all --option=permissions --panel=admin --no-interaction
php artisan storage:link
npm ci && npm run build
php artisan config:cache && php artisan route:cache
```

### Queue worker (supervisor)
`/etc/supervisor/conf.d/laravel-worker.conf`:
```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/pmvvfsw/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/pmvvfsw/storage/logs/worker.log
stopwaitsecs=3600
```
```bash
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl start laravel-worker:*
```

---

## C) Due istanze: produzione + demo

Regola d'oro: **stesso codice, `.env` e database SEPARATI.**

Per ogni istanza, nel `.env`:

| Variabile | Produzione | Demo |
|---|---|---|
| `APP_NAME` | "Project Management" | "PM Demo" |
| `APP_ENV` | `production` | `production` (o `local` se locale) |
| `APP_URL` | `https://pm.tuodominio.it` | `https://demo.tuodominio.it` |
| `APP_KEY` | propria (`key:generate`) | **propria, diversa** |
| `DB_DATABASE` | `pm_prod` | `pm_demo` |

Su ciascuna istanza esegui: `migrate --seed --force` + `shield:generate ...` + crea il super admin.

### Demo in locale con Docker (già pronta)

È incluso **`docker-compose.demo.yml`** (progetto Docker `pmdemo`, isolato dal principale):

```bash
# avvia la demo (NON tocca lo stack principale)
docker compose -f docker-compose.demo.yml up -d

# inizializza la demo
docker exec laravel_app_demo php artisan migrate --seed --force
docker exec laravel_app_demo php artisan shield:generate --all --option=permissions --panel=admin --no-interaction
docker exec laravel_app_demo php artisan app:reset --force --email=demo@demo.it --password=demo1234
```

| | Principale | Demo |
|---|---|---|
| App | http://localhost:8000 | http://localhost:8001 |
| phpMyAdmin | http://localhost:8080 | http://localhost:8081 |
| MySQL (host) | 3307 | 3308 |
| Database | `dewakoding_project_management` | `pm_demo` |
| Container | `laravel_*` | `laravel_*_demo` |
| Progetto Docker | (cartella) | `pmdemo` |
| Login demo | — | `demo@demo.it` / `demo1234` |

Comandi utili demo:
```bash
docker compose -f docker-compose.demo.yml ps      # stato
docker compose -f docker-compose.demo.yml down     # ferma (mantiene i dati)
docker compose -f docker-compose.demo.yml down -v  # ferma ed ELIMINA il db demo
```

> ⚠️ **Mai** avviare la demo con `docker compose up` senza `-f docker-compose.demo.yml`:
> il `name: pmdemo` nel file garantisce un progetto separato. Senza il flag `-f`
> useresti lo stack principale.

### Ripristinare lo stato della demo

Quando la demo è "sporca" dopo una presentazione, riportala pulita:
```bash
docker exec laravel_app_demo php artisan app:reset --force --email=demo@demo.it --password=demo1234
```
(reset manuale, on-demand — nessuna automazione notturna).

### Nota sui dati condivisi (demo locale)

La demo locale **condivide la cartella del codice** con il principale (stesso `./` montato),
quindi `storage/logs` e `public/storage` sono in comune — accettabile per una demo locale.
I **dati applicativi** (utenti, progetti, ticket) sono invece **separati** perché stanno
su database distinti. Per una demo totalmente indipendente, copiala in un'altra cartella
con un proprio `.env`.

---

## .env per la produzione (estratto)

```env
APP_NAME="Project Management"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://pm.tuodominio.it

DB_CONNECTION=mysql
DB_HOST=db            # 'db' con Docker, altrimenti 127.0.0.1
DB_PORT=3306
DB_DATABASE=pm_prod
DB_USERNAME=...
DB_PASSWORD=...

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database   # con worker; usa 'sync' se non hai un worker

MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=...
```

> ⚠️ In produzione: `APP_DEBUG=false`, `APP_KEY` impostata, e valuta di **non**
> mostrare il pulsante "Azzera e ripristina database" agli utenti (è già limitato ai
> `super_admin` e richiede di digitare "RESET").
