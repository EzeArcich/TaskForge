<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan_text' => 'required|string|min:10|max:50000',
            'settings' => 'required|array',
            'settings.timezone' => 'required|string|timezone',
            'settings.start_date' => 'required|date_format:Y-m-d',
            'settings.availability' => 'required|array|min:1',
            'settings.availability.*.day' => 'required|string|in:mon,tue,wed,thu,fri,sat,sun',
            'settings.availability.*.start' => ['required', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'settings.availability.*.end' => ['required', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'settings.hours_per_week' => 'required|numeric|min:0.5|max:80',
            'settings.max_minutes_per_day' => 'sometimes|integer|min:15|max:720',
            'settings.kanban_provider' => 'sometimes|string|in:trello',
            'settings.calendar_provider' => 'sometimes|string|in:google',
            'settings.reminders' => 'sometimes|array',
            'settings.reminders.email' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'plan_text.min' => 'Plan text must be at least 10 characters to be meaningful.',
            'settings.availability.min' => 'At least one availability slot is required.',
            'settings.availability.*.day.in' => 'Day must be one of: mon, tue, wed, thu, fri, sat, sun.',
        ];
    }
}
