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
     * @param int $maxMinutesPerDay Per-day cap for planned focus minutes
     * @param array<string, array<int, array{start: int, end: int}>> $occupiedSlotsByDate Existing occupied intervals
     * @return array{slots: array, warnings: array}
     */
    public function schedule(
        array $normalizedPlan,
        array $availability,
        string $startDate,
        string $timezone,
        float $hoursPerWeek,
        int $maxMinutesPerDay = 60,
        array $occupiedSlotsByDate = [],
    ): array {
        $slots = [];
        $warnings = [];
        $currentDate = CarbonImmutable::parse($startDate, $timezone)->startOfDay();

        $availableMinutesPerWeek = $hoursPerWeek * 60;

        foreach ($normalizedPlan['weeks'] as $week) {
            $weekNumber = $week['week'];
            $weekStart = $currentDate->addWeeks($weekNumber - 1)->startOfWeek(Carbon::MONDAY);

            $weekSlots = $this->buildWeekSlots($weekStart, $availability, $maxMinutesPerDay, $occupiedSlotsByDate);
            $totalAvailableMinutes = array_sum(array_map(fn ($s) => $s['duration_minutes'], $weekSlots));

            $totalNeededMinutes = 0;
            foreach ($week['tasks'] as $task) {
                $totalNeededMinutes += $task['estimate_hours'] * 60;
            }

            $effectiveAvailable = min($totalAvailableMinutes, $availableMinutesPerWeek);
            $remainingWeekBudget = (int) $effectiveAvailable;

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

                while ($remainingMinutes > 0 && $slotIndex < count($weekSlots) && $remainingWeekBudget > 0) {
                    $slot = $weekSlots[$slotIndex];
                    $availInSlot = $slot['duration_minutes'] - $slotUsedMinutes;

                    if ($availInSlot <= 0) {
                        $slotIndex++;
                        $slotUsedMinutes = 0;
                        continue;
                    }

                    $assigned = min($remainingMinutes, $availInSlot, $remainingWeekBudget);
                    $startMinutes = $slot['start_minutes'] + $slotUsedMinutes;
                    $endMinutes = $startMinutes + $assigned;

                    $slots[] = [
                        'week' => $weekNumber,
                        'task_title' => $task['title'],
                        'date' => $slot['date'],
                        'start' => $this->minutesToTime($startMinutes),
                        'end' => $this->minutesToTime($endMinutes),
                        'minutes' => $assigned,
                    ];

                    $slotUsedMinutes += $assigned;
                    $remainingMinutes -= $assigned;
                    $remainingWeekBudget -= $assigned;

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
     * @param AvailabilitySlotDTO[] $availability
     * @param array<string, array<int, array{start: int, end: int}>> $occupiedSlotsByDate
     * @return array<array{date: string, start_minutes: int, duration_minutes: int}>
     */
    private function buildWeekSlots(
        CarbonImmutable $weekStart,
        array $availability,
        int $maxMinutesPerDay,
        array $occupiedSlotsByDate,
    ): array {
        $slotsByDate = [];

        for ($dayOffset = 0; $dayOffset < 7; $dayOffset++) {
            $date = $weekStart->addDays($dayOffset);
            $dateKey = $date->format('Y-m-d');
            $dayOfWeek = $date->dayOfWeekIso;
            $rawSegments = [];

            foreach ($availability as $avail) {
                $mappedDay = self::DAY_MAP[$avail->day] ?? null;
                if ($mappedDay === null || $mappedDay !== $dayOfWeek) {
                    continue;
                }

                $startMinutes = $this->timeToMinutes($avail->start);
                $endMinutes = $this->timeToMinutes($avail->end);
                if ($endMinutes <= $startMinutes) {
                    continue;
                }

                $rawSegments[] = ['start' => $startMinutes, 'end' => $endMinutes];
            }

            if (empty($rawSegments)) {
                continue;
            }

            usort($rawSegments, fn ($a, $b) => $a['start'] <=> $b['start']);

            $occupied = $occupiedSlotsByDate[$dateKey] ?? [];
            if (! empty($occupied)) {
                usort($occupied, fn ($a, $b) => $a['start'] <=> $b['start']);
            }

            $freeSegments = [];
            foreach ($rawSegments as $segment) {
                foreach ($this->subtractOccupied($segment['start'], $segment['end'], $occupied) as $free) {
                    if ($free['end'] > $free['start']) {
                        $freeSegments[] = $free;
                    }
                }
            }

            if (empty($freeSegments)) {
                continue;
            }

            usort($freeSegments, fn ($a, $b) => $a['start'] <=> $b['start']);

            $dailyLimit = $maxMinutesPerDay > 0 ? $maxMinutesPerDay : PHP_INT_MAX;
            $usedToday = 0;

            foreach ($freeSegments as $segment) {
                if ($usedToday >= $dailyLimit) {
                    break;
                }

                $segmentDuration = $segment['end'] - $segment['start'];
                $allowedDuration = min($segmentDuration, $dailyLimit - $usedToday);
                if ($allowedDuration <= 0) {
                    continue;
                }

                $slotsByDate[$dateKey][] = [
                    'date' => $dateKey,
                    'start_minutes' => $segment['start'],
                    'duration_minutes' => $allowedDuration,
                ];
                $usedToday += $allowedDuration;
            }
        }

        $slots = [];
        ksort($slotsByDate);
        foreach ($slotsByDate as $dailySlots) {
            usort($dailySlots, fn ($a, $b) => $a['start_minutes'] <=> $b['start_minutes']);
            foreach ($dailySlots as $slot) {
                $slots[] = $slot;
            }
        }

        return $slots;
    }

    /**
     * @param array<int, array{start: int, end: int}> $occupied
     * @return array<int, array{start: int, end: int}>
     */
    private function subtractOccupied(int $start, int $end, array $occupied): array
    {
        $segments = [['start' => $start, 'end' => $end]];

        foreach ($occupied as $block) {
            $next = [];
            foreach ($segments as $segment) {
                if ($block['end'] <= $segment['start'] || $block['start'] >= $segment['end']) {
                    $next[] = $segment;
                    continue;
                }

                if ($block['start'] > $segment['start']) {
                    $next[] = [
                        'start' => $segment['start'],
                        'end' => min($block['start'], $segment['end']),
                    ];
                }

                if ($block['end'] < $segment['end']) {
                    $next[] = [
                        'start' => max($block['end'], $segment['start']),
                        'end' => $segment['end'],
                    ];
                }
            }
            $segments = $next;
            if (empty($segments)) {
                break;
            }
        }

        return $segments;
    }

    private function timeToMinutes(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', substr($time, 0, 5)));
        return ($hours * 60) + $minutes;
    }

    private function minutesToTime(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;
        return sprintf('%02d:%02d', $hours, $mins);
    }
}
