<?php

namespace Tests\Feature;

use App\Application\Contracts\AiNormalizerInterface;
use App\Domain\Enums\TaskStatus;
use App\Models\Plan;
use App\Models\PlanTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeAiNormalizer;
use Tests\TestCase;

class TrelloWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(AiNormalizerInterface::class, new FakeAiNormalizer());
    }

    private function createPlanWithCardIds(): Plan
    {
        $response = $this->postJson('/api/plans', [
            'plan_text' => 'Learn Laravel in 4 weeks with routing, controllers, Eloquent, testing.',
            'settings' => [
                'timezone' => 'UTC',
                'start_date' => '2025-02-01',
                'availability' => [
                    ['day' => 'mon', 'start' => '20:00', 'end' => '21:30'],
                ],
                'hours_per_week' => 7.5,
            ],
        ]);

        $plan = Plan::find($response->json('data.id'));

        // Simulate published state with card IDs
        $plan->tasks->each(function (PlanTask $task, int $index) {
            $task->update(['trello_card_id' => 'card_' . ($index + 1)]);
        });

        return $plan->fresh(['tasks']);
    }

    public function test_head_request_returns_200(): void
    {
        $response = $this->call('HEAD', '/api/webhooks/trello');
        $response->assertStatus(200);
    }

    public function test_marks_task_done_when_card_moved_to_hecho(): void
    {
        $plan = $this->createPlanWithCardIds();
        $cardId = $plan->tasks->first()->trello_card_id;

        $response = $this->postJson('/api/webhooks/trello', [
            'action' => [
                'type' => 'updateCard',
                'data' => [
                    'card' => ['id' => $cardId],
                    'listAfter' => ['name' => 'Hecho'],
                    'listBefore' => ['name' => 'Hoy'],
                ],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'processed')
            ->assertJsonPath('task_updated', true);

        $this->assertDatabaseHas('plan_tasks', [
            'trello_card_id' => $cardId,
            'status' => TaskStatus::Done->value,
        ]);
    }

    public function test_idempotent_same_webhook_does_not_update_twice(): void
    {
        $plan = $this->createPlanWithCardIds();
        $cardId = $plan->tasks->first()->trello_card_id;

        $payload = [
            'action' => [
                'type' => 'updateCard',
                'data' => [
                    'card' => ['id' => $cardId],
                    'listAfter' => ['name' => 'Hecho'],
                    'listBefore' => ['name' => 'Hoy'],
                ],
            ],
        ];

        $first = $this->postJson('/api/webhooks/trello', $payload);
        $first->assertJsonPath('task_updated', true);

        $second = $this->postJson('/api/webhooks/trello', $payload);
        $second->assertJsonPath('task_updated', false);
    }

    public function test_ignores_irrelevant_actions(): void
    {
        $response = $this->postJson('/api/webhooks/trello', [
            'action' => [
                'type' => 'createCard',
                'data' => [
                    'card' => ['id' => 'some_card'],
                ],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'ignored');
    }

    public function test_ignores_moves_to_non_hecho_list(): void
    {
        $plan = $this->createPlanWithCardIds();
        $cardId = $plan->tasks->first()->trello_card_id;

        $response = $this->postJson('/api/webhooks/trello', [
            'action' => [
                'type' => 'updateCard',
                'data' => [
                    'card' => ['id' => $cardId],
                    'listAfter' => ['name' => 'Esta semana'],
                    'listBefore' => ['name' => 'Backlog'],
                ],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'ignored');
    }

    public function test_handles_unknown_card_id_gracefully(): void
    {
        $response = $this->postJson('/api/webhooks/trello', [
            'action' => [
                'type' => 'updateCard',
                'data' => [
                    'card' => ['id' => 'unknown_card_999'],
                    'listAfter' => ['name' => 'Hecho'],
                    'listBefore' => ['name' => 'Hoy'],
                ],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('task_updated', false);
    }

    public function test_rejects_invalid_webhook_secret(): void
    {
        config(['dailypro.trello.webhook_secret' => 'my-secret-token']);

        $response = $this->postJson('/api/webhooks/trello', [
            'action' => [
                'type' => 'updateCard',
                'data' => [
                    'card' => ['id' => 'card_1'],
                    'listAfter' => ['name' => 'Hecho'],
                ],
            ],
        ]);

        $response->assertStatus(401);
    }

    public function test_accepts_valid_webhook_secret_via_query(): void
    {
        config(['dailypro.trello.webhook_secret' => 'my-secret-token']);

        $plan = $this->createPlanWithCardIds();
        $cardId = $plan->tasks->first()->trello_card_id;

        $response = $this->postJson('/api/webhooks/trello?token=my-secret-token', [
            'action' => [
                'type' => 'updateCard',
                'data' => [
                    'card' => ['id' => $cardId],
                    'listAfter' => ['name' => 'Hecho'],
                    'listBefore' => ['name' => 'Hoy'],
                ],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('task_updated', true);
    }

    public function test_handles_empty_body(): void
    {
        $response = $this->postJson('/api/webhooks/trello', []);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'ignored');
    }
}
