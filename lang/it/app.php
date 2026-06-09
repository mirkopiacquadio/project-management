<?php

return [
    // Navigazione e gruppi
    'project_management' => 'Gestione Progetti',
    'analytics' => 'Analisi',
    'settings' => 'Impostazioni',

    // Risorse / Modelli
    'project' => 'Progetto',
    'projects' => 'Progetti',
    'ticket' => 'Ticket',
    'tickets' => 'Ticket',
    'user' => 'Utente',
    'users' => 'Utenti',
    'role' => 'Ruolo',
    'roles' => 'Ruoli',
    'notification' => 'Notifica',
    'notifications' => 'Notifiche',
    'epic' => 'Epica',
    'epics' => 'Epiche',
    'ticket_priority' => 'Priorità Ticket',
    'ticket_priorities' => 'Priorità Ticket',

    // Etichette di navigazione
    'leaderboard' => 'Classifica',
    'project_board' => 'Bacheca Progetto',
    'project_timeline' => 'Timeline Progetto',
    'ticket_timeline' => 'Timeline Ticket',
    'user_contributions' => 'Contributi Utente',
    'epics_overview' => 'Epiche',
    'dashboard' => 'Dashboard',
    'ui_settings' => 'Impostazioni UI',
    'system_settings' => 'Impostazioni Sistema',

    // Reset / Ripristino database
    'reset_database' => 'Azzera e ripristina database',
    'reset_database_section' => 'Azzera ambiente di test',
    'reset_database_section_desc' => 'Elimina tutti i dati di prova e riporta il database allo stato iniziale (migrazioni + dati di base).',
    'reset_database_modal_heading' => 'Azzerare e ripristinare il database?',
    'reset_database_modal_desc' => 'Verranno ELIMINATE tutte le tabelle e tutti i dati (progetti, ticket, commenti, utenti, ecc.). Il database sarà ricreato da zero con le migrazioni e i dati iniziali. Operazione irreversibile.',
    'reset_database_confirm_label' => 'Scrivi RESET per confermare',
    'reset_database_confirm_help' => 'Devi scrivere esattamente RESET per procedere.',
    'reset_database_submit' => 'Sì, azzera tutto',
    'reset_database_success_title' => 'Database ripristinato',
    'reset_database_success_body' => 'Tutti i dati di prova sono stati eliminati e il database è stato riportato allo stato iniziale.',
    'reset_database_error_title' => 'Ripristino non riuscito',

    // Impostazioni UI - sezione Navigazione
    'nav_layout_section' => 'Layout di navigazione',
    'nav_layout_section_desc' => 'Scegli il tuo stile di navigazione preferito',
    'layout_style_label' => 'Stile layout',
    'nav_sidebar' => 'Navigazione laterale',
    'nav_top' => 'Navigazione superiore',
    'nav_sidebar_desc' => 'Layout classico con barra laterale (consigliato per desktop)',
    'nav_top_desc' => 'Barra di navigazione superiore moderna (ottima per i tablet)',

    // Impostazioni UI - sezione Tema colori
    'color_theme_section' => 'Tema colori',
    'color_theme_section_desc' => 'Personalizza i colori dell\'interfaccia',
    'primary_color_label' => 'Colore primario',

    // Impostazioni UI - notifiche
    'nav_updated_title' => 'Navigazione aggiornata',
    'nav_updated_top' => 'Preferenza navigazione superiore salvata. Ricarica per applicare.',
    'nav_updated_sidebar' => 'Preferenza navigazione laterale salvata.',
    'color_updated_title' => 'Tema colori aggiornato',
    'color_updated_body' => 'Colore primario cambiato in :color.',
    'settings_saved_title' => 'Impostazioni salvate con successo',
    'settings_saved_body' => 'Preferenze salvate. Ricarico per applicare il layout...',
    'save' => 'Salva',

    // Notifiche / Tab
    'all_notifications' => 'Tutte le Notifiche',
    'my_notifications' => 'Le Mie Notifiche',
    'unread' => 'Non Lette',
    'read' => 'Lette',

    // Campi comuni
    'name' => 'Nome',
    'description' => 'Descrizione',
    'status' => 'Stato',
    'priority' => 'Priorità',
    'assignees' => 'Assegnatari',
    'assigned_to' => 'Assegnato a',
    'assign_to' => 'Assegna a',
    'assignee' => 'Assegnatario',
    'created_by' => 'Creato Da',
    'start_date' => 'Data Inizio',
    'due_date' => 'Data Scadenza',
    'created_at' => 'Creato Il',
    'updated_at' => 'Aggiornato Il',
    'message' => 'Messaggio',
    'members' => 'Membri',
    'notes' => 'Note',
    'email' => 'Email',
    'password' => 'Password',

    // Ticket
    'ticket_id' => 'ID Ticket',
    'ticket_name' => 'Nome Ticket',
    'no_priority' => 'Nessuna Priorità',
    'no_epic' => 'Nessuna Epica',
    'no_description_provided' => 'Nessuna descrizione fornita',

    // Azioni
    'back_to_board' => 'Torna alla Bacheca',
    'edit_comment' => 'Modifica Commento',
    'delete_comment' => 'Elimina Commento',
    'update' => 'Aggiorna',
    'copy' => 'Copia',
    'import_from_excel' => 'Importa da Excel',
    'download_import_template' => 'Scarica Template Importazione',
    'excel_file' => 'File Excel',
    'update_status' => 'Aggiorna Stato',

    // Notifiche di sistema
    'import_completed' => 'Importazione Completata',
    'import_failed' => 'Importazione Fallita',
    'import_error' => 'Errore Importazione',
    'ticket_created' => 'Ticket creato',
    'ticket_updated' => 'Ticket aggiornato',
    'comment_updated_successfully' => 'Commento aggiornato con successo',
    'comment_deleted_successfully' => 'Commento eliminato con successo',
    'comment_not_found' => 'Commento non trovato',
    'some_assignees_removed' => 'Alcuni assegnatari rimossi',
    'no_permission_edit_comment' => 'Non hai il permesso di modificare questo commento',
    'no_permission_delete_comment' => 'Non hai il permesso di eliminare questo commento',

    // Ticket Statuses
    'ticket_statuses' => 'Stati Ticket',

    // Widget / Statistiche
    'total_projects' => 'Progetti Totali',
    'total_assigned_tickets' => 'Ticket Assegnati Totali',
    'tickets_created' => 'Ticket Creati',
];
