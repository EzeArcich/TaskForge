<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RescheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'availability' => 'required|array|min:1',
            'availability.*.day' => 'required|string|in:mon,tue,wed,thu,fri,sat,sun',
            'availability.*.start' => ['required', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'availability.*.end' => ['required', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'start_date' => 'required|date_format:Y-m-d',
            'hours_per_week' => 'required|numeric|min:0.5|max:80',
            'max_minutes_per_day' => 'sometimes|integer|min:15|max:720',
        ];
    }
}
