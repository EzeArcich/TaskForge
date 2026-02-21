<?php

namespace Tests\Feature;

use App\Application\Contracts\AiNormalizerInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeAiNormalizer;
use Tests\TestCase;

class ShowPlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(AiNormalizerInterface::class, new FakeAiNormalizer());
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
            ],
        ]);

        return $response->json('data.id');
    }

    public function test_returns_plan_with_full_structure(): void
    {
        $planId = $this->createPlan();

        $response = $this->getJson("/api/plans/{$planId}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'hash',
                    'settings',
                    'normalized_json',
                    'schedule',
                    'validation_status',
                    'publish_status',
                    'publication' => [
                        'trello' => ['published', 'board_id', 'board_url'],
                        'google_calendar' => ['published', 'calendar_id'],
                    ],
                    'weeks',
                ],
            ]);
    }

    public function test_shows_publication_status(): void
    {
        $planId = $this->createPlan();

        $response = $this->getJson("/api/plans/{$planId}");

        $response->assertJsonPath('data.publication.trello.published', false);
        $response->assertJsonPath('data.publication.google_calendar.published', false);
    }

    public function test_returns_404_for_nonexistent_plan(): void
    {
        $response = $this->getJson('/api/plans/99999');

        $response->assertStatus(404);
    }
}
