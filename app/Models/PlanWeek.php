<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanWeek extends Model
{
    protected $fillable = [
        'plan_id',
        'week_number',
        'goal',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(PlanTask::class);
    }
}
