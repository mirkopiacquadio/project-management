# Deploy su Hosting Condiviso Linux (stile Aruba)

Guida per pubblicare il progetto su un **hosting condiviso** (es. "Hosting Linux" di
Aruba). È **fattibile ma scomodo**: niente Docker, spesso niente SSH, niente processi
persistenti. Per un'esperienza molto migliore valuta un piccolo **VPS** → vedi
[DEPLOY.md](DEPLOY.md).

---

## 0) Prima di tutto: verifica con l'hosting

Chiedi/conferma nel pannello dell'hosting:

1. **PHP 8.3+** disponibile e selezionabile? (obbligatorio)
2. Estensioni PHP: `pdo_mysql, mbstring, bcmath, gd, intl, zip, exif, fileinfo, openssl`
3. **MySQL** incluso (crea un database + utente)
4. **SSH** disponibile? (cambia tutto, vedi sotto)
5. Posso impostare la **document root** su una sottocartella (`/public`)? E **cron job**?

Se PHP 8.3 **non** è disponibile → non puoi pubblicare qui.

---

## 1) Prepara tutto IN LOCALE

Sull'hosting condiviso di solito **non puoi** lanciare `composer`, `npm`, `artisan`.
Quindi prepari il pacchetto sul tuo PC e poi carichi via FTP/File Manager.

```bash
# In locale, nella cartella del progetto:
composer install --no-dev --optimize-autoloader   # genera vendor/ di produzione
npm ci && npm run build                            # genera public/build/

# Genera una APP_KEY da incollare nel .env (vedi punto 3)
php artisan key:generate --show
```

Da caricare sul server: **tutto il progetto, incluse `vendor/` e `public/build/`**
(non escluderle nell'upload).

---

## 2) Document root → `public/`

Laravel deve essere servito dalla cartella **`public/`**. Due casi:

**Caso A — puoi impostare la docroot** (alcuni piani): puntala alla cartella
`.../pmvvfsw/public`. Fine.

**Caso B — docroot fissa** (es. `htdocs/` o `www/`): metti i file dell'app **sopra**
la docroot e nella docroot copia il contenuto di `public/`, poi in `index.php`
correggi i due path:

```php
// in public/index.php (copiato nella docroot)
require __DIR__.'/../pmvvfsw/vendor/autoload.php';
$app = require_once __DIR__.'/../pmvvfsw/bootstrap/app.php';
```
Mantieni la cartella dell'app **fuori** dalla webroot per sicurezza.

Assicurati che il `.htaccess` di Laravel (in `public/`) sia presente (Apache + mod_rewrite).

---

## 3) Configura il `.env`

Crea/carica il file `.env` sul server con i dati dell'hosting:

```env
APP_NAME="Project Management"
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...incolla-qui-quella-generata...
APP_URL=https://iltuodominio.it

DB_CONNECTION=mysql
DB_HOST=localhost            # spesso 'localhost' su shared; Aruba a volte un host dedicato
DB_PORT=3306
DB_DATABASE=nome_db_aruba
DB_USERNAME=utente_db
DB_PASSWORD=password_db

# Niente worker su shared hosting → email sincrone:
QUEUE_CONNECTION=sync
SESSION_DRIVER=database
CACHE_STORE=database

MAIL_MAILER=smtp
MAIL_HOST=smtps.aruba.it
MAIL_PORT=465
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=tuo@dominio.it
```

> `QUEUE_CONNECTION=sync` = le notifiche email partono durante la richiesta (un po' più
> lente ma non serve alcun processo in background).

---

## 4) Inizializza il database (il punto critico senza SSH)

Le tabelle vanno create. Tre opzioni, in ordine di preferenza:

### Opzione 1 — Hai SSH (anche tramite il pannello)
```bash
php8.3 artisan migrate --seed --force
php8.3 artisan shield:generate --all --option=permissions --panel=admin --no-interaction
php8.3 artisan storage:link
php8.3 artisan app:reset --force --email=tuo@dominio.it --password=...   # crea super admin
```
(Spesso il binario è `php8.3`/`php83`, non `php`.)

### Opzione 2 — Niente SSH: importa un dump SQL
1. In **locale** prepara il DB completo (`migrate --seed` + `shield:generate` + crea il super admin).
2. Esporta: `mysqldump -u root -psecret pm_prod > dump.sql` (o da phpMyAdmin locale).
3. Sull'hosting, da **phpMyAdmin**, importa `dump.sql` nel database creato.
4. Verifica che l'utente super admin esista (così puoi fare login).

### Opzione 3 — Niente SSH: rotta web temporanea (sconsigliata, ma utile una tantum)
Crea una rotta protetta da token che lancia i comandi una sola volta, poi **rimuovila**:
```php
// routes/web.php — SOLO temporaneo, poi cancella!
Route::get('/__setup/{token}', function (string $token) {
    abort_unless($token === env('SETUP_TOKEN'), 403);
    \Artisan::call('migrate', ['--seed' => true, '--force' => true]);
    \Artisan::call('shield:generate', ['--all'=>true,'--option'=>'permissions','--panel'=>'admin','--no-interaction'=>true]);
    return 'OK';
});
```
Imposta `SETUP_TOKEN` nel `.env`, visita l'URL una volta, **poi elimina la rotta**.

---

## 5) Permessi e storage

- Rendi scrivibili dal web server: `storage/` e `bootstrap/cache/` (di solito `755`/`775`).
- `storage:link` crea il symlink `public/storage` → `storage/app/public`. Se non puoi
  lanciarlo (no SSH) e i symlink non sono permessi, **copia** manualmente i file da
  `storage/app/public` dentro `public/storage`, oppure imposta `FILESYSTEM_DISK=public`.

---

## 6) Limiti da accettare sul condiviso

| Aspetto | Conseguenza |
|---|---|
| Niente `php artisan` (senza SSH) | I comandi del progetto (**`app:reset`**, `shield:generate`) non sono lanciabili → usa dump SQL |
| Niente worker/supervisor | `QUEUE_CONNECTION=sync` (email sincrone) |
| Niente Docker | Usi PHP/MySQL nativi dell'hosting |
| Aggiornamenti del codice | Ricarichi via FTP `vendor/` e `public/build/` rigenerati in locale |
| Nessun reverse proxy custom | HTTPS gestito dall'hosting (di solito incluso) |

---

## Checklist finale

- [ ] PHP 8.3+ attivo sul dominio
- [ ] Database MySQL creato + credenziali nel `.env`
- [ ] `vendor/` e `public/build/` caricati
- [ ] `APP_KEY` impostata, `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` corretto
- [ ] Document root su `public/` (o workaround `index.php`)
- [ ] DB inizializzato (migrate/seed o import dump)
- [ ] Super admin esistente per il login
- [ ] `storage/` e `bootstrap/cache/` scrivibili
- [ ] `QUEUE_CONNECTION=sync`
- [ ] Login su `https://iltuodominio.it/admin`
