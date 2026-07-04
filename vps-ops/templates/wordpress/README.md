# Template WordPress → stack sulla VPS PM-GEST

Stack: container `wp` (wordpress:php8.3-apache, porta 80 interna) + `db` (mysql:8.0),
volumi persistenti `${PROJECT_NAME}-wp-data` e `${PROJECT_NAME}-db-data`.
Il container `wp` sta sulla rete `proxy` → NPM lo instrada.

Sostituisci ovunque `<PROJECT_NAME>` (es. `mariorossi`) e `<DOMINIO>` (es. `mariorossi.it`).

---

## A. Nuovo sito WordPress (installazione pulita)

Sulla VPS:

```bash
NAME=<PROJECT_NAME>
STACK=/opt/docker/stacks/$NAME
mkdir -p "$STACK"
cd "$STACK"

# 1) porta qui i file dello stack (compose + .env.example)
#    - se hai un repo:  git clone <repo> app  &&  cp app/deploy/* .
#    - altrimenti copia docker-compose.prod.yml e .env.prod.example in questa cartella
cp docker-compose.prod.yml docker-compose.yml
cp .env.prod.example .env

# 2) genera i segreti nel .env
sed -i "s|^PROJECT_NAME=.*|PROJECT_NAME=$NAME|" .env
sed -i "s|^MYSQL_PASSWORD=.*|MYSQL_PASSWORD=$(openssl rand -base64 24)|" .env
sed -i "s|^MYSQL_ROOT_PASSWORD=.*|MYSQL_ROOT_PASSWORD=$(openssl rand -base64 24)|" .env

# 3) assicurati che la rete condivisa esista, poi avvia
docker network inspect proxy >/dev/null 2>&1 || docker network create proxy
docker compose up -d

docker compose ps
```

Poi: **Proxy Host su NPM** + **DNS** (sezione C) e completa l'installazione da browser.

---

## B. Importare un WordPress ESISTENTE (es. da htdocs/MarioRossi)

Hai i **file** del sito e (di solito) un **dump SQL**.

```bash
NAME=<PROJECT_NAME>
STACK=/opt/docker/stacks/$NAME
# ...esegui prima i passi 1-3 della sezione A per creare .env e avviare i container...

# 4) copia i file del sito dentro il volume wp-data (dentro il container wp)
#    (dal tuo PC, prima: scp -r /path/locale/MarioRossi root@5.249.150.209:/tmp/wpsrc)
docker cp /tmp/wpsrc/. ${NAME}-wp:/var/www/html/
docker compose exec wp chown -R www-data:www-data /var/www/html

# 5) importa il dump SQL nel database
source <(grep -E '^(MYSQL_DATABASE|MYSQL_USER|MYSQL_PASSWORD)=' "$STACK/.env")
docker compose exec -T db mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" < /tmp/mariorossi.sql

# 6) aggiorna gli URL del sito al nuovo dominio (siteurl/home + contenuti)
#    installa wp-cli al volo nel container:
docker compose exec wp bash -c '
  curl -sO https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar &&
  chmod +x wp-cli.phar && mv wp-cli.phar /usr/local/bin/wp'
docker compose exec -u www-data wp wp option update home    "https://<DOMINIO>"
docker compose exec -u www-data wp wp option update siteurl "https://<DOMINIO>"
docker compose exec -u www-data wp wp search-replace "http://vecchio-dominio" "https://<DOMINIO>" --all-tables --skip-columns=guid
```

> Se il DB era su un `wp_` prefix diverso o con credenziali diverse, il `wp-config.php`
> importato coi file viene comunque **sovrascritto** dalle env `WORDPRESS_DB_*` del container
> solo se NON esiste già un wp-config: se il sito esistente porta il suo `wp-config.php`,
> aggiorna a mano host/user/password/DB (host = `db`), oppure rimuovilo e lascia che WP
> lo rigeneri dalle variabili d'ambiente.

---

## C. Instradamento (uguale per A e B)

1. **DNS** sul registrar: record **A** `<DOMINIO>` → `5.249.150.209` (+ `www` se serve).
   Verifica: `dig +short <DOMINIO>` → `5.249.150.209`.
2. **NPM** → Add Proxy Host:
   - Domain Names: `<DOMINIO>`, `www.<DOMINIO>`
   - Scheme `http` — Forward Hostname `<PROJECT_NAME>-wp` — Port `80`
   - Block Common Exploits: ON — Websockets Support: ON
   - Tab **SSL** (SOLO dopo che il DNS risolve alla VPS): Request new LE cert + Force SSL + HTTP/2.

## D. Manutenzione

```bash
cd /opt/docker/stacks/<PROJECT_NAME>
docker compose ps
docker compose logs -f wp
docker compose restart wp
# backup DB
source <(grep -E '^(MYSQL_DATABASE|MYSQL_USER|MYSQL_PASSWORD)=' .env)
docker compose exec -T db mysqldump -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" \
  > /opt/backups/<PROJECT_NAME>-$(date +%F-%H%M).sql
# backup file
docker run --rm -v <PROJECT_NAME>-wp-data:/data -v /opt/backups:/out alpine \
  tar czf /out/<PROJECT_NAME>-wp-$(date +%F).tgz -C /data .
```
