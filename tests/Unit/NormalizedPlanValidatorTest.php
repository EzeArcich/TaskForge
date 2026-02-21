<?php

namespace Tests\Unit;

use App\Application\Services\NormalizedPlanValidator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class NormalizedPlanValidatorTest extends TestCase
{
    private NormalizedPlanValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new NormalizedPlanValidator();
    }

    private function validPlan(): array
    {
        return [
            'title' => 'Learn Laravel',
            'timezone' => 'America/Argentina/Buenos_Aires',
            'start_date' => '2025-02-01',
            'weeks' => [
                [
                    'week' => 1,
                    'goal' => 'Setup and basics',
                    'tasks' => [
                        ['title' => 'Install Laravel', 'estimate_hours' => 1],
                        ['title' => 'Read documentation', 'estimate_hours' => 2],
                    ],
                ],
            ],
        ];
    }

    public function test_validates_correct_plan(): void
    {
        $result = $this->validator->validate($this->validPlan());

        $this->assertEquals('Learn Laravel', $result['title']);
        $this->assertCount(1, $result['weeks']);
    }

    public function test_is_valid_returns_true_for_valid_plan(): void
    {
        $this->assertTrue($this->validator->isValid($this->validPlan()));
    }

    public function test_is_valid_returns_false_for_invalid_plan(): void
    {
        $this->assertFalse($this->validator->isValid([]));
    }

    public function test_rejects_missing_title(): void
    {
        $plan = $this->validPlan();
        unset($plan['title']);

        $this->expectException(ValidationException::class);
        $this->validator->validate($plan);
    }

    public function test_rejects_missing_weeks(): void
    {
        $plan = $this->validPlan();
        unset($plan['weeks']);

        $this->expectException(ValidationException::class);
        $this->validator->validate($plan);
    }

    public function test_rejects_empty_weeks(): void
    {
        $plan = $this->validPlan();
        $plan['weeks'] = [];

        $this->expectException(ValidationException::class);
        $this->validator->validate($plan);
    }

    public function test_rejects_missing_task_title(): void
    {
        $plan = $this->validPlan();
        unset($plan['weeks'][0]['tasks'][0]['title']);

        $this->expectException(ValidationException::class);
        $this->validator->validate($plan);
    }

    public function test_rejects_invalid_estimate_hours(): void
    {
        $plan = $this->validPlan();
        $plan['weeks'][0]['tasks'][0]['estimate_hours'] = 0; // below min 0.25

        $this->expectException(ValidationException::class);
        $this->validator->validate($plan);
    }

    public function test_rejects_estimate_hours_over_max(): void
    {
        $plan = $this->validPlan();
        $plan['weeks'][0]['tasks'][0]['estimate_hours'] = 50; // over max 40

        $this->expectException(ValidationException::class);
        $this->validator->validate($plan);
    }

    public function test_rejects_invalid_timezone(): void
    {
        $plan = $this->validPlan();
        $plan['timezone'] = 'Invalid/Zone';

        $this->expectException(ValidationException::class);
        $this->validator->validate($plan);
    }

    public function test_rejects_invalid_date_format(): void
    {
        $plan = $this->validPlan();
        $plan['start_date'] = '01-02-2025';

        $this->expectException(ValidationException::class);
        $this->validator->validate($plan);
    }

    public function test_errors_returns_array_of_errors(): void
    {
        $errors = $this->validator->errors([]);

        $this->assertArrayHasKey('title', $errors);
        $this->assertArrayHasKey('weeks', $errors);
    }

    public function test_errors_returns_empty_for_valid_plan(): void
    {
        $errors = $this->validator->errors($this->validPlan());

        $this->assertEmpty($errors);
    }
}
