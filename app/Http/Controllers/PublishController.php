<?php

namespace App\Http\Controllers;

use App\Application\Services\PlanService;
use App\Exceptions\PublishFailedException;
use App\Http\Resources\PlanResource;
use App\Infrastructure\Calendar\CalendarProviderFactory;
use App\Infrastructure\Kanban\KanbanProviderFactory;
use App\Jobs\PublishPlanJob;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublishController extends Controller
{
    public function __construct(
        private readonly PlanService $planService,
        private readonly KanbanProviderFactory $kanbanFactory,
        private readonly CalendarProviderFactory $calendarFactory,
    ) {}

    /**
     * POST /api/plans/{plan}/publish - Publish plan to Kanban + Calendar.
     */
    public function __invoke(Plan $plan, Request $request): JsonResponse
    {
        $plan->load(['weeks.tasks']);

        // Idempotent: already published
        if ($plan->isPublished()) {
            return (new PlanResource($plan))
                ->response()
                ->setStatusCode(200);
        }

        $async = $request->boolean('async', false);

        if ($async) {
            PublishPlanJob::dispatch($plan->id);

            return response()->json([
                'message' => 'Plan publish queued.',
                'plan_id' => $plan->id,
                'status' => 'publishing',
            ], 202);
        }

        try {
            $kanbanProvider = $this->kanbanFactory->make(
                $plan->settings['kanban_provider'] ?? 'trello'
            );
            $calendarProvider = $this->calendarFactory->make(
                $plan->settings['calendar_provider'] ?? 'google'
            );

            $plan = $this->planService->publishPlan($plan, $kanbanProvider, $calendarProvider);

            return (new PlanResource($plan))
                ->response()
                ->setStatusCode(200);
        } catch (PublishFailedException $e) {
            return response()->json([
                'type' => 'publish_error',
                'title' => 'Publish Failed',
                'detail' => $e->getMessage(),
                'provider' => $e->provider,
            ], 502);
        } catch (\Throwable $e) {
            return response()->json([
                'type' => 'publish_error',
                'title' => 'Publish Failed',
                'detail' => $e->getMessage(),
            ], 502);
        }
    }
}
