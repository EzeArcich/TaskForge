<?php

namespace App\Application\Contracts;

use App\Models\Plan;

interface CalendarProviderInterface
{
    /**
     * Create calendar events for all scheduled tasks.
     *
     * @return array{calendar_id: string, event_ids: array<string, string>}
     */
    public function createEvents(Plan $plan): array;

    /**
     * Update existing events with new schedule.
     */
    public function updateEvents(Plan $plan): void;

    /**
     * Delete all events for a plan.
     */
    public function deleteEvents(Plan $plan): void;
}
