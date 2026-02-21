<?php

namespace App\Application\DTOs;

use Illuminate\Support\Arr;

final readonly class CreatePlanDTO
{
    /** @param AvailabilitySlotDTO[] $availability */
    public function __construct(
        public string $planText,
        public string $timezone,
        public string $startDate,
        public array $availability,
        public float $hoursPerWeek,
        public string $kanbanProvider,
        public string $calendarProvider,
        public bool $emailReminders,
    ) {}

    public static function fromRequest(array $data): self
    {
        $settings = $data['settings'];

        return new self(
            planText: $data['plan_text'],
            timezone: $settings['timezone'] ?? 'UTC',
            startDate: $settings['start_date'],
            availability: array_map(
                fn (array $slot) => AvailabilitySlotDTO::fromArray($slot),
                $settings['availability'] ?? []
            ),
            hoursPerWeek: (float) ($settings['hours_per_week'] ?? 7.5),
            kanbanProvider: $settings['kanban_provider'] ?? 'trello',
            calendarProvider: $settings['calendar_provider'] ?? 'google',
            emailReminders: Arr::get($settings, 'reminders.email', false),
        );
    }

    public function toSettingsArray(): array
    {
        return [
            'timezone' => $this->timezone,
            'start_date' => $this->startDate,
            'availability' => array_map(fn (AvailabilitySlotDTO $s) => $s->toArray(), $this->availability),
            'hours_per_week' => $this->hoursPerWeek,
            'kanban_provider' => $this->kanbanProvider,
            'calendar_provider' => $this->calendarProvider,
            'reminders' => ['email' => $this->emailReminders],
        ];
    }
}
