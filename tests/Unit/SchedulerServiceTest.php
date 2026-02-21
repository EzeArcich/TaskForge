<?php

namespace Tests\Unit;

use App\Application\DTOs\AvailabilitySlotDTO;
use App\Application\Services\SchedulerService;
use PHPUnit\Framework\TestCase;

class SchedulerServiceTest extends TestCase
{
    private SchedulerService $scheduler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scheduler = new SchedulerService();
    }

    private function samplePlan(): array
    {
        return [
            'title' => 'Test Plan',
            'timezone' => 'UTC',
            'start_date' => '2025-01-20',
            'weeks' => [
                [
                    'week' => 1,
                    'goal' => 'Week 1 goal',
                    'tasks' => [
                        ['title' => 'Task A', 'estimate_hours' => 1.5],
                        ['title' => 'Task B', 'estimate_hours' => 1],
                    ],
                ],
            ],
        ];
    }

    private function availability(): array
    {
        return [
            new AvailabilitySlotDTO('mon', '20:00', '21:30'),
            new AvailabilitySlotDTO('wed', '20:00', '21:30'),
        ];
    }

    public function test_schedules_tasks_in_available_slots(): void
    {
        $result = $this->scheduler->schedule(
            $this->samplePlan(),
            $this->availability(),
            '2025-01-20',
            'UTC',
            7.5,
        );

        $this->assertArrayHasKey('slots', $result);
        $this->assertArrayHasKey('warnings', $result);
        $this->assertNotEmpty($result['slots']);
    }

    public function test_slot_has_required_fields(): void
    {
        $result = $this->scheduler->schedule(
            $this->samplePlan(),
            $this->availability(),
            '2025-01-20',
            'UTC',
            7.5,
        );

        $firstSlot = $result['slots'][0];

        $this->assertArrayHasKey('week', $firstSlot);
        $this->assertArrayHasKey('task_title', $firstSlot);
        $this->assertArrayHasKey('date', $firstSlot);
        $this->assertArrayHasKey('start', $firstSlot);
        $this->assertArrayHasKey('end', $firstSlot);
        $this->assertArrayHasKey('minutes', $firstSlot);
    }

    public function test_respects_availability_days(): void
    {
        $result = $this->scheduler->schedule(
            $this->samplePlan(),
            $this->availability(),
            '2025-01-20',
            'UTC',
            7.5,
        );

        foreach ($result['slots'] as $slot) {
            $dayOfWeek = date('N', strtotime($slot['date']));
            // 1=Mon, 3=Wed
            $this->assertContains((int) $dayOfWeek, [1, 3],
                "Slot scheduled on invalid day: {$slot['date']} (day {$dayOfWeek})");
        }
    }

    public function test_warns_on_overflow(): void
    {
        $plan = $this->samplePlan();
        $plan['weeks'][0]['tasks'] = [
            ['title' => 'Huge task', 'estimate_hours' => 10],
        ];

        $availability = [
            new AvailabilitySlotDTO('mon', '20:00', '21:00'), // only 1h
        ];

        $result = $this->scheduler->schedule(
            $plan,
            $availability,
            '2025-01-20',
            'UTC',
            1.0,
        );

        $overflowWarnings = array_filter($result['warnings'], fn ($w) => $w['type'] === 'overflow');
        $this->assertNotEmpty($overflowWarnings);
    }

    public function test_warns_on_unscheduled_tasks(): void
    {
        $plan = $this->samplePlan();
        $plan['weeks'][0]['tasks'] = [
            ['title' => 'Huge task', 'estimate_hours' => 10],
        ];

        $availability = [
            new AvailabilitySlotDTO('mon', '20:00', '21:00'), // only 1h available
        ];

        $result = $this->scheduler->schedule(
            $plan,
            $availability,
            '2025-01-20',
            'UTC',
            1.0,
        );

        $unscheduledWarnings = array_filter($result['warnings'], fn ($w) => $w['type'] === 'unscheduled');
        $this->assertNotEmpty($unscheduledWarnings);
    }

    public function test_splits_task_across_multiple_slots(): void
    {
        $plan = [
            'title' => 'Test',
            'timezone' => 'UTC',
            'start_date' => '2025-01-20',
            'weeks' => [
                [
                    'week' => 1,
                    'goal' => 'Goal',
                    'tasks' => [
                        ['title' => 'Big task', 'estimate_hours' => 2.5],
                    ],
                ],
            ],
        ];

        $availability = [
            new AvailabilitySlotDTO('mon', '20:00', '21:30'), // 90 min
            new AvailabilitySlotDTO('wed', '20:00', '21:30'), // 90 min
        ];

        $result = $this->scheduler->schedule($plan, $availability, '2025-01-20', 'UTC', 7.5);

        $bigTaskSlots = array_filter($result['slots'], fn ($s) => $s['task_title'] === 'Big task');
        $this->assertGreaterThanOrEqual(2, count($bigTaskSlots), 'Task should be split across slots');
    }

    public function test_handles_empty_availability(): void
    {
        $result = $this->scheduler->schedule(
            $this->samplePlan(),
            [], // no availability
            '2025-01-20',
            'UTC',
            7.5,
        );

        $this->assertEmpty($result['slots']);
        $this->assertNotEmpty($result['warnings']);
    }

    public function test_handles_multiple_weeks(): void
    {
        $plan = [
            'title' => 'Multi-week Plan',
            'timezone' => 'UTC',
            'start_date' => '2025-01-20',
            'weeks' => [
                [
                    'week' => 1,
                    'goal' => 'Week 1',
                    'tasks' => [['title' => 'W1 Task', 'estimate_hours' => 1]],
                ],
                [
                    'week' => 2,
                    'goal' => 'Week 2',
                    'tasks' => [['title' => 'W2 Task', 'estimate_hours' => 1]],
                ],
            ],
        ];

        $result = $this->scheduler->schedule(
            $plan,
            $this->availability(),
            '2025-01-20',
            'UTC',
            7.5,
        );

        $weeks = array_unique(array_column($result['slots'], 'week'));
        $this->assertCount(2, $weeks);
    }

    public function test_total_scheduled_minutes_match_estimate(): void
    {
        $plan = $this->samplePlan();
        $plan['weeks'][0]['tasks'] = [
            ['title' => 'Small Task', 'estimate_hours' => 1],
        ];

        $result = $this->scheduler->schedule(
            $plan,
            $this->availability(),
            '2025-01-20',
            'UTC',
            7.5,
        );

        $totalMinutes = array_sum(array_column($result['slots'], 'minutes'));
        $this->assertEquals(60, $totalMinutes);
    }
}
