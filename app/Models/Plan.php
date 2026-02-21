<?php

namespace App\Models;

use App\Domain\Enums\PlanStatus;
use App\Domain\Enums\ValidationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'hash',
        'plan_text',
        'settings',
        'normalized_json',
        'schedule',
        'validation_status',
        'publish_status',
        'trello_board_id',
        'trello_board_url',
        'google_calendar_id',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'normalized_json' => 'array',
            'schedule' => 'array',
            'validation_status' => ValidationStatus::class,
            'publish_status' => PlanStatus::class,
        ];
    }

    public function weeks(): HasMany
    {
        return $this->hasMany(PlanWeek::class)->orderBy('week_number');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(PlanTask::class);
    }

    public function isPublished(): bool
    {
        return $this->publish_status === PlanStatus::Published;
    }

    public function isDraft(): bool
    {
        return $this->publish_status === PlanStatus::Draft;
    }
}
