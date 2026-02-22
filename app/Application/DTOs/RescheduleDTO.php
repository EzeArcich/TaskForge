<?php

namespace App\Application\DTOs;

final readonly class RescheduleDTO
{
    /** @param AvailabilitySlotDTO[] $availability */
    public function __construct(
        public array $availability,
        public string $startDate,
        public float $hoursPerWeek,
        public int $maxMinutesPerDay,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            availability: array_map(
                fn (array $slot) => AvailabilitySlotDTO::fromArray($slot),
                $data['availability'] ?? []
            ),
            startDate: $data['start_date'],
            hoursPerWeek: (float) ($data['hours_per_week'] ?? 7.5),
            maxMinutesPerDay: (int) ($data['max_minutes_per_day'] ?? config('dailypro.scheduler.default_max_minutes_per_day', 60)),
        );
    }
}
