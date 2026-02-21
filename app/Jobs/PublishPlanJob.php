<?php

namespace App\Jobs;

use App\Application\Services\PlanService;
use App\Infrastructure\Calendar\CalendarProviderFactory;
use App\Infrastructure\Kanban\KanbanProviderFactory;
use App\Models\Plan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PublishPlanJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public readonly int $planId,
    ) {}

    public function handle(
        PlanService $planService,
        KanbanProviderFactory $kanbanFactory,
        CalendarProviderFactory $calendarFactory,
    ): void {
        $plan = Plan::with(['weeks.tasks'])->findOrFail($this->planId);

        if ($plan->isPublished()) {
            Log::info('PublishPlanJob: plan already published, skipping.', ['plan_id' => $plan->id]);
            return;
        }

        $kanban = $kanbanFactory->make($plan->settings['kanban_provider'] ?? 'trello');
        $calendar = $calendarFactory->make($plan->settings['calendar_provider'] ?? 'google');

        $planService->publishPlan($plan, $kanban, $calendar);

        Log::info('PublishPlanJob: plan published successfully.', ['plan_id' => $plan->id]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('PublishPlanJob failed', [
            'plan_id' => $this->planId,
            'error' => $exception->getMessage(),
        ]);
    }
}
