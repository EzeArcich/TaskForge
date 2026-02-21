<?php

namespace App\Application\Services;

use App\Application\DTOs\AvailabilitySlotDTO;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

class SchedulerService
{
    private const DAY_MAP = [
        'mon' => Carbon::MONDAY,
        'tue' => Carbon::TUESDAY,
        'wed' => Carbon::WEDNESDAY,
        'thu' => Carbon::THURSDAY,
        'fri' => Carbon::FRIDAY,
        'sat' => Carbon::SATURDAY,
        'sun' => Carbon::SUNDAY,
    ];

    /**
     * Distribute tasks across available time slots.
     *
     * @param array $normalizedPlan The normalized plan with weeks/tasks
     * @param AvailabilitySlotDTO[] $availability
     * @param string $startDate Y-m-d
     * @param string $timezone
     * @param float $hoursPerWeek
     * @return array{slots: array, warnings: array}
     */
    public function schedule(
        array $normalizedPlan,
        array $availability,
        string $startDate,
        string $timezone,
        float $hoursPerWeek,
    ): array {
        $slots = [];
        $warnings = [];
        $currentDate = CarbonImmutable::parse($startDate, $timezone)->startOfDay();

        $availableMinutesPerWeek = $hoursPerWeek * 60;

        foreach ($normalizedPlan['weeks'] as $week) {
            $weekNumber = $week['week'];
            $weekStart = $currentDate->addWeeks($weekNumber - 1)->startOfWeek(Carbon::MONDAY);

            $weekSlots = $this->buildWeekSlots($weekStart, $availability, $timezone);
            $totalAvailableMinutes = array_sum(array_map(fn ($s) => $s['duration_minutes'], $weekSlots));

            $totalNeededMinutes = 0;
            foreach ($week['tasks'] as $task) {
                $totalNeededMinutes += $task['estimate_hours'] * 60;
            }

            $effectiveAvailable = min($totalAvailableMinutes, $availableMinutesPerWeek);

            if ($totalNeededMinutes > $effectiveAvailable) {
                $warnings[] = [
                    'week' => $weekNumber,
                    'type' => 'overflow',
                    'message' => sprintf(
                        'Week %d needs %.1fh but only %.1fh available. Tasks will overflow.',
                        $weekNumber,
                        $totalNeededMinutes / 60,
                        $effectiveAvailable / 60
                    ),
                ];
            }

            $slotIndex = 0;
            $slotUsedMinutes = 0;

            foreach ($week['tasks'] as $task) {
                $remainingMinutes = (int) ($task['estimate_hours'] * 60);

                while ($remainingMinutes > 0 && $slotIndex < count($weekSlots)) {
                    $slot = $weekSlots[$slotIndex];
                    $availInSlot = $slot['duration_minutes'] - $slotUsedMinutes;

                    if ($availInSlot <= 0) {
                        $slotIndex++;
                        $slotUsedMinutes = 0;
                        continue;
                    }

                    $assigned = min($remainingMinutes, $availInSlot);

                    $startTime = Carbon::parse($slot['start'], $timezone)->addMinutes($slotUsedMinutes);
                    $endTime = (clone $startTime)->addMinutes($assigned);

                    $slots[] = [
                        'week' => $weekNumber,
                        'task_title' => $task['title'],
                        'date' => $slot['date'],
                        'start' => $startTime->format('H:i'),
                        'end' => $endTime->format('H:i'),
                        'minutes' => $assigned,
                    ];

                    $slotUsedMinutes += $assigned;
                    $remainingMinutes -= $assigned;

                    if ($slotUsedMinutes >= $slot['duration_minutes']) {
                        $slotIndex++;
                        $slotUsedMinutes = 0;
                    }
                }

                if ($remainingMinutes > 0) {
                    $warnings[] = [
                        'week' => $weekNumber,
                        'type' => 'unscheduled',
                        'message' => sprintf(
                            'Task "%s" has %.0f unscheduled minutes in week %d.',
                            $task['title'],
                            $remainingMinutes,
                            $weekNumber
                        ),
                    ];
                }
            }
        }

        return [
            'slots' => $slots,
            'warnings' => $warnings,
        ];
    }

    /**
     * Build available time slots for a given week.
     *
     * @return array<array{date: string, start: string, end: string, duration_minutes: int}>
     */
    private function buildWeekSlots(
        CarbonImmutable $weekStart,
        array $availability,
        string $timezone,
    ): array {
        $slots = [];

        for ($dayOffset = 0; $dayOffset < 7; $dayOffset++) {
            $date = $weekStart->addDays($dayOffset);
            $dayOfWeek = $date->dayOfWeekIso;

            foreach ($availability as $avail) {
                $mappedDay = self::DAY_MAP[$avail->day] ?? null;
                if ($mappedDay === null || $mappedDay !== $dayOfWeek) {
                    continue;
                }

                $duration = $avail->durationMinutes();
                if ($duration <= 0) {
                    continue;
                }

                $slots[] = [
                    'date' => $date->format('Y-m-d'),
                    'start' => $avail->start,
                    'end' => $avail->end,
                    'duration_minutes' => $duration,
                ];
            }
        }

        usort($slots, fn ($a, $b) => $a['date'] <=> $b['date'] ?: $a['start'] <=> $b['start']);

        return $slots;
    }
}
