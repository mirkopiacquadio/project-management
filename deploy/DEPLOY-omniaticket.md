# Deploy — `ticket.omnianextsrl.it` (stack `omniaticket`)

Messa online dell'istanza **Omnianext** di questo gestionale ticket, con **import dei dati
locali** (23 progetti, 35 ticket, 9 epic, 2 utenti). Convive sulla stessa VPS con lo stack
`pmvvf` (`pm-gest.it`), da cui e' **completamente separata**: stesso codice, DB e volumi diversi.

## Coordinate

| Cosa | Valore |
|------|--------|
| VPS | `5.249.150.209` (PM-GEST) |
| PROJECT_NAME | `omniaticket` |
| Cartella stack | `/opt/docker/stacks/omniaticket` |
| Dominio | `https://ticket.omnianextsrl.it` |
| Container | `omniaticket-app`, `omniaticket-nginx`, `omniaticket-db`, `omniaticket-queue` |
| Reti | `omniaticket-network` (privata) + `proxy` (esterna, con NPM) |
| Volumi | `omniaticket-db-data`, `omniaticket-storage` |
| DB | `omniaticket` (nome diverso dal locale: il dump non e' legato al nome) |
| Dump dati | `deploy/seed/omniaticket-seed.sql` (gitignorato, si copia via `scp`) |

> ⚠️ Lo stack `pmvvf` non va toccato in nessuno dei passi seguenti. Ogni comando parte da
> `cd /opt/docker/stacks/omniaticket`.

---

## 0. Prerequisiti manuali (da fare PRIMA, richiedono propagazione)

**a) DNS su Aruba** — zona `omnianextsrl.it`, aggiungi un record:

```
Tipo: A     Nome: ticket     Valore: 5.249.150.209     TTL: default
```

Verifica dal Mac prima di procedere con l'SSL (deve rispondere `5.249.150.209`):

```bash
dig +short ticket.omnianextsrl.it
```

**b) Casella email su Aruba** — crea `noreply@omnianextsrl.it` e tieni a portata la password:
serve per l'SMTP al punto 3. Aruba rifiuta invii con mittente esterno al dominio.

---

## 1. Copia il dump dei dati sulla VPS (dal Mac)

Il dump e' gia' stato generato da questa cartella (contiene tutte le 24 tabelle di dati;
escluse solo `cache`, `cache_locks`, `sessions`, `jobs`, `job_batches`, `failed_jobs`,
che si ricreano vuote da sole).

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/PMVVFSW
scp deploy/seed/omniaticket-seed.sql root@5.249.150.209:/opt/backups/omniaticket-seed.sql
```

Per rigenerarlo (se nel frattempo lavori ancora in locale), stesso comando che l'ha creato:

```bash
docker exec laravel_db mysqldump -uroot -psecret \
  --single-transaction --no-tablespaces --skip-add-locks --skip-comments \
  --default-character-set=utf8mb4 \
  --ignore-table=dewakoding_project_management.cache \
  --ignore-table=dewakoding_project_management.cache_locks \
  --ignore-table=dewakoding_project_management.sessions \
  --ignore-table=dewakoding_project_management.jobs \
  --ignore-table=dewakoding_project_management.job_batches \
  --ignore-table=dewakoding_project_management.failed_jobs \
  dewakoding_project_management > deploy/seed/omniaticket-seed.sql
```

---

## 2. Crea lo stack sulla VPS

```bash
ssh root@5.249.150.209

# Come clona gia' pmvvf (ssh o https)? Usa lo stesso metodo per non incastrarti sulle chiavi:
git -C /opt/docker/stacks/pmvvf/app remote -v

mkdir -p /opt/docker/stacks/omniaticket
cd /opt/docker/stacks/omniaticket

# Clona il repo (stesso di pmvvf: identico codice, istanza diversa)
git clone git@github.com:mirkopiacquadio/project-management.git app
# ...oppure, se pmvvf usa https:
# git clone https://github.com/mirkopiacquadio/project-management.git app
```

---

## 3. Scrivi il `.env` dello stack

> ⚠️ Il `.env` va nella **root dello stack** (`/opt/docker/stacks/omniaticket`), **non** in
> `app/`: e' li' che sta `docker-compose.yml` e da li' viene letto come `env_file`.
> Dopo il `git clone` ti ritrovi facilmente dentro `app/`, quindi il `cd` conta.
>
> ⚠️ Niente heredoc (`cat > .env <<EOF`): incollando da chat o da un browser le righe
> arrivano rientrate, il terminatore `EOF` diventa `  EOF`, bash non lo riconosce e resta
> appeso al prompt `>`. Il comando qui sotto e' **una riga sola**: gli spazi iniziali
> non danno fastidio e non c'e' nessun terminatore da azzeccare.

Incolla questo blocco (le prime due righe e la terza sono tre comandi distinti):

```bash
cd /opt/docker/stacks/omniaticket
```

```bash
DB_PASS=$(openssl rand -hex 20); DB_ROOT=$(openssl rand -hex 20); printf 'PROJECT_NAME=omniaticket\n\nAPP_NAME="Omnianext Ticket"\nAPP_ENV=production\nAPP_DEBUG=false\nAPP_KEY=\nAPP_URL=https://ticket.omnianextsrl.it\nAPP_LOCALE=it\nAPP_FALLBACK_LOCALE=it\n\nDB_CONNECTION=mysql\nDB_HOST=db\nDB_PORT=3306\nDB_DATABASE=omniaticket\nDB_USERNAME=omniaticket\nDB_PASSWORD=%s\n\nMYSQL_DATABASE=omniaticket\nMYSQL_USER=omniaticket\nMYSQL_PASSWORD=%s\nMYSQL_ROOT_PASSWORD=%s\n\nQUEUE_CONNECTION=database\nCACHE_STORE=database\nSESSION_DRIVER=database\n\nMAIL_MAILER=smtp\nMAIL_SCHEME=smtps\nMAIL_HOST=smtps.aruba.it\nMAIL_PORT=465\nMAIL_USERNAME=noreply@omnianextsrl.it\nMAIL_PASSWORD=CAMBIAMI\nMAIL_FROM_ADDRESS=noreply@omnianextsrl.it\nMAIL_FROM_NAME="Omnianext Ticket"\n' "$DB_PASS" "$DB_PASS" "$DB_ROOT" > .env; chmod 600 .env; echo "ROOT DB: $DB_ROOT"; echo "USER DB: $DB_PASS"
```

Salva subito le due password stampate, poi metti quella vera della casella Aruba al posto
di `CAMBIAMI`:

```bash
nano .env      # riga MAIL_PASSWORD=
```

Controllo prima di proseguire (34 righe, **zero** righe che iniziano con uno spazio):

```bash
wc -l .env; grep -c '^[[:space:]]' .env; head -1 .env
```

> `APP_KEY` resta vuoto: lo genera `install.sh` al passo 4.
> `GESTIONALE_API_TOKEN` **non va messo**: il gestionale `omnianextsrl` e' fermo e
> l'accoppiamento e' staccato. Senza token il middleware `EnsureGestionaleToken` e'
> fail-closed e `/api/projects` risponde `401` a chiunque — che e' esattamente quello
> che vogliamo. Per riattivarlo vedi "API gestionale" a fondo pagina.

---

## 4. Build e avvio

```bash
cd /opt/docker/stacks/omniaticket
bash app/deploy/install.sh
```

Lo script: genera `APP_KEY`, copia `deploy/docker-compose.prod.yml` in `docker-compose.yml`,
builda le immagini e avvia lo stack. Al primo boot l'entrypoint lancia `migrate --force`,
quindi il DB parte con lo **schema vuoto** — i dati arrivano al passo 5.

```bash
docker compose ps     # tutti "running", db "healthy"
```

---

## 5. Importa i dati locali

```bash
cd /opt/docker/stacks/omniaticket

# La password root viene letta dentro il container: non finisce nella history
docker compose exec -T db sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" omniaticket' \
  < /opt/backups/omniaticket-seed.sql

# Svuota le cache di Laravel (il DB e' cambiato sotto i piedi) e riavvia
docker compose exec -T app php artisan optimize:clear
docker compose exec -T app php artisan optimize
docker compose restart app queue
```

**Verifica che i dati ci siano** (attesi: 2 utenti, 23 progetti, 35 ticket, 9 epic):

```bash
docker compose exec -T db sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" omniaticket -e "
  SELECT (SELECT COUNT(*) FROM users) AS utenti,
         (SELECT COUNT(*) FROM projects) AS progetti,
         (SELECT COUNT(*) FROM tickets) AS ticket,
         (SELECT COUNT(*) FROM epics) AS epic;"'

# Nessuna migrazione deve risultare pendente
docker compose exec -T app php artisan migrate:status | tail -5
```

---

## 6. Reverse proxy e SSL (Nginx Proxy Manager)

`http://5.249.150.209:81` → **Hosts → Proxy Hosts → Add Proxy Host**

Tab **Details**:

| Campo | Valore |
|-------|--------|
| Domain Names | `ticket.omnianextsrl.it` |
| Scheme | `http` ← **non** https |
| Forward Hostname / IP | `omniaticket-nginx` ← il container, non il dominio |
| Forward Port | `80` |
| Block Common Exploits | ✅ |
| Websockets Support | ✅ |

Tab **SSL** (solo **dopo** che `dig` risponde correttamente): *Request a new SSL Certificate*
+ ✅ Force SSL + ✅ HTTP/2 + accetta i ToS Let's Encrypt.

Poi apri **https://ticket.omnianextsrl.it/admin** e accedi con le credenziali che usi in
locale (`piacquadio.mirko@gmail.com`).

---

## 7. Backup automatico del DB (consigliato)

Il DB ora e' l'unica copia dei tuoi dati oltre al locale. Cron giornaliero alle 03:00:

```bash
mkdir -p /opt/backups/omniaticket
crontab -e
```

```cron
0 3 * * * cd /opt/docker/stacks/omniaticket && docker compose exec -T db sh -c 'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --no-tablespaces omniaticket' > /opt/backups/omniaticket/omniaticket-$(date +\%F).sql 2>/dev/null && find /opt/backups/omniaticket -name '*.sql' -mtime +14 -delete
```

---

## Operativita' corrente

```bash
cd /opt/docker/stacks/omniaticket

docker compose ps                      # stato
docker compose logs --tail=100 app     # errori Laravel
docker compose exec app php artisan <cmd>
bash app/deploy/update.sh                  # pull + rebuild + migrate + optimize

# dopo aver modificato .env serve --force-recreate (restart NON rilegge le variabili)
docker compose up -d --force-recreate app queue
```

### ⚠️ Mai su questo stack

```bash
php artisan app:reset --force   # WIPE TOTALE del DB: cancellerebbe i tuoi 23 progetti
```

### Problemi noti (identici a pmvvf, vedi `deploy/RUNBOOK.md`)

- **502 Bad Gateway** → su NPM scheme `https` invece di `http`, o forward host sbagliato
  (deve essere `omniaticket-nginx`), oppure IP upstream stale: `docker compose restart nginx`.
- **500 su `/admin` ma la home redirige** → manca `public/build/manifest.json`: rebuild immagine.
- **500 intermittenti senza nulla in `laravel.log`** → permessi storage:
  `docker compose exec app chown -R www-data:www-data storage bootstrap/cache && docker compose restart app`.
- **UI in inglese / chiavi grezze `app.xxx`** → manca `APP_LOCALE=it` nel `.env`.

---

## API gestionale — accoppiamento staccato

Questo PM espone `/api/projects` per il gestionale `htdocs/omnianextsrl`, che pero' e'
**un progetto fermo**: al 2026-08-24 il suo stack Docker locale e' stato spento
(`docker compose stop`, container `omnia_*`) e nel suo `.env` e' impostato
`PM_API_ENABLED=false` (backup: `.env.bak-2026-08-24`). Il client `PmApiClient` in quello
stato restituisce liste vuote senza sollevare eccezioni, quindi non rompe nulla.

In produzione l'accoppiamento resta staccato semplicemente **non definendo**
`GESTIONALE_API_TOKEN`: `EnsureGestionaleToken` e' fail-closed e nega tutto.

```bash
# verifica che l'API sia chiusa (deve rispondere 401)
curl -s -o /dev/null -w '%{http_code}\n' https://ticket.omnianextsrl.it/api/projects
```

### Per riattivarlo, quando il gestionale tornera' vivo

```bash
# 1) sulla VPS: genera un token NUOVO (quello vecchio ha girato solo in locale)
cd /opt/docker/stacks/omniaticket
TOKEN=$(openssl rand -hex 24)
echo "GESTIONALE_API_TOKEN=${TOKEN}" >> .env
docker compose up -d --force-recreate app queue    # un restart NON rilegge il .env
echo "$TOKEN"

# 2) nel .env del gestionale omnianextsrl
#    PM_API_ENABLED=true
#    PM_API_BASE_URL=https://ticket.omnianextsrl.it
#    PM_API_TOKEN=<il token stampato sopra>
#    poi: php artisan config:clear   e riaccendi lo stack (docker compose start)
```
