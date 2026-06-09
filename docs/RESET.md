# Reset & ripristino del sistema

Promemoria personale per duplicare/testare il progetto e riportarlo allo stato
iniziale ("da principio").

---

## Avvio rapido del progetto

Il progetto gira in **Docker** (non con il PHP locale).

```bash
# Avviare tutti i container
docker compose up -d

# Stato dei container
docker compose ps
```

URL e servizi:

| Servizio    | URL                          | Note                                   |
|-------------|------------------------------|----------------------------------------|
| App / Admin | http://localhost:8000/admin  | la root `/` reindirizza alla login     |
| phpMyAdmin  | http://localhost:8080        | gestione database                      |
| MySQL       | localhost:**3307**           | porta esterna mappata sul 3306 interno |

Credenziali database (definite in `.env`):

| Campo    | root          | utente app |
|----------|---------------|------------|
| Utente   | `root`        | `laravel`  |
| Password | `secret`      | `secret`   |
| Database | `dewakoding_project_management`         |

> ⚠️ **artisan va eseguito DENTRO il container.** Il PHP locale è 8.2, l'app
> richiede 8.3+. Quindi sempre:
>
> ```bash
> docker exec laravel_app php artisan <comando>
> ```

---

## Come azzerare e ripristinare

Ci sono **due modi**, entrambi fanno la stessa cosa (condividono
`app/Services/SystemResetService.php`).

### 1. Dal pannello (tasto)

`Admin → Impostazioni → System Settings` → pulsante in alto a destra
**"Azzera e ripristina database"**.

- Visibile **solo** ai `super_admin`.
- Chiede conferma: bisogna scrivere `RESET`.
- Ricrea l'utente con cui sei loggato come super_admin e ti tiene connesso.

### 2. Da terminale (comando) — la "via di fuga"

Da usare quando **sei bloccato fuori** (es. hai cancellato il super admin e il
tasto non compare più):

```bash
# Interattivo (chiede conferma + email/nome/password)
docker exec -it laravel_app php artisan app:reset

# Non interattivo, con default (email: admin@admin.it / password: password)
docker exec laravel_app php artisan app:reset --force

# Non interattivo con credenziali personalizzate
docker exec laravel_app php artisan app:reset --force \
  --email=mia@email.it --name="Mirko" --password=segreta
```

Opzioni:

| Opzione       | Significato                                            |
|---------------|-------------------------------------------------------|
| `--force`     | salta la conferma, usa i default (non interattivo)    |
| `--email=`    | email del super admin da (ri)creare                   |
| `--name=`     | nome visualizzato                                     |
| `--password=` | password del super admin                              |

> ⚠️ Senza `--force` in produzione il comando si rifiuta di partire.

---

## Cosa fa esattamente il ripristino

1. `migrate:fresh --seed --force` → **droppa tutte le tabelle** e ricrea il DB
   da zero con migrazioni + seeder (ruoli, permessi base, dati iniziali).
2. `shield:generate --all --option=permissions --panel=admin` → rigenera i
   permessi Filament Shield per **risorse, pagine e widget**.
3. Sincronizza il ruolo `super_admin` con **tutti** i permessi.
4. Svuota la cache dei permessi.
5. (Ri)crea un utente `super_admin`.

### Perché il punto 2 e 3 sono fondamentali

In `config/filament-shield.php` il super admin ha `define_via_gate => false`:
non è un "gate che vede tutto", è un **ruolo normale** che deve possedere i
permessi **esplicitamente**. Il `RoleSeeder` crea solo i permessi delle risorse,
**non** quelli dei widget → senza il punto 2/3 la **dashboard resta vuota** e la
navigazione è ridotta. La rigenerazione + sync risolve.

---

## Risoluzione problemi

| Sintomo                                   | Causa / Rimedio                                                                 |
|-------------------------------------------|--------------------------------------------------------------------------------|
| Dashboard vuota / menu ridotto dopo reset | Permessi widget mancanti → rilancia il reset (già incluso nel flusso)           |
| Non vedo il tasto "Azzera e ripristina"   | Il tuo utente non è `super_admin` → usa `app:reset` da terminale                |
| Bloccato fuori (super admin cancellato)   | `docker exec laravel_app php artisan app:reset --force`                         |
| `artisan` non parte in locale             | Va eseguito nel container: `docker exec laravel_app php artisan ...`            |

### Riassegnare super_admin senza reset (senza perdere i dati)

Se vuoi solo recuperare i permessi senza azzerare nulla:

```bash
docker exec laravel_app php artisan tinker --execute='
use App\Models\User; use Spatie\Permission\Models\{Role,Permission};
$r = Role::firstOrCreate(["name"=>"super_admin"]);
$r->syncPermissions(Permission::all());
User::first()->assignRole("super_admin");
app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
'
```
