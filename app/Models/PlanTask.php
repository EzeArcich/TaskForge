<?php

namespace App\Models;

use App\Domain\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanTask extends Model
{
    protected $fillable = [
        'plan_id',
        'plan_week_id',
        'title',
        'estimate_hours',
        'status',
        'scheduled_date',
        'scheduled_start',
        'scheduled_end',
        'trello_card_id',
        'google_event_id',
    ];

    protected function casts(): array
    {
        return [
            'estimate_hours' => 'decimal:2',
            'status' => TaskStatus::class,
            'scheduled_date' => 'date',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function week(): BelongsTo
    {
        return $this->belongsTo(PlanWeek::class, 'plan_week_id');
    }

    public function isDone(): bool
    {
        return $this->status === TaskStatus::Done;
    }
}
