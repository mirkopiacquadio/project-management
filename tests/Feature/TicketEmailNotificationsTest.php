<?php

use App\Mail\TicketCommentNotification;
use App\Mail\TicketCreatedNotification;
use App\Mail\TicketStatusChangedNotification;
use App\Models\Project;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

// Stessa scelta degli altri test: transazione annullata a fine test, database di sviluppo intatto.
uses(DatabaseTransactions::class);

beforeEach(function () {
    Mail::fake();
    Setting::setValue('email_notifications_enabled', '1', 'notifications');

    Role::findOrCreate('super_admin', 'web');

    $this->admin = User::create([
        'name' => 'Admin di prova',
        'email' => 'admin-test-'.uniqid().'@example.test',
        'password' => bcrypt('secret'),
    ]);
    $this->admin->assignRole('super_admin');

    $this->author = User::create([
        'name' => 'Utente di prova',
        'email' => 'utente-test-'.uniqid().'@example.test',
        'password' => bcrypt('secret'),
    ]);

    $this->project = Project::create([
        'name' => 'Progetto di prova',
        'ticket_prefix' => 'TST',
        'start_date' => now(),
        'end_date' => now()->addMonth(),
    ]);

    $this->statuses = TicketStatus::query()->global()->orderBy('sort_order')->take(2)->get();
});

function makeTicket($test): Ticket
{
    return Ticket::create([
        'project_id' => $test->project->id,
        'ticket_status_id' => $test->statuses->first()->id,
        'name' => 'Ticket di prova',
        'description' => 'Descrizione di prova',
        'created_by' => $test->author->id,
    ]);
}

it('manda una email ai super admin quando viene aperto un ticket', function () {
    $this->actingAs($this->author);

    makeTicket($this);

    Mail::assertQueued(
        TicketCreatedNotification::class,
        fn ($mail) => $mail->hasTo($this->admin->email)
    );
});

it('non manda la email a chi ha aperto il ticket', function () {
    $this->actingAs($this->admin);

    Ticket::create([
        'project_id' => $this->project->id,
        'ticket_status_id' => $this->statuses->first()->id,
        'name' => 'Ticket aperto da un admin',
        'created_by' => $this->admin->id,
    ]);

    Mail::assertNotQueued(
        TicketCreatedNotification::class,
        fn ($mail) => $mail->hasTo($this->admin->email)
    );
});

it('avvisa chi ha aperto il ticket quando arriva un commento', function () {
    $this->actingAs($this->author);
    $ticket = makeTicket($this);

    $this->actingAs($this->admin);
    TicketComment::create([
        'ticket_id' => $ticket->id,
        'user_id' => $this->admin->id,
        'comment' => '<p>Ci sto lavorando</p>',
    ]);

    Mail::assertQueued(
        TicketCommentNotification::class,
        fn ($mail) => $mail->hasTo($this->author->email)
    );
});

it('avvisa chi ha aperto il ticket quando cambia lo stato', function () {
    $this->actingAs($this->author);
    $ticket = makeTicket($this);

    $this->actingAs($this->admin);
    $ticket->update(['ticket_status_id' => $this->statuses->last()->id]);

    Mail::assertQueued(
        TicketStatusChangedNotification::class,
        fn ($mail) => $mail->hasTo($this->author->email)
            && $mail->newStatusName === $this->statuses->last()->name
            && $mail->oldStatusName === $this->statuses->first()->name
    );
});

it('resta spento sulle istanze dove TICKET_EMAIL_NOTIFICATIONS non e\' attivo', function () {
    // Nessuna riga in settings: vale il default dell'istanza letto dal .env.
    Setting::where('key', 'email_notifications_enabled')->delete();
    config()->set('notifications.ticket_emails', false);

    $this->actingAs($this->author);
    makeTicket($this);

    Mail::assertNothingQueued();
});

it('non manda nessuna email se le notifiche email sono disattivate', function () {
    Setting::setValue('email_notifications_enabled', '0', 'notifications');

    $this->actingAs($this->author);
    $ticket = makeTicket($this);

    $this->actingAs($this->admin);
    $ticket->update(['ticket_status_id' => $this->statuses->last()->id]);

    Mail::assertNothingQueued();
});
