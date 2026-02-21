<?php

namespace App\Application\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class NormalizedPlanValidator
{
    /**
     * Validate AI-normalized plan structure.
     *
     * @throws ValidationException
     */
    public function validate(array $data): array
    {
        $validator = Validator::make($data, [
            'title' => 'required|string|min:1|max:500',
            'timezone' => 'required|string|timezone',
            'start_date' => 'required|date_format:Y-m-d',
            'weeks' => 'required|array|min:1',
            'weeks.*.week' => 'required|integer|min:1',
            'weeks.*.goal' => 'required|string|min:1|max:1000',
            'weeks.*.tasks' => 'required|array|min:1',
            'weeks.*.tasks.*.title' => 'required|string|min:1|max:500',
            'weeks.*.tasks.*.estimate_hours' => 'required|numeric|min:0.25|max:40',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * Check if the structure is valid without throwing.
     */
    public function isValid(array $data): bool
    {
        try {
            $this->validate($data);
            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    /**
     * Get validation errors without throwing.
     */
    public function errors(array $data): array
    {
        $validator = Validator::make($data, [
            'title' => 'required|string|min:1|max:500',
            'timezone' => 'required|string|timezone',
            'start_date' => 'required|date_format:Y-m-d',
            'weeks' => 'required|array|min:1',
            'weeks.*.week' => 'required|integer|min:1',
            'weeks.*.goal' => 'required|string|min:1|max:1000',
            'weeks.*.tasks' => 'required|array|min:1',
            'weeks.*.tasks.*.title' => 'required|string|min:1|max:500',
            'weeks.*.tasks.*.estimate_hours' => 'required|numeric|min:0.25|max:40',
        ]);

        if ($validator->fails()) {
            return $validator->errors()->toArray();
        }

        return [];
    }
}
