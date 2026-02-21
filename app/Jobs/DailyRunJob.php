<?php

namespace App\Jobs;

use App\Application\Services\PlanService;
use App\Domain\Enums\PlanStatus;
use App\Domain\Enums\TaskStatus;
use App\Infrastructure\Kanban\KanbanProviderFactory;
use App\Mail\DailyPlanMail;
use App\Models\Plan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class DailyRunJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        public readonly int $planId,
    ) {}

    public function handle(
        PlanService $planService,
        KanbanProviderFactory $kanbanFactory,
    ): void {
        $plan = Plan::with(['weeks.tasks'])->findOrFail($this->planId);
        $timezone = $plan->settings['timezone'] ?? 'UTC';
        $today = now($timezone)->format('Y-m-d');

        // Get today's tasks
        $todayTasks = $plan->tasks()
            ->where('scheduled_date', $today)
            ->where('status', '!=', TaskStatus::Done->value)
            ->orderBy('scheduled_start')
            ->get();

        // Move cards to "Hoy" on Trello if published
        if ($plan->isPublished() && $plan->trello_board_id) {
            try {
                $kanban = $kanbanFactory->make($plan->settings['kanban_provider'] ?? 'trello');

                foreach ($todayTasks as $task) {
                    if ($task->trello_card_id) {
                        $kanban->moveCard($task->trello_card_id, 'Hoy');
                    }
                }

                Log::info('DailyRunJob: moved cards to Hoy list.', [
                    'plan_id' => $plan->id,
                    'cards_moved' => $todayTasks->count(),
                ]);
            } catch (\Throwable $e) {
                Log::error('DailyRunJob: failed to move Trello cards', [
                    'plan_id' => $plan->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Send daily email if configured
        $emailEnabled = $plan->settings['reminders']['email'] ?? false;
        $emailTo = config('dailypro.reminder_email');

        if ($emailEnabled && $emailTo && $todayTasks->isNotEmpty()) {
            try {
                Mail::to($emailTo)->send(new DailyPlanMail($plan, $todayTasks, $today));

                Log::info('DailyRunJob: daily email sent.', [
                    'plan_id' => $plan->id,
                    'to' => $emailTo,
                ]);
            } catch (\Throwable $e) {
                Log::error('DailyRunJob: failed to send email', [
                    'plan_id' => $plan->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
