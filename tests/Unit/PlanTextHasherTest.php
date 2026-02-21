<?php

namespace Tests\Unit;

use App\Application\Services\PlanTextHasher;
use PHPUnit\Framework\TestCase;

class PlanTextHasherTest extends TestCase
{
    private PlanTextHasher $hasher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hasher = new PlanTextHasher();
    }

    public function test_same_input_produces_same_hash(): void
    {
        $text = 'Learn Laravel in 4 weeks';
        $settings = ['timezone' => 'UTC', 'start_date' => '2025-01-20'];

        $hash1 = $this->hasher->hash($text, $settings);
        $hash2 = $this->hasher->hash($text, $settings);

        $this->assertEquals($hash1, $hash2);
    }

    public function test_different_text_produces_different_hash(): void
    {
        $settings = ['timezone' => 'UTC'];

        $hash1 = $this->hasher->hash('Plan A', $settings);
        $hash2 = $this->hasher->hash('Plan B', $settings);

        $this->assertNotEquals($hash1, $hash2);
    }

    public function test_different_settings_produces_different_hash(): void
    {
        $text = 'Same plan';

        $hash1 = $this->hasher->hash($text, ['timezone' => 'UTC']);
        $hash2 = $this->hasher->hash($text, ['timezone' => 'America/Argentina/Buenos_Aires']);

        $this->assertNotEquals($hash1, $hash2);
    }

    public function test_normalizes_whitespace(): void
    {
        $settings = ['timezone' => 'UTC'];

        $hash1 = $this->hasher->hash("Plan  with   spaces", $settings);
        $hash2 = $this->hasher->hash("Plan with spaces", $settings);

        $this->assertEquals($hash1, $hash2);
    }

    public function test_normalizes_line_endings(): void
    {
        $settings = ['timezone' => 'UTC'];

        $hash1 = $this->hasher->hash("Line1\r\nLine2", $settings);
        $hash2 = $this->hasher->hash("Line1\nLine2", $settings);

        $this->assertEquals($hash1, $hash2);
    }

    public function test_trims_whitespace(): void
    {
        $settings = ['timezone' => 'UTC'];

        $hash1 = $this->hasher->hash('  Plan  ', $settings);
        $hash2 = $this->hasher->hash('Plan', $settings);

        $this->assertEquals($hash1, $hash2);
    }

    public function test_sorts_settings_keys(): void
    {
        $text = 'Plan';

        $hash1 = $this->hasher->hash($text, ['a' => 1, 'b' => 2]);
        $hash2 = $this->hasher->hash($text, ['b' => 2, 'a' => 1]);

        $this->assertEquals($hash1, $hash2);
    }

    public function test_sorts_availability_by_day(): void
    {
        $text = 'Plan';

        $settings1 = [
            'availability' => [
                ['day' => 'tue', 'start' => '20:00', 'end' => '21:30'],
                ['day' => 'mon', 'start' => '20:00', 'end' => '21:30'],
            ],
        ];

        $settings2 = [
            'availability' => [
                ['day' => 'mon', 'start' => '20:00', 'end' => '21:30'],
                ['day' => 'tue', 'start' => '20:00', 'end' => '21:30'],
            ],
        ];

        $hash1 = $this->hasher->hash($text, $settings1);
        $hash2 = $this->hasher->hash($text, $settings2);

        $this->assertEquals($hash1, $hash2);
    }

    public function test_produces_64_char_sha256(): void
    {
        $hash = $this->hasher->hash('test', ['a' => 1]);

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hash);
    }
}
