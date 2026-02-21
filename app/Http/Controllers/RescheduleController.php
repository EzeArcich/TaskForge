<?php

namespace App\Http\Controllers;

use App\Application\DTOs\RescheduleDTO;
use App\Application\Services\PlanService;
use App\Http\Requests\RescheduleRequest;
use App\Http\Resources\PlanResource;
use App\Models\Plan;

class RescheduleController extends Controller
{
    public function __construct(
        private readonly PlanService $planService,
    ) {}

    /**
     * POST /api/plans/{plan}/reschedule - Reschedule plan tasks.
     */
    public function __invoke(Plan $plan, RescheduleRequest $request): PlanResource
    {
        $dto = RescheduleDTO::fromRequest($request->validated());
        $plan = $this->planService->reschedulePlan($plan, $dto);

        return new PlanResource($plan);
    }
}
