# Notifiche email

Oltre alle notifiche dentro l'app (campanella / risorsa *Notifiche*), il sistema manda
email automatiche sui ticket.

## Quando parte una email e a chi

| Evento | Destinatari |
| --- | --- |
| **Ticket aperto** | tutti gli utenti con ruolo `super_admin`, escluso chi lo ha aperto |
| **Nuovo commento** | chi ha aperto il ticket, gli assegnatari e chi ha già commentato, escluso chi scrive |
| **Cambio di stato** | chi ha aperto il ticket, gli assegnatari e chi ha già commentato, escluso chi lo ha spostato |

Note:
- Il cambio di stato viene rilevato ovunque avvenga: bacheca progetto, bacheca sprint,
  modifica del ticket (l'hook sta sul modello `Ticket`, non sulle pagine).
- La **modifica** di un commento già pubblicato genera solo la notifica in-app, non una nuova email.
- Chi entra dal portale esterno (`/external/{token}`) non riceve email: quell'accesso è
  legato a un token di progetto e non ha un indirizzo email associato.

## Dove sono attive

**Spente di default.** Ogni istanza decide con una variabile nel proprio `.env`:

```env
TICKET_EMAIL_NOTIFICATIONS=true     # solo sullo stack omniaticket
```

Quindi pmvvf / pm-gest e lo sviluppo locale non mandano niente finché non lo si chiede.

Sopra questo default c'è l'interruttore
**Impostazioni di sistema → Notifiche email → "Invia notifiche email"** (solo `super_admin`),
che salva in `settings` la chiave `email_notifications_enabled` e ha la precedenza sul `.env`.
Serve per spegnerle al volo senza toccare il server; se il database viene azzerato, la riga
sparisce e torna a valere il default del `.env`.

## Cosa serve in produzione

1. **SMTP configurato** nel `.env` del server. Con una casella Aruba:

   ```env
   MAIL_MAILER=smtp
   MAIL_SCHEME=smtps                       # porta 465; per la 587 usa MAIL_SCHEME=smtp
   MAIL_HOST=smtps.aruba.it
   MAIL_PORT=465
   MAIL_USERNAME=info@omnianextsrl.it      # indirizzo completo, non solo la parte prima della @
   MAIL_PASSWORD=<password della casella>
   MAIL_FROM_ADDRESS=info@omnianextsrl.it  # Aruba rifiuta un mittente diverso dalla casella autenticata
   MAIL_FROM_NAME="Omnianext Ticket"
   ```

   Su Laravel 12 vale `MAIL_SCHEME`, non il vecchio `MAIL_ENCRYPTION`.

2. **`APP_URL` corretto** (es. `https://ticket.omnianextsrl.it`): il pulsante "Apri il ticket"
   dentro l'email viene costruito da lì.

3. **Worker della coda attivo**: le email sono accodate (`QUEUE_CONNECTION=database`), quindi
   senza worker restano ferme nella tabella `jobs`. In produzione è il servizio `queue`
   di `deploy/docker-compose.prod.yml`; in locale il container `laravel_queue`.

Dopo aver toccato il `.env`:

```bash
docker compose -f deploy/docker-compose.prod.yml exec app php artisan config:clear
docker compose -f deploy/docker-compose.prod.yml restart queue
```

## Se le email non arrivano

```bash
# job in attesa e job falliti
docker exec <prefix>-app php artisan queue:failed
docker exec <prefix>-app php artisan tinker --execute="echo DB::table('jobs')->count();"

# log applicativo (gli errori di invio vengono loggati senza bloccare l'azione)
docker exec <prefix>-app tail -n 100 storage/logs/laravel.log
```

Prova d'invio secca, senza passare da un ticket:

```bash
docker compose exec app php artisan tinker --execute="Mail::raw('prova', fn(\$m) => \$m->to('info@omnianextsrl.it')->subject('Test SMTP')); echo 'inviata';"
```

`535 Authentication failed` da Aruba = password sbagliata o casella che in realtà è un alias.

In locale `MAIL_MAILER=log`: le email non partono davvero, vengono scritte in
`storage/logs/laravel.log` — comodo per verificare testo e destinatari.
