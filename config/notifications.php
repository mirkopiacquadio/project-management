<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Email automatiche sui ticket
    |--------------------------------------------------------------------------
    |
    | Valore di default per istanza: le email partono solo dove il .env lo dice
    | (es. lo stack omniaticket). L'interruttore in "Impostazioni di sistema"
    | salva il valore in `settings` e ha la precedenza su questo default.
    |
    */

    'ticket_emails' => env('TICKET_EMAIL_NOTIFICATIONS', false),

];
