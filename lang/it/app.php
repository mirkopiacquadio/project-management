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

    // ---- i18n completo pannello ----
    // Ticket / assegnatari
    'select_assignees_help' => 'Seleziona uno o più utenti da assegnare a questo ticket. Solo i membri del progetto possono essere assegnati.',
    'non_member_removed_body' => 'Alcuni utenti selezionati non sono membri di questo progetto e sono stati rimossi dagli assegnatari.',
    'auto_assigned' => 'Assegnazione automatica',
    'auto_assigned_body' => 'Nessun assegnatario valido trovato. Sei stato assegnato automaticamente a questo ticket.',
    'ticket_created_body' => 'Il ticket è stato creato con successo.',
    'ticket_updated_body' => 'Il ticket è stato aggiornato con successo.',
    'delete_comment_confirm' => 'Sei sicuro di voler eliminare questo commento? L\'azione è irreversibile.',
    'ticket_discussion' => 'Discussione su questo ticket',
    'ticket_not_found' => 'Ticket non trovato',
    'no_perm_move' => 'Non hai il permesso di spostare questo ticket.',
    'no_perm_edit' => 'Non hai il permesso di modificare questo ticket.',
    'permission_denied' => 'Permesso negato',

    // Progetto
    'project_color' => 'Colore progetto',
    'project_color_help' => 'Scegli un colore per la card e il badge del progetto',
    'use_default_statuses' => 'Usa stati ticket predefiniti',
    'default_statuses_help' => 'Crea automaticamente gli stati standard Backlog, To Do, In Progress, Review e Done',
    'pin_project' => 'Fissa progetto',
    'pin_project_help' => 'I progetti fissati appariranno nella timeline della dashboard',
    'pinned_date' => 'Data fissaggio',
    'pinned_status' => 'Stato fissaggio',
    'progress' => 'Avanzamento',
    'remaining_days' => 'Giorni rimanenti',
    'pinned' => 'Fissato',
    'ticket_prefix_label' => 'Prefisso ticket',
    'not_set' => 'Non impostato',
    'tickets_count_label' => 'Numero ticket',

    // Epiche / ordinamento
    'sort_order' => 'Ordinamento',
    'lower_numbers_first' => 'I numeri più bassi appaiono per primi',
    'order' => 'Ordine',
    'assign_to_epic' => 'Assegna a epica',
    'assign_epic_help' => 'Seleziona un\'epica a cui assegnare i ticket selezionati. Lascia vuoto per rimuovere l\'assegnazione.',
    'epic_assignment_updated' => 'Assegnazione epica aggiornata',

    // Note
    'note_date' => 'Data nota',
    'note_help' => 'Scrivi qui il riepilogo della riunione o le note di progetto con formattazione avanzata.',
    'recent_30_days' => 'Recenti (30 giorni)',
    'add_note' => 'Aggiungi nota',
    'no_notes_heading' => 'Ancora nessuna nota di progetto',
    'no_notes_desc' => 'Inizia a documentare le riunioni e le note importanti del progetto.',

    // Stati ticket
    'status_color_help' => 'Seleziona un colore per questo stato',
    'status_order_help' => 'Determina l\'ordine di visualizzazione nella bacheca (i valori più bassi appaiono per primi)',
    'mark_completed_status' => 'Segna come stato "Completato"',
    'one_completed_help' => 'Solo uno stato per progetto può essere segnato come completato',
    'cannot_mark_completed' => 'Impossibile segnare come completato',
    'completed' => 'Completato',
    'status_updated' => 'Stato aggiornato',

    // Membri / assegnazioni di massa
    'remove' => 'Rimuovi',
    'remove_selected' => 'Rimuovi selezionati',
    'assign_users' => 'Assegna utenti',
    'assignment_mode' => 'Modalità di assegnazione',
    'users_assigned' => 'Utenti assegnati',
    'update_priority' => 'Aggiorna priorità',
    'assign_role' => 'Assegna ruolo',
    'has_projects' => 'Ha progetti',
    'has_assigned_tickets' => 'Ha ticket assegnati',
    'has_created_tickets' => 'Ha ticket creati',
    'email_unverified' => 'Email non verificata',
    'tickets_assigned_tooltip' => 'Numero di ticket assegnati a questo utente',
    'tickets_created_tooltip' => 'Numero di ticket creati da questo utente',
    'back_to_ticket' => 'Torna al ticket',

    // Dashboard esterna
    'external_dashboard' => 'Dashboard esterna',
    'external_dashboard_access' => 'Accesso dashboard esterna',
    'external_share_desc' => 'Condividi queste credenziali con utenti esterni per accedere alla dashboard del progetto.',
    'regenerate_access' => 'Rigenera accesso',
    'regenerate_external_access' => 'Rigenera accesso esterno',
    'regenerate_warning' => 'Verranno generate nuove credenziali e quelle attuali saranno invalidate.',
    'external_regenerated' => 'Accesso esterno rigenerato con successo',

    // Notifiche (risorsa)
    'na' => 'N/D',
    'mark_as_read' => 'Segna come letto',
    'notification_marked_read' => 'Notifica segnata come letta',
    'view_ticket' => 'Vedi ticket',
    'mark_all_read' => 'Segna tutte come lette',
    'all_notifications_read' => 'Tutte le notifiche segnate come lette',
    'unread_only' => 'Solo non lette',

    // Import / Export
    'template_downloaded' => 'Template scaricato',
    'export_to_excel' => 'Esporta in Excel',
    'export_columns_desc' => 'Scegli quali colonne includere nell\'esportazione Excel',
    'export_columns_section' => 'Seleziona colonne da esportare',
    'columns' => 'Colonne',
    'import_desc' => 'Carica un file Excel per importare i ticket in questo progetto. Puoi scaricare il template qui sotto.',
    'import_select_project_desc' => 'Seleziona un progetto e carica un file Excel per importare i ticket. Dopo aver selezionato un progetto potrai scaricare il template qui sotto.',
    'upload_excel_help' => 'Carica il file Excel con i dati dei ticket. Assicurati di usare il formato del template qui sopra.',
    'import_error_prefix' => 'Si è verificato un errore durante l\'importazione: ',
    'export_error_prefix' => 'Si è verificato un errore durante l\'esportazione: ',
    'error' => 'Errore',
    'select_project_first' => 'Seleziona prima un progetto.',
    'export_failed' => 'Esportazione fallita',
    'export_no_columns' => 'Seleziona almeno una colonna da esportare.',
    'export_no_tickets' => 'Nessun ticket da esportare.',
    'export_successful' => 'Esportazione completata',
    'export_downloading' => 'Il tuo file Excel è in download.',

    // Bacheca / filtri
    'refresh_board' => 'Aggiorna bacheca',
    'filter_by_user' => 'Filtra per utente',
    'select_users_filter' => 'Seleziona utenti da filtrare',
    'filter_applied' => 'Filtro applicato',
    'filter_cleared' => 'Filtro rimosso',
    'showing_all_tickets' => 'Visualizzazione di tutti i ticket',

    // Pagine / errori
    'project_not_found' => 'Progetto non trovato',
    'project_not_found_body' => 'Il progetto selezionato non è stato trovato o non hai accesso.',
    'data_refreshed' => 'Dati aggiornati',
    'error_loading_page' => 'Errore nel caricamento della pagina',

    // Attività recenti
    'activity' => 'Attività',
    'today_only' => 'Solo oggi',
    'open_ticket' => 'Apri ticket',
    'no_activity_heading' => 'Nessuna attività trovata',
    'no_activity_desc' => 'Nessuna attività sui ticket nel periodo selezionato.',

    // Commenti
    'add_comment' => 'Aggiungi un commento',
    'comment_placeholder' => 'Scrivi qui il tuo commento...',
    'comment_added' => 'Commento aggiunto con successo',

    // Sezioni (View/Form)
    'ticket_information' => 'Informazioni ticket',
    'status_assignment' => 'Stato e assegnazione',
    'comments_section' => 'Commenti',
    'metadata' => 'Metadati',
    'status_history' => 'Cronologia stati',
    'import_section' => 'Importa ticket da Excel',
    'project_information' => 'Informazioni progetto',
    'project_statistics' => 'Statistiche progetto',
    'timestamps' => 'Date',

    // Statistiche dashboard
    'stat_active_projects' => 'Progetti attivi nel sistema',
    'stat_tickets_all' => 'Ticket su tutti i progetti',
    'stat_tickets_assigned' => 'Ticket assegnati a te',
    'stat_registered_users' => 'Utenti registrati',
    'stat_projects_member' => 'Progetti di cui sei membro',
    'stat_tickets_created' => 'Ticket che hai creato',
    'stat_total_tickets' => 'Ticket totali nei tuoi progetti',
    'stat_completed' => 'I tuoi ticket completati',
    'stat_created_in_projects' => 'Creati nei tuoi progetti',
    'stat_past_due' => 'I tuoi ticket scaduti',
    'stat_people' => 'Persone nei tuoi progetti',

    // Heading widget / grafici
    'chart_tickets_per_project' => 'Numero di ticket per progetto',
    'chart_user_stats' => 'Statistiche utenti',
    'overview' => 'Panoramica',
    'chart_monthly_trend' => 'Andamento mensile creazione ticket',

    // Etichette campi (auto-label)
    'end_date' => 'Data Fine',
    'color' => 'Colore',
    'uuid' => 'ID',
    'title' => 'Titolo',
    'comment' => 'Commento',
    'content' => 'Contenuto',
    'message_field' => 'Messaggio',
    'team' => 'Team',
    'guard_name' => 'Guard',
    'email_verified_at' => 'Email verificata il',
    'due_from' => 'Scadenza da',
    'due_until' => 'Scadenza fino a',
    'read_status' => 'Stato lettura',
    'statuses_count' => 'Stati',
    'permissions_count' => 'Permessi',
    'assigned_tickets_count' => 'Ticket assegnati',
];
