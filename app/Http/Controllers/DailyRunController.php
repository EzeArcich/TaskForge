<?php

namespace App\Http\Controllers;

use App\Jobs\DailyRunJob;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;

class DailyRunController extends Controller
{
    /**
     * POST /api/plans/{plan}/daily-run - Trigger daily routine manually.
     */
    public function __invoke(Plan $plan): JsonResponse
    {
        $plan->load(['weeks.tasks']);

        DailyRunJob::dispatchSync($plan->id);

        $timezone = $plan->settings['timezone'] ?? 'UTC';
        $today = now($timezone)->format('Y-m-d');

        $todayTasks = $plan->tasks()
            ->where('scheduled_date', $today)
            ->orderBy('scheduled_start')
            ->get()
            ->map(fn ($task) => [
                'id' => $task->id,
                'title' => $task->title,
                'status' => $task->status?->value ?? $task->status,
                'scheduled_start' => $task->scheduled_start,
                'scheduled_end' => $task->scheduled_end,
            ]);

        return response()->json([
            'message' => 'Daily run completed.',
            'plan_id' => $plan->id,
            'date' => $today,
            'today_tasks' => $todayTasks,
        ]);
    }
}
