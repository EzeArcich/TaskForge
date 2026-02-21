<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'hash' => $this->hash,
            'plan_text' => $this->plan_text,
            'settings' => $this->settings,
            'normalized_json' => $this->normalized_json,
            'schedule' => $this->schedule,
            'validation_status' => $this->validation_status?->value ?? $this->validation_status,
            'publish_status' => $this->publish_status?->value ?? $this->publish_status,
            'publication' => [
                'trello' => [
                    'published' => (bool) $this->trello_board_id,
                    'board_id' => $this->trello_board_id,
                    'board_url' => $this->trello_board_url,
                ],
                'google_calendar' => [
                    'published' => (bool) $this->google_calendar_id,
                    'calendar_id' => $this->google_calendar_id,
                ],
            ],
            'weeks' => $this->whenLoaded('weeks', fn () => $this->weeks->map(fn ($week) => [
                'id' => $week->id,
                'week_number' => $week->week_number,
                'goal' => $week->goal,
                'tasks' => $week->tasks->map(fn ($task) => [
                    'id' => $task->id,
                    'title' => $task->title,
                    'estimate_hours' => (float) $task->estimate_hours,
                    'status' => $task->status?->value ?? $task->status,
                    'scheduled_date' => $task->scheduled_date?->format('Y-m-d'),
                    'scheduled_start' => $task->scheduled_start,
                    'scheduled_end' => $task->scheduled_end,
                    'trello_card_id' => $task->trello_card_id,
                    'google_event_id' => $task->google_event_id,
                ]),
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
