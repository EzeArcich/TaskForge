<?php

namespace App\Infrastructure\Calendar;

use App\Application\Contracts\CalendarProviderInterface;
use App\Models\Plan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleCalendarProvider implements CalendarProviderInterface
{
    private string $baseUrl = 'https://www.googleapis.com/calendar/v3';

    public function createEvents(Plan $plan): array
    {
        $accessToken = $this->getAccessToken();
        $timezone = $plan->settings['timezone'] ?? 'UTC';
        $calendarId = 'primary';
        $eventIds = [];

        $plan->load('tasks');

        foreach ($plan->tasks as $task) {
            if (! $task->scheduled_date || ! $task->scheduled_start || ! $task->scheduled_end) {
                continue;
            }

            $startDateTime = $task->scheduled_date->format('Y-m-d') . 'T' . $task->scheduled_start . ':00';
            $endDateTime = $task->scheduled_date->format('Y-m-d') . 'T' . $task->scheduled_end . ':00';

            $event = $this->request($accessToken, 'POST', "/calendars/{$calendarId}/events", [
                'summary' => 'Deep Work: ' . $task->title,
                'description' => sprintf(
                    "DailyPro Task\nEstimate: %.1fh\nPlan: %s",
                    $task->estimate_hours,
                    $plan->normalized_json['title'] ?? ''
                ),
                'start' => [
                    'dateTime' => $startDateTime,
                    'timeZone' => $timezone,
                ],
                'end' => [
                    'dateTime' => $endDateTime,
                    'timeZone' => $timezone,
                ],
                'reminders' => [
                    'useDefault' => false,
                    'overrides' => [
                        ['method' => 'popup', 'minutes' => 10],
                    ],
                ],
            ]);

            $eventIds[$task->id] = $event['id'];
        }

        Log::info('Google Calendar events created', [
            'plan_id' => $plan->id,
            'events_created' => count($eventIds),
        ]);

        return [
            'calendar_id' => $calendarId,
            'event_ids' => $eventIds,
        ];
    }

    public function updateEvents(Plan $plan): void
    {
        $accessToken = $this->getAccessToken();
        $timezone = $plan->settings['timezone'] ?? 'UTC';
        $calendarId = $plan->google_calendar_id ?? 'primary';

        $plan->load('tasks');

        foreach ($plan->tasks as $task) {
            if (! $task->google_event_id || ! $task->scheduled_date) {
                continue;
            }

            $startDateTime = $task->scheduled_date->format('Y-m-d') . 'T' . $task->scheduled_start . ':00';
            $endDateTime = $task->scheduled_date->format('Y-m-d') . 'T' . $task->scheduled_end . ':00';

            $this->request($accessToken, 'PUT', "/calendars/{$calendarId}/events/{$task->google_event_id}", [
                'summary' => 'Deep Work: ' . $task->title,
                'start' => [
                    'dateTime' => $startDateTime,
                    'timeZone' => $timezone,
                ],
                'end' => [
                    'dateTime' => $endDateTime,
                    'timeZone' => $timezone,
                ],
            ]);
        }
    }

    public function deleteEvents(Plan $plan): void
    {
        $accessToken = $this->getAccessToken();
        $calendarId = $plan->google_calendar_id ?? 'primary';

        $plan->load('tasks');

        foreach ($plan->tasks as $task) {
            if (! $task->google_event_id) {
                continue;
            }

            try {
                $this->request($accessToken, 'DELETE', "/calendars/{$calendarId}/events/{$task->google_event_id}");
            } catch (\Throwable $e) {
                Log::warning('Failed to delete calendar event', [
                    'event_id' => $task->google_event_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function getAccessToken(): string
    {
        // In production, this would use OAuth refresh token flow.
        // For MVP, we use a stored access token from config/session.
        $token = config('dailypro.google.access_token');
        if (! $token) {
            throw new \RuntimeException('Google Calendar access token not configured. Complete OAuth flow first.');
        }

        return $token;
    }

    private function request(string $accessToken, string $method, string $path, array $data = []): array
    {
        $url = $this->baseUrl . $path;

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
        ])->retry(2, 500);

        $response = match ($method) {
            'GET' => $response->get($url, $data),
            'POST' => $response->post($url, $data),
            'PUT' => $response->put($url, $data),
            'DELETE' => $response->delete($url),
            default => throw new \InvalidArgumentException("Unsupported method: {$method}"),
        };

        if ($method === 'DELETE' && $response->successful()) {
            return [];
        }

        $response->throw();

        return $response->json() ?? [];
    }
}
