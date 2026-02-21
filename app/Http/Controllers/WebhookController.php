<?php

namespace App\Http\Controllers;

use App\Application\Services\PlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        private readonly PlanService $planService,
    ) {}

    /**
     * POST /api/webhooks/trello - Handle Trello webhook callbacks.
     */
    public function trello(Request $request): JsonResponse
    {
        // Trello sends HEAD to verify webhook URL
        if ($request->isMethod('HEAD')) {
            return response()->json([], 200);
        }

        // Validate webhook secret (MVP: simple token check)
        $secret = config('dailypro.trello.webhook_secret');
        if ($secret && $request->header('X-Trello-Webhook-Secret') !== $secret) {
            // Also check query param for simpler setups
            if ($request->query('token') !== $secret) {
                Log::warning('Invalid Trello webhook secret');

                return response()->json([
                    'type' => 'authentication_error',
                    'title' => 'Invalid webhook secret',
                ], 401);
            }
        }

        $action = $request->input('action');

        if (! $action) {
            return response()->json(['status' => 'ignored', 'reason' => 'no_action'], 200);
        }

        // Only handle card moves to "Hecho"
        $actionType = $action['type'] ?? '';
        $listAfter = $action['data']['listAfter']['name'] ?? null;
        $cardId = $action['data']['card']['id'] ?? null;

        if ($actionType === 'updateCard' && $listAfter === 'Hecho' && $cardId) {
            $updated = $this->planService->markTaskDoneByCardId($cardId);

            Log::info('Trello webhook: card moved to Hecho', [
                'card_id' => $cardId,
                'task_updated' => $updated,
            ]);

            return response()->json([
                'status' => 'processed',
                'task_updated' => $updated,
            ], 200);
        }

        return response()->json(['status' => 'ignored', 'reason' => 'irrelevant_action'], 200);
    }
}
