<?php

use App\Models\TicketStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ticket statuses become a single, global set shared by every project
     * board and sprint board (project_id = null). Every existing ticket is
     * reset to the Backlog status, and the old per-project status columns are
     * removed.
     */
    public function up(): void
    {
        // 1. Allow global statuses (project_id = null). The original column is
        //    NOT NULL with an ON DELETE CASCADE foreign key, so drop the FK,
        //    relax the column, then restore the FK.
        Schema::table('ticket_statuses', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
        });

        Schema::table('ticket_statuses', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->change();
        });

        Schema::table('ticket_statuses', function (Blueprint $table) {
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
        });

        // 2. Seed the fixed global statuses if not already present.
        if (DB::table('ticket_statuses')->whereNull('project_id')->count() === 0) {
            $now = now();

            foreach (TicketStatus::DEFAULT_GLOBAL_STATUSES as $status) {
                DB::table('ticket_statuses')->insert([
                    ...$status,
                    'project_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // 3. Reset every ticket to the global Backlog status.
        //    (tickets.ticket_status_id cascades on delete, so we must repoint
        //    BEFORE deleting the old per-project rows.)
        $backlogId = DB::table('ticket_statuses')
            ->whereNull('project_id')
            ->orderBy('sort_order')
            ->value('id');

        if ($backlogId) {
            DB::table('tickets')->update(['ticket_status_id' => $backlogId]);
        }

        // 4. Sprint statuses are now dormant; the sprint board uses the global
        //    statuses via ticket_status_id. Clear the unused column.
        if (Schema::hasColumn('tickets', 'sprint_status_id')) {
            DB::table('tickets')->update(['sprint_status_id' => null]);
        }

        // 5. Drop the old per-project statuses now that nothing references them.
        DB::table('ticket_statuses')->whereNotNull('project_id')->delete();
    }

    /**
     * This is a one-way data migration: the original per-project statuses
     * cannot be reconstructed. We only restore the schema shape as far as
     * possible without losing referential integrity.
     */
    public function down(): void
    {
        // Intentionally left as a no-op. Reversing would delete the global
        // statuses that every ticket now points to, breaking foreign keys.
    }
};
