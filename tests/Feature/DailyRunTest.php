<?php

namespace Tests\Feature;

use App\Application\Contracts\AiNormalizerInterface;
use App\Application\Contracts\KanbanProviderInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeAiNormalizer;
use Tests\Fakes\FakeKanbanProvider;
use Tests\TestCase;

class DailyRunTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(AiNormalizerInterface::class, new FakeAiNormalizer());
        $this->app->instance(KanbanProviderInterface::class, new FakeKanbanProvider());
    }

    private function createPlan(): int
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
                'reminders' => ['email' => true],
            ],
        ]);

        return $response->json('data.id');
    }

    public function test_daily_run_returns_today_tasks(): void
    {
        $planId = $this->createPlan();

        $response = $this->postJson("/api/plans/{$planId}/daily-run");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'plan_id',
                'date',
                'today_tasks',
            ])
            ->assertJsonPath('plan_id', $planId);
    }

    public function test_daily_run_returns_404_for_nonexistent_plan(): void
    {
        $response = $this->postJson('/api/plans/99999/daily-run');

        $response->assertStatus(404);
    }
}
