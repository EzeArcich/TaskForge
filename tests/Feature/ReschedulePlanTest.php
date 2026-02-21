<?php

namespace Tests\Feature;

use App\Application\Contracts\AiNormalizerInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeAiNormalizer;
use Tests\TestCase;

class ReschedulePlanTest extends TestCase
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
                'hours_per_week' => 3,
            ],
        ]);

        return $response->json('data.id');
    }

    public function test_reschedules_with_new_availability(): void
    {
        $planId = $this->createPlan();

        $response = $this->postJson("/api/plans/{$planId}/reschedule", [
            'availability' => [
                ['day' => 'mon', 'start' => '18:00', 'end' => '20:00'],
                ['day' => 'tue', 'start' => '18:00', 'end' => '20:00'],
                ['day' => 'thu', 'start' => '18:00', 'end' => '20:00'],
            ],
            'start_date' => '2025-03-01',
            'hours_per_week' => 10,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['id', 'schedule', 'settings'],
            ]);

        // Verify settings were updated
        $this->assertEquals(10, $response->json('data.settings.hours_per_week'));
        $this->assertEquals('2025-03-01', $response->json('data.settings.start_date'));
        $this->assertCount(3, $response->json('data.settings.availability'));
    }

    public function test_reschedule_marks_published_plan_as_needs_update(): void
    {
        $planId = $this->createPlan();

        // Manually mark as published
        \App\Models\Plan::find($planId)->update([
            'publish_status' => 'published',
            'trello_board_id' => 'fake_board',
        ]);

        $response = $this->postJson("/api/plans/{$planId}/reschedule", [
            'availability' => [
                ['day' => 'fri', 'start' => '09:00', 'end' => '12:00'],
            ],
            'start_date' => '2025-03-01',
            'hours_per_week' => 5,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.publish_status', 'needs_update');
    }

    public function test_reschedule_validates_input(): void
    {
        $planId = $this->createPlan();

        $response = $this->postJson("/api/plans/{$planId}/reschedule", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['availability', 'start_date', 'hours_per_week']);
    }

    public function test_reschedule_validates_day_names(): void
    {
        $planId = $this->createPlan();

        $response = $this->postJson("/api/plans/{$planId}/reschedule", [
            'availability' => [
                ['day' => 'invalid', 'start' => '20:00', 'end' => '21:30'],
            ],
            'start_date' => '2025-03-01',
            'hours_per_week' => 5,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['availability.0.day']);
    }

    public function test_reschedule_returns_404_for_nonexistent_plan(): void
    {
        $response = $this->postJson('/api/plans/99999/reschedule', [
            'availability' => [
                ['day' => 'mon', 'start' => '20:00', 'end' => '21:30'],
            ],
            'start_date' => '2025-03-01',
            'hours_per_week' => 5,
        ]);

        $response->assertStatus(404);
    }
}
