<?php

namespace Tests\Feature;

use App\Application\Contracts\AiNormalizerInterface;
use App\Application\Contracts\CalendarProviderInterface;
use App\Application\Contracts\KanbanProviderInterface;
use App\Infrastructure\Calendar\GoogleCalendarProvider;
use App\Infrastructure\Kanban\TrelloProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeAiNormalizer;
use Tests\Fakes\FakeCalendarProvider;
use Tests\Fakes\FakeKanbanProvider;
use Tests\TestCase;

class PublishPlanTest extends TestCase
{
    use RefreshDatabase;

    private FakeKanbanProvider $fakeKanban;
    private FakeCalendarProvider $fakeCalendar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(AiNormalizerInterface::class, new FakeAiNormalizer());

        $this->fakeKanban = new FakeKanbanProvider();
        $this->fakeCalendar = new FakeCalendarProvider();
        $this->app->instance(KanbanProviderInterface::class, $this->fakeKanban);
        $this->app->instance(CalendarProviderInterface::class, $this->fakeCalendar);
        // Also bind concrete classes so the factory resolves our fakes
        $this->app->instance(TrelloProvider::class, $this->fakeKanban);
        $this->app->instance(GoogleCalendarProvider::class, $this->fakeCalendar);
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
                    ['day' => 'wed', 'start' => '20:00', 'end' => '21:30'],
                ],
                'hours_per_week' => 7.5,
                'kanban_provider' => 'trello',
                'calendar_provider' => 'google',
            ],
        ]);

        return $response->json('data.id');
    }

    public function test_publishes_plan_successfully(): void
    {
        $planId = $this->createPlan();

        $response = $this->postJson("/api/plans/{$planId}/publish");

        $response->assertStatus(200)
            ->assertJsonPath('data.publish_status', 'published')
            ->assertJsonPath('data.publication.trello.published', true)
            ->assertJsonPath('data.publication.google_calendar.published', true);

        $this->assertCount(1, $this->fakeKanban->createdBoards);
        $this->assertCount(1, $this->fakeCalendar->createdEvents);
    }

    public function test_publish_is_idempotent(): void
    {
        $planId = $this->createPlan();

        $this->postJson("/api/plans/{$planId}/publish")->assertStatus(200);
        $this->postJson("/api/plans/{$planId}/publish")->assertStatus(200);

        // Should only create board once
        $this->assertCount(1, $this->fakeKanban->createdBoards);
        $this->assertCount(1, $this->fakeCalendar->createdEvents);
    }

    public function test_publish_returns_404_for_nonexistent_plan(): void
    {
        $response = $this->postJson('/api/plans/99999/publish');

        $response->assertStatus(404);
    }

    public function test_publish_returns_502_when_kanban_fails(): void
    {
        $this->fakeKanban->shouldFail = true;

        $planId = $this->createPlan();
        $response = $this->postJson("/api/plans/{$planId}/publish");

        $response->assertStatus(502)
            ->assertJsonPath('type', 'publish_error');
    }

    public function test_publish_persists_external_ids(): void
    {
        $planId = $this->createPlan();

        $this->postJson("/api/plans/{$planId}/publish");

        $this->assertDatabaseHas('plans', [
            'id' => $planId,
            'trello_board_id' => 'fake_board_' . $planId,
            'google_calendar_id' => 'primary',
        ]);
    }

    public function test_publish_stores_trello_card_ids_on_tasks(): void
    {
        $planId = $this->createPlan();

        $this->postJson("/api/plans/{$planId}/publish");

        $this->assertDatabaseMissing('plan_tasks', [
            'plan_id' => $planId,
            'trello_card_id' => null,
        ]);
    }
}
