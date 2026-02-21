<?php

namespace Tests\Fakes;

use App\Application\Contracts\CalendarProviderInterface;
use App\Models\Plan;

class FakeCalendarProvider implements CalendarProviderInterface
{
    public array $createdEvents = [];
    public bool $shouldFail = false;

    public function createEvents(Plan $plan): array
    {
        if ($this->shouldFail) {
            throw new \RuntimeException('Fake Google Calendar error');
        }

        $eventIds = [];
        foreach ($plan->tasks as $task) {
            $eventIds[$task->id] = 'fake_event_' . $task->id;
        }

        $result = [
            'calendar_id' => 'primary',
            'event_ids' => $eventIds,
        ];

        $this->createdEvents[] = $result;

        return $result;
    }

    public function updateEvents(Plan $plan): void
    {
        // no-op
    }

    public function deleteEvents(Plan $plan): void
    {
        // no-op
    }
}
