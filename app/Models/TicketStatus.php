<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class TicketStatus extends Model
{
    use HasFactory;

    /**
     * Fixed, global board statuses shared by every project board and sprint
     * board. Stored as rows with a null project_id. Editable from System
     * Settings; this is only the initial/default set.
     */
    public const DEFAULT_GLOBAL_STATUSES = [
        ['name' => 'Backlog', 'color' => '#6B7280', 'sort_order' => 0, 'is_completed' => false],
        ['name' => 'Da fare', 'color' => '#F59E0B', 'sort_order' => 1, 'is_completed' => false],
        ['name' => 'In corso', 'color' => '#3B82F6', 'sort_order' => 2, 'is_completed' => false],
        ['name' => 'In revisione', 'color' => '#8B5CF6', 'sort_order' => 3, 'is_completed' => false],
        ['name' => 'Completato', 'color' => '#10B981', 'sort_order' => 4, 'is_completed' => true],
    ];

    protected $fillable = [
        'project_id',
        'name',
        'sort_order',
        'color',
        'is_completed',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /** Global statuses have no owning project. */
    public function scopeGlobal(Builder $query): Builder
    {
        return $query->whereNull('project_id');
    }

    /** The global board statuses, ordered as they appear on the boards. */
    public static function globalStatuses(): Collection
    {
        return static::query()->global()->orderBy('sort_order')->get();
    }

    /** The first (default) global status — new tickets land here (Backlog). */
    public static function defaultStatus(): ?self
    {
        return static::query()->global()->orderBy('sort_order')->first();
    }

    /** Create the default global statuses once if none exist yet. */
    public static function ensureGlobalDefaults(): void
    {
        if (static::query()->global()->exists()) {
            return;
        }

        foreach (static::DEFAULT_GLOBAL_STATUSES as $status) {
            static::create(['project_id' => null, ...$status]);
        }
    }
}
