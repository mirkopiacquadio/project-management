<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sprint extends Model
{
    use HasFactory;

    public const STATUS_PLANNING = 'planning';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'name',
        'goal',
        'start_date',
        'end_date',
        'status',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function statuses(): HasMany
    {
        return $this->hasMany(SprintStatus::class)->orderBy('sort_order');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'sprint_user')
            ->withTimestamps();
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPlanning(): bool
    {
        return $this->status === self::STATUS_PLANNING;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function hasMember(User $user): bool
    {
        return $this->members()->where('users.id', $user->id)->exists();
    }

    public function getProgressPercentageAttribute(): float
    {
        $totalTickets = $this->tickets()->count();

        if ($totalTickets === 0) {
            return 0.0;
        }

        $completedTickets = $this->tickets()
            ->whereHas('sprintStatus', function ($query) {
                $query->where('is_completed', true);
            })
            ->count();

        return round(($completedTickets / $totalTickets) * 100, 1);
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_PLANNING => __('app.sprint_status_planning'),
            self::STATUS_ACTIVE => __('app.sprint_status_active'),
            self::STATUS_COMPLETED => __('app.sprint_status_completed'),
        ];
    }
}
