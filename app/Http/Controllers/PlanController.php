<?php

namespace App\Http\Controllers;

use App\Application\DTOs\CreatePlanDTO;
use App\Application\Services\PlanService;
use App\Exceptions\NormalizationFailedException;
use App\Http\Requests\CreatePlanRequest;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;

class PlanController extends Controller
{
    public function __construct(
        private readonly PlanService $planService,
    ) {}

    /**
     * POST /api/plans - Create a new plan (idempotent).
     */
    public function store(CreatePlanRequest $request): JsonResponse
    {
        try {
            $dto = CreatePlanDTO::fromRequest($request->validated());
            $result = $this->planService->createPlan($dto);

            $statusCode = $result['existed'] ? 200 : 201;

            return (new PlanResource($result['plan']))
                ->response()
                ->setStatusCode($statusCode);
        } catch (NormalizationFailedException $e) {
            return response()->json([
                'type' => 'normalization_error',
                'title' => 'Plan Normalization Failed',
                'detail' => $e->getMessage(),
                'errors' => $e->errors,
            ], 422);
        }
    }

    /**
     * GET /api/plans/{plan} - Get plan details.
     */
    public function show(Plan $plan): PlanResource
    {
        $plan->load(['weeks.tasks']);

        return new PlanResource($plan);
    }
}
