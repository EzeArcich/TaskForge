<?php

namespace App\Application\DTOs;

final readonly class AvailabilitySlotDTO
{
    public function __construct(
        public string $day,
        public string $start,
        public string $end,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            day: strtolower($data['day']),
            start: $data['start'],
            end: $data['end'],
        );
    }

    public function toArray(): array
    {
        return [
            'day' => $this->day,
            'start' => $this->start,
            'end' => $this->end,
        ];
    }

    public function durationMinutes(): int
    {
        $start = strtotime($this->start);
        $end = strtotime($this->end);

        return ($end - $start) / 60;
    }
}
