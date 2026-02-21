<?php

namespace Tests\Unit;

use App\Application\DTOs\AvailabilitySlotDTO;
use PHPUnit\Framework\TestCase;

class AvailabilitySlotDTOTest extends TestCase
{
    public function test_from_array(): void
    {
        $dto = AvailabilitySlotDTO::fromArray([
            'day' => 'MON',
            'start' => '20:00',
            'end' => '21:30',
        ]);

        $this->assertEquals('mon', $dto->day);
        $this->assertEquals('20:00', $dto->start);
        $this->assertEquals('21:30', $dto->end);
    }

    public function test_to_array(): void
    {
        $dto = new AvailabilitySlotDTO('tue', '09:00', '12:00');
        $arr = $dto->toArray();

        $this->assertEquals(['day' => 'tue', 'start' => '09:00', 'end' => '12:00'], $arr);
    }

    public function test_duration_minutes(): void
    {
        $dto = new AvailabilitySlotDTO('mon', '20:00', '21:30');
        $this->assertEquals(90, $dto->durationMinutes());
    }

    public function test_duration_minutes_full_hour(): void
    {
        $dto = new AvailabilitySlotDTO('mon', '09:00', '12:00');
        $this->assertEquals(180, $dto->durationMinutes());
    }
}
