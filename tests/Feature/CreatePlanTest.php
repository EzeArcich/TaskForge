<?php

namespace Tests\Feature;

use App\Application\Contracts\AiNormalizerInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeAiNormalizer;
use Tests\TestCase;

class CreatePlanTest extends TestCase
{
    use RefreshDatabase;

    private FakeAiNormalizer $fakeAi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeAi = new FakeAiNormalizer();
        $this->app->instance(AiNormalizerInterface::class, $this->fakeAi);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'plan_text' => 'Learn Laravel in 4 weeks: routing, controllers, Eloquent, testing, deployment.',
            'settings' => [
                'timezone' => 'America/Argentina/Buenos_Aires',
                'start_date' => '2025-02-01',
                'availability' => [
                    ['day' => 'mon', 'start' => '20:00', 'end' => '21:30'],
                    ['day' => 'wed', 'start' => '20:00', 'end' => '21:30'],
                ],
                'hours_per_week' => 7.5,
                'kanban_provider' => 'trello',
                'calendar_provider' => 'google',
                'reminders' => ['email' => true],
            ],
        ], $overrides);
    }

    public function test_creates_plan_successfully(): void
    {
        $response = $this->postJson('/api/plans', $this->validPayload());

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'hash',
                    'normalized_json',
                    'schedule',
                    'validation_status',
                    'publish_status',
                    'weeks',
                    'created_at',
                ],
            ])
            ->assertJsonPath('data.validation_status', 'valid')
            ->assertJsonPath('data.publish_status', 'draft');

        $this->assertDatabaseCount('plans', 1);
        $this->assertDatabaseCount('plan_weeks', 2);
        $this->assertDatabaseCount('plan_tasks', 4);
    }

    public function test_idempotent_same_payload_returns_existing(): void
    {
        $payload = $this->validPayload();

        $first = $this->postJson('/api/plans', $payload);
        $first->assertStatus(201);

        $second = $this->postJson('/api/plans', $payload);
        $second->assertStatus(200);

        $this->assertEquals(
            $first->json('data.id'),
            $second->json('data.id')
        );

        $this->assertDatabaseCount('plans', 1);
    }

    public function test_different_text_creates_new_plan(): void
    {
        $this->postJson('/api/plans', $this->validPayload())->assertStatus(201);

        $payload = $this->validPayload(['plan_text' => 'A completely different plan about machine learning']);
        $this->postJson('/api/plans', $payload)->assertStatus(201);

        $this->assertDatabaseCount('plans', 2);
    }

    public function test_validates_missing_plan_text(): void
    {
        $payload = $this->validPayload();
        unset($payload['plan_text']);

        $response = $this->postJson('/api/plans', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['plan_text']);
    }

    public function test_validates_short_plan_text(): void
    {
        $payload = $this->validPayload(['plan_text' => 'short']);

        $response = $this->postJson('/api/plans', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['plan_text']);
    }

    public function test_validates_missing_settings(): void
    {
        $payload = ['plan_text' => 'Learn Laravel in 4 weeks with lots of detail'];

        $response = $this->postJson('/api/plans', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['settings']);
    }

    public function test_validates_missing_timezone(): void
    {
        $payload = $this->validPayload();
        unset($payload['settings']['timezone']);

        $response = $this->postJson('/api/plans', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['settings.timezone']);
    }

    public function test_validates_invalid_timezone(): void
    {
        $payload = $this->validPayload();
        $payload['settings']['timezone'] = 'Not/A/Timezone';

        $response = $this->postJson('/api/plans', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['settings.timezone']);
    }

    public function test_validates_missing_availability(): void
    {
        $payload = $this->validPayload();
        unset($payload['settings']['availability']);

        $response = $this->postJson('/api/plans', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['settings.availability']);
    }

    public function test_validates_invalid_day_in_availability(): void
    {
        $payload = $this->validPayload();
        $payload['settings']['availability'] = [
            ['day' => 'invalid_day', 'start' => '20:00', 'end' => '21:30'],
        ];

        $response = $this->postJson('/api/plans', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['settings.availability.0.day']);
    }

    public function test_validates_invalid_time_format(): void
    {
        $payload = $this->validPayload();
        $payload['settings']['availability'] = [
            ['day' => 'mon', 'start' => '8pm', 'end' => '21:30'],
        ];

        $response = $this->postJson('/api/plans', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['settings.availability.0.start']);
    }

    public function test_returns_422_when_ai_normalization_fails(): void
    {
        $this->fakeAi->shouldFail();

        $response = $this->postJson('/api/plans', $this->validPayload());

        $response->assertStatus(422)
            ->assertJsonPath('type', 'normalization_error');
    }

    public function test_schedule_contains_warnings_on_overflow(): void
    {
        $this->fakeAi->setResponse([
            'title' => 'Heavy plan',
            'timezone' => 'UTC',
            'start_date' => '2025-02-01',
            'weeks' => [
                [
                    'week' => 1,
                    'goal' => 'Do everything',
                    'tasks' => [
                        ['title' => 'Massive task', 'estimate_hours' => 40],
                    ],
                ],
            ],
        ]);

        $payload = $this->validPayload();
        $payload['settings']['hours_per_week'] = 3;

        $response = $this->postJson('/api/plans', $payload);

        $response->assertStatus(201);
        $this->assertNotEmpty($response->json('data.schedule.warnings'));
    }
}
