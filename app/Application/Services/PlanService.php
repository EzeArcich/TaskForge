<?php

namespace App\Application\Services;

use App\Application\Contracts\AiNormalizerInterface;
use App\Application\Contracts\CalendarProviderInterface;
use App\Application\Contracts\KanbanProviderInterface;
use App\Application\DTOs\AvailabilitySlotDTO;
use App\Application\DTOs\CreatePlanDTO;
use App\Application\DTOs\RescheduleDTO;
use App\Domain\Enums\PlanStatus;
use App\Domain\Enums\TaskStatus;
use App\Domain\Enums\ValidationStatus;
use App\Exceptions\NormalizationFailedException;
use App\Models\Plan;
use App\Models\PlanTask;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PlanService
{
    public function __construct(
        private readonly PlanTextHasher $hasher,
        private readonly NormalizedPlanValidator $validator,
        private readonly SchedulerService $scheduler,
        private readonly AiNormalizerInterface $normalizer,
    ) {}

    /**
     * Create a new plan or return existing one (idempotent).
     *
     * @return array{plan: Plan, existed: bool}
     */
    public function createPlan(CreatePlanDTO $dto): array
    {
        $settings = $dto->toSettingsArray();
        $hash = $this->hasher->hash($dto->planText, $settings);

        $existing = Plan::where('hash', $hash)->first();
        if ($existing) {
            $existing->load(['weeks.tasks']);
            return ['plan' => $existing, 'existed' => true];
        }

        // Normalize with AI (retry up to 2 times on validation failure)
        $normalizedJson = $this->normalizeWithRetries($dto->planText, $dto->timezone, $dto->startDate);

        // Schedule tasks
        $occupiedSlotsByDate = $this->getOccupiedSlotsByDate(
            $dto->startDate,
            $dto->timezone,
            $normalizedJson,
        );

        $scheduleResult = $this->scheduler->schedule(
            $normalizedJson,
            $dto->availability,
            $dto->startDate,
            $dto->timezone,
            $dto->hoursPerWeek,
            $dto->maxMinutesPerDay,
            $occupiedSlotsByDate,
        );

        // Persist everything in a transaction
        $plan = DB::transaction(function () use ($hash, $dto, $settings, $normalizedJson, $scheduleResult) {
            $plan = Plan::create([
                'hash' => $hash,
                'plan_text' => $dto->planText,
                'settings' => $settings,
                'normalized_json' => $normalizedJson,
                'schedule' => $scheduleResult,
                'validation_status' => ValidationStatus::Valid->value,
                'publish_status' => PlanStatus::Draft->value,
            ]);

            $this->persistWeeksAndTasks($plan, $normalizedJson, $scheduleResult);

            return $plan;
        });

        $plan->load(['weeks.tasks']);

        return ['plan' => $plan, 'existed' => false];
    }

    /**
     * Publish plan to Kanban + Calendar.
     */
    public function publishPlan(
        Plan $plan,
        KanbanProviderInterface $kanban,
        CalendarProviderInterface $calendar,
    ): Plan {
        if ($plan->isPublished()) {
            Log::info('Plan already published, returning current state.', ['plan_id' => $plan->id]);
            return $plan;
        }

        $plan->update(['publish_status' => PlanStatus::Publishing->value]);

        try {
            // Kanban
            if (! $plan->trello_board_id) {
                $kanbanResult = $kanban->createBoard($plan);
                $plan->update([
                    'trello_board_id' => $kanbanResult['board_id'],
                    'trello_board_url' => $kanbanResult['board_url'],
                ]);

                // Update task card IDs
                foreach ($kanbanResult['card_ids'] as $taskId => $cardId) {
                    PlanTask::where('id', $taskId)->update(['trello_card_id' => $cardId]);
                }
            } else {
                // Plan already connected to Trello: refresh due dates after reschedule/updates.
                $kanban->updateCardDueDates($plan);
            }

            // Calendar
            if (! $plan->google_calendar_id) {
                $calendarResult = $calendar->createEvents($plan);
                $plan->update([
                    'google_calendar_id' => $calendarResult['calendar_id'],
                ]);

                foreach ($calendarResult['event_ids'] as $taskId => $eventId) {
                    PlanTask::where('id', $taskId)->update(['google_event_id' => $eventId]);
                }
            } else {
                // Plan already connected to Calendar: refresh event datetimes after reschedule/updates.
                $calendar->updateEvents($plan);
            }

            $plan->update(['publish_status' => PlanStatus::Published->value]);
        } catch (\Throwable $e) {
            $plan->update(['publish_status' => PlanStatus::Draft->value]);
            Log::error('Publish failed', ['plan_id' => $plan->id, 'error' => $e->getMessage()]);
            throw $e;
        }

        return $plan->fresh(['weeks.tasks']);
    }

    /**
     * Reschedule a plan with new availability.
     */
    public function reschedulePlan(Plan $plan, RescheduleDTO $dto): Plan
    {
        $normalizedJson = $plan->normalized_json;
        $settings = $plan->settings;
        $timezone = $settings['timezone'] ?? 'UTC';
        $maxMinutesPerDay = $dto->maxMinutesPerDay ?: (int) ($settings['max_minutes_per_day'] ?? config('dailypro.scheduler.default_max_minutes_per_day', 60));

        $occupiedSlotsByDate = $this->getOccupiedSlotsByDate(
            $dto->startDate,
            $timezone,
            $normalizedJson,
            $plan->id,
        );

        $scheduleResult = $this->scheduler->schedule(
            $normalizedJson,
            $dto->availability,
            $dto->startDate,
            $timezone,
            $dto->hoursPerWeek,
            $maxMinutesPerDay,
            $occupiedSlotsByDate,
        );

        // Update settings
        $settings['availability'] = array_map(fn (AvailabilitySlotDTO $s) => $s->toArray(), $dto->availability);
        $settings['start_date'] = $dto->startDate;
        $settings['hours_per_week'] = $dto->hoursPerWeek;
        $settings['max_minutes_per_day'] = $maxMinutesPerDay;

        DB::transaction(function () use ($plan, $settings, $scheduleResult) {
            $plan->update([
                'settings' => $settings,
                'schedule' => $scheduleResult,
                'publish_status' => $plan->isPublished()
                    ? PlanStatus::NeedsUpdate->value
                    : $plan->publish_status->value,
            ]);

            // Update scheduled dates on tasks from new schedule
            $this->updateTaskSchedules($plan, $scheduleResult);
        });

        // Recompute hash since settings changed
        $newHash = $this->hasher->hash($plan->plan_text, $settings);
        $plan->update(['hash' => $newHash]);

        return $plan->fresh(['weeks.tasks']);
    }

    /**
     * Mark a task as done by its Trello card ID.
     *
     * @return bool Whether the task was found and updated
     */
    public function markTaskDoneByCardId(string $cardId): bool
    {
        $task = PlanTask::where('trello_card_id', $cardId)->first();
        if (! $task || $task->isDone()) {
            return false;
        }

        $task->update(['status' => TaskStatus::Done->value]);
        Log::info('Task marked done via webhook', ['task_id' => $task->id, 'card_id' => $cardId]);

        return true;
    }

    /**
     * Get tasks scheduled for today.
     */
    public function getTodayTasks(Plan $plan, string $timezone): array
    {
        $today = now($timezone)->format('Y-m-d');

        return $plan->tasks()
            ->where('scheduled_date', $today)
            ->where('status', '!=', TaskStatus::Done->value)
            ->orderBy('scheduled_start')
            ->get()
            ->toArray();
    }

    private function normalizeWithRetries(string $planText, string $timezone, string $startDate, int $maxRetries = 2): array
    {
        $lastErrors = [];

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            try {
                $result = $this->normalizer->normalize($planText, $timezone, $startDate);
                $validated = $this->validator->validate($result);

                return $validated;
            } catch (\Illuminate\Validation\ValidationException $e) {
                $lastErrors = $e->errors();
                Log::warning('AI normalization validation failed', [
                    'attempt' => $attempt + 1,
                    'errors' => $lastErrors,
                ]);
            } catch (\Throwable $e) {
                $lastErrors = ['ai' => [$e->getMessage()]];
                Log::error('AI normalization error', [
                    'attempt' => $attempt + 1,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        throw new NormalizationFailedException(
            'AI normalization failed after ' . ($maxRetries + 1) . ' attempts.',
            $lastErrors,
        );
    }

    private function persistWeeksAndTasks(Plan $plan, array $normalizedJson, array $scheduleResult): void
    {
        $slotsByWeekAndTask = collect($scheduleResult['slots'] ?? [])
            ->groupBy(fn (array $slot) => $this->slotKey((int) ($slot['week'] ?? 0), (string) ($slot['task_title'] ?? '')));

        foreach ($normalizedJson['weeks'] as $weekData) {
            $week = $plan->weeks()->create([
                'week_number' => $weekData['week'],
                'goal' => $weekData['goal'],
            ]);

            foreach ($weekData['tasks'] as $taskData) {
                $taskSlots = $slotsByWeekAndTask->get(
                    $this->slotKey((int) $weekData['week'], (string) $taskData['title']),
                    collect()
                );
                $firstSlot = $taskSlots->first();

                $plan->tasks()->create([
                    'plan_week_id' => $week->id,
                    'title' => $taskData['title'],
                    'estimate_hours' => $taskData['estimate_hours'],
                    'status' => TaskStatus::Pending->value,
                    'scheduled_date' => $firstSlot['date'] ?? null,
                    'scheduled_start' => $firstSlot ? $firstSlot['start'] : null,
                    'scheduled_end' => $firstSlot ? $firstSlot['end'] : null,
                ]);
            }
        }
    }

    private function updateTaskSchedules(Plan $plan, array $scheduleResult): void
    {
        $slotsByWeekAndTask = collect($scheduleResult['slots'] ?? [])
            ->groupBy(fn (array $slot) => $this->slotKey((int) ($slot['week'] ?? 0), (string) ($slot['task_title'] ?? '')));
        $weekNumberById = $plan->weeks()->pluck('week_number', 'id');

        foreach ($plan->tasks as $task) {
            $weekNumber = (int) ($weekNumberById[$task->plan_week_id] ?? 0);
            $taskSlots = $slotsByWeekAndTask->get(
                $this->slotKey($weekNumber, (string) $task->title),
                collect()
            );
            $firstSlot = $taskSlots->first();

            $task->update([
                'scheduled_date' => $firstSlot['date'] ?? null,
                'scheduled_start' => $firstSlot ? $firstSlot['start'] : null,
                'scheduled_end' => $firstSlot ? $firstSlot['end'] : null,
            ]);
        }
    }

    private function slotKey(int $week, string $taskTitle): string
    {
        return $week . '|' . $taskTitle;
    }

    /**
     * Build occupied time intervals from existing tasks in DB.
     *
     * @return array<string, array<int, array{start: int, end: int}>>
     */
    private function getOccupiedSlotsByDate(
        string $startDate,
        string $timezone,
        array $normalizedJson,
        ?int $excludePlanId = null,
    ): array {
        $maxWeek = (int) collect($normalizedJson['weeks'] ?? [])->max('week');
        if ($maxWeek <= 0) {
            return [];
        }

        $rangeStart = CarbonImmutable::parse($startDate, $timezone)->startOfWeek(\Carbon\Carbon::MONDAY);
        $rangeEnd = $rangeStart->addWeeks($maxWeek)->subDay();

        $tasksQuery = PlanTask::query()
            ->whereNotNull('scheduled_date')
            ->whereNotNull('scheduled_start')
            ->whereNotNull('scheduled_end')
            ->where('status', '!=', TaskStatus::Done->value)
            ->whereBetween('scheduled_date', [$rangeStart->toDateString(), $rangeEnd->toDateString()]);

        if ($excludePlanId) {
            $tasksQuery->where('plan_id', '!=', $excludePlanId);
        }

        $tasks = $tasksQuery->get(['scheduled_date', 'scheduled_start', 'scheduled_end']);

        $occupied = [];
        foreach ($tasks as $task) {
            $date = $task->scheduled_date->format('Y-m-d');
            $start = $this->timeToMinutes((string) $task->scheduled_start);
            $end = $this->timeToMinutes((string) $task->scheduled_end);
            if ($end <= $start) {
                continue;
            }
            $occupied[$date][] = ['start' => $start, 'end' => $end];
        }

        return $occupied;
    }

    private function timeToMinutes(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', substr($time, 0, 5)));
        return ($hours * 60) + $minutes;
    }
}
